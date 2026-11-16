<?php

namespace App\Modules\Hrms\Imports;

use App\Modules\Hrms\Models\Absensi;
use App\Modules\Hrms\Models\Karyawan;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use OpenSpout\Reader\XLSX\Reader;
use RuntimeException;
use Throwable;

/**
 * Samakan absensi satu bulan dengan sheet bulanan di file rekap Excel HRD (mis. sheet "Nop'26" di "Absensi 2026.xlsm").
 *
 * Format sheet: baris header berisi "NIK" dan "DATE", baris di bawahnya nomor tanggal 1 - 31, lalu satu baris per karyawan
 * dengan kode absen per tanggal. Bulan dibaca dari nama sheet (mis. "Nop'26" = November 2026), karena sel "Tanggal :"
 * di sheet berisi rumus TODAY().
 *
 * Sel berkode ditambahkan atau kodenya diganti; keterangan yang sudah ada dibiarkan. Menit terlambat tidak ada di sheet,
 * jadi menit lama dipertahankan kalau kode sebelumnya juga DT, selain itu kosong. Absensi bulan itu milik karyawan yang
 * ada di sheet tapi selnya kosong hanya dihapus kalau $hapus = true. Karyawan yang tidak ada di sheet tidak disentuh.
 * NIK yang belum terdaftar dan kode yang tidak dikenal dilewati (data lama di sel itu juga tidak dihapus).
 */
class AbsensiBulananImport
{
    /** Singkatan bulan di nama sheet (huruf kecil) => nomor bulan. */
    private const BULAN = [
        'jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4, 'mei' => 5, 'may' => 5, 'jun' => 6, 'jul' => 7,
        'agu' => 8, 'ags' => 8, 'aug' => 8, 'sep' => 9, 'sept' => 9, 'okt' => 10, 'oct' => 10,
        'nop' => 11, 'nov' => 11, 'des' => 12, 'dec' => 12,
    ];

    /** Panjang minimal NIK; mengembalikan nol di depan kalau NIK terbaca sebagai angka. */
    private const NIK_DIGITS = 4;

    public function __construct(private ?int $userId = null) {}

    /**
     * @param  bool  $simpan  false = hanya hitung perubahan (dry-run), database tidak diubah.
     * @param  bool  $hapus  true = hapus absensi karyawan di sheet yang selnya kosong, supaya sama persis dengan sheet.
     * @return array{bulan: Carbon, sel: int, ditambah: int, diubah: int, sama: int, hanya_di_database: list<string>, dihapus: int, nik_tidak_terdaftar: list<string>, kode_tidak_dikenal: list<string>, contoh_diubah: list<string>}
     */
    public function import(string $path, string $sheetName, bool $simpan = true, ?Carbon $bulan = null, bool $hapus = false): array
    {
        $bulan = ($bulan ?? self::bulanDariNamaSheet($sheetName))->copy()->startOfMonth();
        $rows = $this->readSheet($path, $sheetName);
        [$headerRow, $nikColumn, $dayColumns] = $this->locateHeader($rows);

        // [nik][Y-m-d] => kode. $nikDiSheet juga memuat karyawan yang barisnya kosong sebulan penuh.
        $sel = [];
        $nikDiSheet = [];
        $abaikan = [];
        $kodeTidakDikenal = [];
        foreach (array_slice($rows, $headerRow + 2) as $cells) {
            $nik = $this->nik($cells[$nikColumn] ?? null);
            if ($nik === null) {
                continue;
            }

            $nikDiSheet[$nik] = true;

            foreach ($dayColumns as $column => $day) {
                $kode = mb_strtoupper(trim((string) ($cells[$column] ?? '')));
                if ($kode === '' || $day > $bulan->daysInMonth) {
                    continue;
                }

                $tanggal = $bulan->copy()->day($day)->toDateString();
                if (! isset(Absensi::KODE[$kode])) {
                    $kodeTidakDikenal[] = "{$nik} {$tanggal} \"{$kode}\"";
                    $abaikan["{$nik}|{$tanggal}"] = true;

                    continue;
                }

                $sel[$nik][$tanggal] = $kode;
            }
        }

        // Kunci array PHP seperti "3556" otomatis jadi integer, jadi NIK dikembalikan ke string sebelum dicari.
        $niks = array_map('strval', array_keys($sel));
        $karyawanIds = Karyawan::whereIn('nik', array_map('strval', array_keys($nikDiSheet)))->pluck('id', 'nik');
        $nikOf = $karyawanIds->flip();
        $nikTidakTerdaftar = array_values(array_diff($niks, array_map('strval', $karyawanIds->keys()->all())));

        $existing = Absensi::query()
            ->whereIn('karyawan_id', $karyawanIds->values())
            ->whereBetween('tanggal', [$bulan->toDateString(), $bulan->copy()->endOfMonth()->toDateString()])
            ->get()
            ->keyBy(fn (Absensi $absensi) => $absensi->karyawan_id.'|'.$absensi->tanggal->toDateString());

        $hasil = ['ditambah' => 0, 'diubah' => 0, 'sama' => 0];
        $contohDiubah = [];
        $dipakai = [];

        $proses = function () use ($sel, $karyawanIds, $existing, $simpan, &$hasil, &$contohDiubah, &$dipakai) {
            foreach ($sel as $nik => $tanggals) {
                $karyawanId = $karyawanIds[(string) $nik] ?? null;
                if ($karyawanId === null) {
                    continue;
                }

                foreach ($tanggals as $tanggal => $kode) {
                    $key = "{$karyawanId}|{$tanggal}";
                    $dipakai[$key] = true;
                    $absensi = $existing[$key] ?? null;

                    if ($absensi?->kode === $kode) {
                        $hasil['sama']++;

                        continue;
                    }

                    if ($absensi) {
                        $hasil['diubah']++;
                        $contohDiubah[] = "{$nik} {$tanggal} {$absensi->kode} -> {$kode}";
                    } else {
                        $hasil['ditambah']++;
                    }

                    if (! $simpan) {
                        continue;
                    }

                    $absensi ??= new Absensi(['karyawan_id' => $karyawanId, 'tanggal' => $tanggal, 'created_by' => $this->userId]);
                    $absensi->fill([
                        'kode' => $kode,
                        'menit_terlambat' => Absensi::isTerlambat($kode) && Absensi::isTerlambat($absensi->getOriginal('kode'))
                            ? $absensi->menit_terlambat
                            : null,
                        'updated_by' => $this->userId,
                    ])->save();
                }
            }
        };

        // Dihitung setelah $proses mengisi $dipakai: absensi yang tidak punya pasangan sel berkode di sheet.
        $sisa = function () use ($existing, &$dipakai, $abaikan, $nikOf) {
            return $existing->reject(fn (Absensi $absensi, string $key) => isset($dipakai[$key])
                || isset($abaikan[$nikOf[$absensi->karyawan_id].'|'.$absensi->tanggal->toDateString()]));
        };

        $hanyaDiDatabase = $simpan
            ? DB::transaction(function () use ($proses, $sisa, $hapus) {
                $proses();
                $hanyaDiDatabase = $sisa();

                if ($hapus && $hanyaDiDatabase->isNotEmpty()) {
                    Absensi::whereKey($hanyaDiDatabase->modelKeys())->delete();
                }

                return $hanyaDiDatabase;
            })
            : ($proses() ?? $sisa());

        return [
            'bulan' => $bulan,
            'sel' => array_sum(array_map('count', $sel)),
            ...$hasil,
            'hanya_di_database' => $hanyaDiDatabase
                ->map(fn (Absensi $absensi) => $nikOf[$absensi->karyawan_id].' '.$absensi->tanggal->toDateString().' '.$absensi->kode)
                ->values()->all(),
            'dihapus' => $simpan && $hapus ? $hanyaDiDatabase->count() : 0,
            'nik_tidak_terdaftar' => $nikTidakTerdaftar,
            'kode_tidak_dikenal' => $kodeTidakDikenal,
            'contoh_diubah' => $contohDiubah,
        ];
    }

