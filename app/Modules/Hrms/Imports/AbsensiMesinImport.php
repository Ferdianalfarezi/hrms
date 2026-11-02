<?php

namespace App\Modules\Hrms\Imports;

use App\Modules\Hrms\Models\Absensi;
use App\Modules\Hrms\Models\AbsensiMesin;
use App\Modules\Hrms\Models\Karyawan;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use OpenSpout\Reader\XLSX\Reader;
use RuntimeException;
use Throwable;

/**
 * Import laporan kehadiran (format "Attendance Report"): kolom Attendance Date, Employee Code, Employee Name, Time In, Time Out.
 * Isinya karyawan yang HADIR (tercatat di mesin). Satu upload = laporan satu hari kerja, yaitu tanggal terakhir di file.
 *
 * Attendance Date shift malam = tanggal mulai shift (mis. masuk 29/10 18:46, pulang 30/10 06:21 tercatat 29/10).
 * Jadi hari kerja X = baris tanggal X-1 yang masuk malam (Time In >= 12:00) + baris tanggal X yang masuk pagi.
 * Baris tanpa Time In dicatat di tanggal tap pulangnya, jadi dihitung untuk tanggal itu sendiri.
 * Shift dibaca dari jam masuk, bukan dari rotasi grup, supaya karyawan yang tukar shift / belum punya grup tetap terbaca.
 * Karyawan terdaftar yang tidak ditemukan dianggap tidak absen dan disimpan ke absensi_mesins.
 * Upload ulang hari kerja yang sama mengganti seluruh data hari itu.
 *
 * Time In juga dipakai mencatat keterlambatan (kode DT< / DT>) ke tabel absensi: acuan shift pagi 07:00, shift malam 20:00.
 * DT otomatis ditandai keterangan "Mesin absen: ..."; upload ulang memperbarui / menghapusnya. Kode lain atau DT yang
 * diisi manual tidak ditimpa.
 *
 * File .xlsx dibaca dengan OpenSpout. File .xls berupa stream BIFF tanpa kontainer OLE dibaca dengan parser kecil di bawah.
 */
class AbsensiMesinImport
{
    /** Nama header yang dikenali (huruf kecil, tanpa spasi/tanda baca). */
    private const HEADERS = [
        'nik' => ['employeecode', 'nik'],
        'tanggal' => ['attendancedate', 'tanggal'],
        'masuk' => ['timein', 'jammasuk'],
    ];

    /** Time In mulai jam ini dianggap shift malam (shift pagi masuk 05.00 - 08.00, shift malam 16.00 - 20.00). */
    private const JAM_MULAI_MALAM = 12;

    /** Jam masuk acuan keterlambatan, dalam menit sejak 00:00. */
    private const ACUAN_PAGI = 7 * 60;

    private const ACUAN_MALAM = 20 * 60;

    /** Penanda keterangan DT yang dibuat otomatis dari upload ini. */
    public const PENANDA_DT = 'Mesin absen';

    /** Panjang minimal NIK; dipakai mengembalikan nol di depan kalau Employee Code terbaca sebagai angka. */
    private const NIK_DIGITS = 4;

