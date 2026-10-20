<?php

namespace App\Modules\Hrms\Imports;

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
 * Import export mesin fingerprint (format "data komparasi absen hrms.xls"): kolom AC-No, Name, Department, Date, Time.
 * Isinya hanya karyawan yang tidak absen di mesin. Upload ulang tanggal yang sama mengganti seluruh data tanggal itu.
 *
 * File .xls dari software mesin berupa stream BIFF tanpa kontainer OLE, jadi dibaca dengan parser kecil di bawah.
 * File yang sudah disimpan ulang lewat Excel sebagai .xlsx dibaca dengan OpenSpout.
 */
class AbsensiMesinImport
{
    /** Nama header yang dikenali (huruf kecil, tanpa spasi/tanda baca). */
    private const HEADERS = [
        'nik' => ['acno', 'nik'],
        'nama' => ['name', 'nama'],
        'tanggal' => ['date', 'tanggal'],
    ];

    /** Panjang minimal NIK; dipakai mengembalikan nol di depan kalau AC-No terbaca sebagai angka. */
    private const NIK_DIGITS = 4;

    /**
     * @return array{tanggal: list<string>, total: int, dilewati: int}
     */
    public function import(string $path, string $extension, ?int $userId): array
    {
        $rows = $this->readRows($path, Str::lower($extension));
        [$headerRow, $columns] = $this->locateHeader($rows);

        // [Y-m-d][nik] => nama
        $data = [];
        foreach (array_slice($rows, $headerRow + 1) as $cells) {
            $nik = $this->nik($cells[$columns['nik']] ?? null);
            if ($nik === null) {
                continue;
            }

            $tanggal = $this->date($cells[$columns['tanggal']] ?? null);
            if ($tanggal === null) {
                throw new RuntimeException("Tanggal untuk AC-No {$nik} kosong atau formatnya tidak dikenali.");
            }

            $nama = $this->text($cells[$columns['nama']] ?? null);
            // Mesin mengisi nama dengan AC-No kalau nama belum didaftarkan di mesin.
            $data[$tanggal][$nik] = $nama === $nik ? null : $nama;
        }

        if ($data === []) {
            throw new RuntimeException('Tidak ada data karyawan di file.');
        }

        ksort($data);
        $now = now();

        // AC-No yang tidak ada di data karyawan tidak disimpan (mis. karyawan sudah keluar / belum diinput).
        $niks = collect($data)->flatMap(fn (array $karyawans) => array_map('strval', array_keys($karyawans)))->unique()->values();
        $terdaftar = array_flip(Karyawan::whereIn('nik', $niks)->pluck('nik')->all());
        $dilewati = 0;
        foreach ($data as $tanggal => $karyawans) {
            $data[$tanggal] = array_intersect_key($karyawans, $terdaftar);
            $dilewati += count($karyawans) - count($data[$tanggal]);
        }

        DB::transaction(function () use ($data, $userId, $now) {
            foreach ($data as $tanggal => $karyawans) {
                AbsensiMesin::whereDate('tanggal', $tanggal)->delete();

                foreach (array_chunk($karyawans, 500, true) as $chunk) {
                    AbsensiMesin::insert(array_map(fn ($nik, $nama) => [
                        'tanggal' => $tanggal, 'nik' => (string) $nik, 'nama' => $nama, 'uploaded_by' => $userId,
                        'created_at' => $now, 'updated_at' => $now,
                    ], array_keys($chunk), $chunk));
                }
            }
        });

        return ['tanggal' => array_keys($data), 'total' => array_sum(array_map('count', $data)), 'dilewati' => $dilewati];
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
     * Cari baris header (berisi AC-No dan Date) di 10 baris pertama.
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
                return [$index, $columns + ['nama' => -1]];
            }
        }

        throw new RuntimeException('Header tidak ditemukan. Pastikan ada kolom "AC-No" dan "Date" seperti export mesin absen.');
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