    /**
     * @return list<list<mixed>>
     */
    private function readSheet(string $path, string $sheetName): array
    {
        if (! is_file($path)) {
            throw new RuntimeException("File {$path} tidak ditemukan. Periksa lagi path-nya (pakai tanda kutip kalau ada spasi).");
        }

        $reader = new Reader;

        try {
            $reader->open($path);
        } catch (Throwable) {
            throw new RuntimeException("File {$path} tidak bisa dibaca. Pastikan file berformat .xlsx / .xlsm.");
        }

        try {
            $names = [];
            foreach ($reader->getSheetIterator() as $sheet) {
                if (trim($sheet->getName()) !== trim($sheetName)) {
                    $names[] = $sheet->getName();

                    continue;
                }

                $rows = [];
                foreach ($sheet->getRowIterator() as $row) {
                    $rows[] = $row->toArray();
                }

                return $rows;
            }
        } finally {
            $reader->close();
        }

        throw new RuntimeException("Sheet \"{$sheetName}\" tidak ditemukan. Sheet yang ada: ".implode(', ', $names).'.');
    }

    /**
     * Baris header (berisi "NIK" dan "DATE"), kolom NIK, dan kolom tanggal => nomor tanggal dari baris di bawahnya.
     *
     * @param  list<list<mixed>>  $rows
     * @return array{0: int, 1: int, 2: array<int, int>}
     */
    private function locateHeader(array $rows): array
    {
        foreach ($rows as $index => $cells) {
            $labels = array_map(fn ($value) => is_string($value) ? mb_strtoupper(trim($value)) : null, $cells);
            $nikColumn = array_search('NIK', $labels, true);
            $dateColumn = array_search('DATE', $labels, true);

            if ($nikColumn === false || $dateColumn === false) {
                continue;
            }

            $dayColumns = [];
            foreach ($rows[$index + 1] ?? [] as $column => $value) {
                if ($column >= $dateColumn && is_numeric($value) && (int) $value >= 1 && (int) $value <= 31) {
                    $dayColumns[$column] = (int) $value;
                }
            }

            if ($dayColumns !== []) {
                return [$index, $nikColumn, $dayColumns];
            }
        }

        throw new RuntimeException('Header sheet tidak dikenali: butuh kolom "NIK" dan "DATE" dengan nomor tanggal 1 - 31 di baris bawahnya.');
    }

    /**
     * Bulan dari nama sheet berformat "<singkatan bulan>'<2 digit tahun>", mis. "Nop'26" atau "Sept'26".
     */
    public static function bulanDariNamaSheet(string $sheetName): Carbon
    {
        if (preg_match("/^\s*([a-z]+)\W*(\d{2}|\d{4})\s*$/i", $sheetName, $match) && isset(self::BULAN[strtolower($match[1])])) {
            $tahun = (int) $match[2];

            return Carbon::create($tahun < 100 ? 2000 + $tahun : $tahun, self::BULAN[strtolower($match[1])], 1);
        }

        throw new RuntimeException("Bulan tidak terbaca dari nama sheet \"{$sheetName}\". Isi opsi --bulan=YYYY-MM.");
    }

    private function nik(mixed $value): ?string
    {
        if (is_int($value) || is_float($value)) {
            return str_pad((string) (int) $value, self::NIK_DIGITS, '0', STR_PAD_LEFT);
        }

        $text = is_string($value) ? trim($value) : '';

        return $text !== '' && ctype_digit($text) ? $text : null;
    }
}