    /**
     * @return array{tanggal: string, hadir: int, total: int, dilewati: int, terlambat: int, peringatan: ?string}
     */
    public function import(string $path, string $extension, ?int $userId): array
    {
        $rows = $this->readRows($path, Str::lower($extension));
        [$headerRow, $columns] = $this->locateHeader($rows);

        // [Y-m-d][nik] => menit Time In sejak 00:00 (null kalau kosong), dipisah menurut jam masuk.
        $pagi = [];
        $malam = [];
        foreach (array_slice($rows, $headerRow + 1) as $cells) {
            $nik = $this->nik($cells[$columns['nik']] ?? null);
            if ($nik === null) {
                continue;
            }

            $tanggal = $this->date($cells[$columns['tanggal']] ?? null);
            if ($tanggal === null) {
                throw new RuntimeException("Tanggal untuk Employee Code {$nik} kosong atau formatnya tidak dikenali.");
            }

            $masuk = $this->minuteOfDay($cells[$columns['masuk']] ?? null);
            if ($masuk !== null && $masuk >= self::JAM_MULAI_MALAM * 60) {
                $malam[$tanggal][$nik] = $this->earliest($malam[$tanggal][$nik] ?? null, $masuk);
            } else {
                $pagi[$tanggal][$nik] = $this->earliest($pagi[$tanggal][$nik] ?? null, $masuk);
            }
        }

        if ($pagi === [] && $malam === []) {
            throw new RuntimeException('Tidak ada data karyawan di file.');
        }

        $now = now();
        $hariKerja = Carbon::createFromFormat('!Y-m-d', max([...array_keys($pagi), ...array_keys($malam)]));
        $tanggal = $hariKerja->toDateString();
        $kemarin = $hariKerja->copy()->subDay()->toDateString();

        // Hanya baris yang masuk ke hari kerja ini.
        $hadir = ($pagi[$tanggal] ?? []) + ($malam[$kemarin] ?? []);

        // Employee Code yang tidak ada di data karyawan hanya dihitung (mis. karyawan sudah keluar / belum diinput).
        $niks = collect(array_keys($hadir))->map(fn ($nik) => (string) $nik);
        $terdaftar = array_flip(Karyawan::whereIn('nik', $niks)->pluck('nik')->all());
        $dilewati = $niks->reject(fn (string $nik) => isset($terdaftar[$nik]))->count();

        // Karyawan terdaftar (sudah masuk kerja pada hari itu) yang tidak ada di file: [nik] => nama.
        $karyawanAktif = Karyawan::query()
            ->where(fn ($query) => $query->whereNull('tanggal_masuk')->orWhereDate('tanggal_masuk', '<=', $tanggal))
            ->get(['nik', 'nama']);
        $tidakAbsen = $karyawanAktif
            ->reject(fn (Karyawan $karyawan) => array_key_exists($karyawan->nik, $hadir))
            ->pluck('nama', 'nik')
            ->all();

        $peringatan = isset($pagi[$kemarin]) || isset($malam[$kemarin]) ? null
            : 'File tidak berisi tanggal '.$hariKerja->copy()->subDay()->format('d/m/Y').', jadi shift malam dianggap tidak absen.';

        $terlambat = [
            ...$this->terlambat($pagi[$tanggal] ?? [], $tanggal, self::ACUAN_PAGI, 'pagi'),
            ...$this->terlambat($malam[$kemarin] ?? [], $kemarin, self::ACUAN_MALAM, 'malam'),
        ];

        $dicatat = DB::transaction(function () use ($tanggal, $tidakAbsen, $userId, $now, $terlambat) {
            AbsensiMesin::whereDate('tanggal', $tanggal)->delete();

            foreach (array_chunk($tidakAbsen, 500, true) as $chunk) {
                AbsensiMesin::insert(array_map(fn ($nik, $nama) => [
                    'tanggal' => $tanggal, 'nik' => (string) $nik, 'nama' => $nama, 'uploaded_by' => $userId,
                    'created_at' => $now, 'updated_at' => $now,
                ], array_keys($chunk), $chunk));
            }

            return $this->simpanTerlambat($terlambat, $userId);
        });

        return [
            'tanggal' => $tanggal,
            'hadir' => $karyawanAktif->count() - count($tidakAbsen),
            'total' => count($tidakAbsen),
            'dilewati' => $dilewati,
            'terlambat' => $dicatat,
            'peringatan' => $peringatan,
        ];
    }

    /**
     * Keterlambatan per karyawan terdaftar di satu tanggal shift. Menit 0 = tepat waktu (DT otomatis lama dihapus).
     *
     * @param  array<string, ?int>  $masuk  [nik] => menit Time In
     * @return list<array{karyawan_id: int, tanggal: string, menit: int, keterangan: string}>
     */
    private function terlambat(array $masuk, string $tanggal, int $acuan, string $shift): array
    {
        $masuk = array_filter($masuk, fn (?int $menit) => $menit !== null);
        if ($masuk === []) {
            return [];
        }

        $ids = Karyawan::whereIn('nik', array_map('strval', array_keys($masuk)))->pluck('id', 'nik');
        $jamAcuan = $this->jam($acuan);

        return $ids->map(fn (int $id, int|string $nik) => [
            'karyawan_id' => $id,
            'tanggal' => $tanggal,
            'menit' => min(max(0, $masuk[$nik] - $acuan), 720),
            'keterangan' => self::PENANDA_DT.': Time In '.$this->jam($masuk[$nik])." (shift {$shift}, acuan {$jamAcuan})",
        ])->values()->all();
    }

    /**
     * Catat DT otomatis dan kembalikan jumlah DT yang tercatat.
     * Absensi lain (kode selain DT, DT manual, atau yang sedang diajukan perubahan) tidak disentuh.
     *
     * @param  list<array{karyawan_id: int, tanggal: string, menit: int, keterangan: string}>  $terlambat
     */
    private function simpanTerlambat(array $terlambat, ?int $userId): int
    {
        if ($terlambat === []) {
            return 0;
        }

        $tanggals = array_column($terlambat, 'tanggal');
        $dicatat = 0;

        $existing = Absensi::query()
            ->withCount(['approvals', 'pendingApproval'])
            ->whereIn('karyawan_id', array_column($terlambat, 'karyawan_id'))
            ->whereDate('tanggal', '>=', min($tanggals))
            ->whereDate('tanggal', '<=', max($tanggals))
            ->get()
            ->keyBy(fn (Absensi $absensi) => $absensi->karyawan_id.'|'.$absensi->tanggal->toDateString());

        foreach ($terlambat as $item) {
            $absensi = $existing[$item['karyawan_id'].'|'.$item['tanggal']] ?? null;
            $kode = $item['menit'] > Absensi::MENIT_PER_POTONGAN ? 'DT>' : 'DT<';

            if ($absensi === null) {
                if ($item['menit'] > 0) {
                    Absensi::create([
                        'karyawan_id' => $item['karyawan_id'], 'tanggal' => $item['tanggal'], 'kode' => $kode,
                        'menit_terlambat' => $item['menit'], 'keterangan' => $item['keterangan'],
                        'created_by' => $userId, 'updated_by' => $userId,
                    ]);
                    $dicatat++;
                }

                continue;
            }

            $otomatis = Absensi::isTerlambat($absensi->kode) && str_starts_with((string) $absensi->keterangan, self::PENANDA_DT);
            if (! $otomatis || $absensi->pending_approval_count > 0) {
                continue;
            }

            if ($item['menit'] > 0) {
                $absensi->update([
                    'kode' => $kode, 'menit_terlambat' => $item['menit'], 'keterangan' => $item['keterangan'], 'updated_by' => $userId,
                ]);
                $dicatat++;
            } elseif ($absensi->approvals_count === 0) {
                $absensi->delete();
            }
        }

        return $dicatat;
    }

    private function earliest(?int $current, ?int $menit): ?int
    {
        return $current === null ? $menit : ($menit === null ? $current : min($current, $menit));
    }

    private function jam(int $menit): string
    {
        return sprintf('%02d:%02d', intdiv($menit, 60), $menit % 60);
    }

    /**
     * @return list<list<mixed>>
     */
    private function readRows(string $path, string $extension): array
    {
        return match ($extension) {
            'xls' => $this->readXls($path),
            'xlsx' => $this->readXlsx($path),
            default => throw new RuntimeException('File harus berformat .xls atau .xlsx.'),
        };
    }

    /**
     * @return list<list<mixed>>
     */
    private function readXlsx(string $path): array
    {
        $reader = new Reader;

        try {
            $reader->open($path);
        } catch (Throwable) {
            throw new RuntimeException('File tidak bisa dibaca. Pastikan file berformat .xlsx.');
        }

        $rows = [];
        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $rows[] = $row->toArray();
                }
                break;
            }
        } finally {
            $reader->close();
        }

        return $rows;
    }

    /**
     * Baca sheet pertama dari stream BIFF (BIFF2 - BIFF8 tanpa kontainer OLE): hanya sel teks dan angka.
     *
     * @return list<list<mixed>>
     */
    private function readXls(string $path): array
    {
        $bytes = (string) file_get_contents($path);

        if (str_starts_with($bytes, "\xD0\xCF\x11\xE0")) {
            throw new RuntimeException('File .xls ini sudah disimpan ulang lewat Excel. Upload file asli dari mesin, atau simpan sebagai .xlsx.');
        }

        if (strlen($bytes) < 4 || ! in_array(unpack('v', $bytes)[1], [0x0009, 0x0209, 0x0409, 0x0809], true)) {
            throw new RuntimeException('File tidak bisa dibaca. Pastikan file adalah export .xls dari mesin absen.');
        }

        $cells = [];
        $biff8 = false;
        $length = strlen($bytes);
        $pos = 0;

        while ($pos + 4 <= $length) {
            ['type' => $type, 'size' => $size] = unpack('vtype/vsize', $bytes, $pos);
            $body = substr($bytes, $pos + 4, $size);
            $pos += 4 + $size;

            if ($type === 0x0809) {
                $biff8 = strlen($body) >= 2 && unpack('v', $body)[1] === 0x0600;

                continue;
            }

            if ($type === 0x000A) {
                break; // EOF sheet pertama
            }

            if (strlen($body) < 8) {
                continue;
            }

            $value = match ($type) {
                0x0004 => mb_convert_encoding(substr($body, 8, ord($body[7])), 'UTF-8', 'Windows-1252'), // LABEL (BIFF2)
                0x0204 => $this->biffLabel($body, $biff8),                 // LABEL (BIFF3 - BIFF8)
                0x0003 => strlen($body) >= 15 ? unpack('e', $body, 7)[1] : null, // NUMBER (BIFF2)
                0x0203 => strlen($body) >= 14 ? unpack('e', $body, 6)[1] : null, // NUMBER
                0x027E => strlen($body) >= 10 ? $this->rk(unpack('V', $body, 6)[1]) : null, // RK
                default => null,
            };

            if ($value === null) {
                continue;
            }

            ['row' => $row, 'col' => $col] = unpack('vrow/vcol', $body);
            $cells[$row][$col] = $value;
        }

        ksort($cells);

        return array_values(array_map(function (array $row) {
            $list = array_fill(0, max(array_keys($row)) + 1, null);

            return array_replace($list, $row);
        }, $cells));
    }

    private function biffLabel(string $body, bool $biff8): string
    {
        $chars = unpack('v', $body, 6)[1];

        if (! $biff8) {
            return mb_convert_encoding(substr($body, 8, $chars), 'UTF-8', 'Windows-1252');
        }

        $unicode = (ord($body[8] ?? "\0") & 0x01) === 1;

        return $unicode
            ? mb_convert_encoding(substr($body, 9, $chars * 2), 'UTF-8', 'UTF-16LE')
            : mb_convert_encoding(substr($body, 9, $chars), 'UTF-8', 'Windows-1252');
    }

    /**
     * Angka RK: 30 bit angka (integer atau 30 bit atas double), bit 0 = dibagi 100, bit 1 = integer.
     */
    private function rk(int $rk): int|float
    {
        if ($rk & 0x02) {
            $value = $rk >> 2;
            $value = $value & 0x20000000 ? $value - 0x40000000 : $value;
        } else {
            $value = unpack('e', pack('VV', 0, $rk & 0xFFFFFFFC))[1];
        }

        return $rk & 0x01 ? $value / 100 : $value;
    }

    /**
     * Cari baris header (berisi Employee Code dan Attendance Date) di 10 baris pertama.
     *
     * @param  list<list<mixed>>  $rows
     * @return array{0: int, 1: array<string, int>}
     */
    private function locateHeader(array $rows): array
    {
        foreach (array_slice($rows, 0, 10, true) as $index => $cells) {
            $labels = array_map(fn ($value) => preg_replace('/[^a-z]/', '', Str::lower((string) $this->text($value))), $cells);
            $columns = [];

            foreach (self::HEADERS as $key => $names) {
                foreach ($labels as $col => $label) {
                    if (in_array($label, $names, true)) {
                        $columns[$key] = $col;
                        break;
                    }
                }
            }

            if (isset($columns['nik'], $columns['tanggal'])) {
                return [$index, $columns + ['masuk' => -1]];
            }
        }

        throw new RuntimeException('Header tidak ditemukan. Pastikan ada kolom "Employee Code" dan "Attendance Date" seperti file Attendance Report.');
    }

    private function nik(mixed $value): ?string
    {
        if (is_int($value) || is_float($value)) {
            return str_pad((string) (int) $value, self::NIK_DIGITS, '0', STR_PAD_LEFT);
        }

        $text = $this->text($value);

        return $text !== null && ctype_digit($text) ? $text : null;
    }

    private function text(mixed $value): ?string
    {
        if ($value === null || $value instanceof DateTimeInterface) {
            return null;
        }

        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    /**
     * Menit sejak 00:00 dari Time In: teks "HH:MM", objek waktu, atau pecahan hari dari .xlsx. "-" atau kosong = null.
     */
    private function minuteOfDay(mixed $value): ?int
    {
        if ($value instanceof DateTimeInterface) {
            return (int) $value->format('G') * 60 + (int) $value->format('i');
        }

        if (is_int($value) || is_float($value)) {
            return (int) floor(fmod((float) $value, 1) * 1440 + 1e-6);
        }

        $text = $this->text($value);

        return $text !== null && preg_match('/^(\d{1,2})[:.](\d{2})/', $text, $match) ? (int) $match[1] * 60 + (int) $match[2] : null;
    }

    /**
     * Tanggal dari mesin berupa teks dd/mm/yyyy; dari .xlsx bisa berupa objek tanggal atau angka serial Excel.
     */
    private function date(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_int($value) || is_float($value)) {
            return Carbon::createFromTimestampUTC((int) round(($value - 25569) * 86400))->format('Y-m-d');
        }

        $text = $this->text($value);
        if ($text === null) {
            return null;
        }

        foreach (['d/m/Y', 'j/n/Y', 'd-m-Y', 'Y-m-d'] as $format) {
            try {
                $date = Carbon::createFromFormat('!'.$format, $text);
                if ($date && $date->format($format) === $text) {
                    return $date->format('Y-m-d');
                }
            } catch (Throwable) {
                // coba format berikutnya
            }
        }

        return null;
    }
}
