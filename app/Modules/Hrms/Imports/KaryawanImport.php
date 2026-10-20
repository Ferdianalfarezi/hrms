<?php

namespace App\Modules\Hrms\Imports;

use App\Modules\Hrms\Models\Department;
use App\Modules\Hrms\Models\Karyawan;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use OpenSpout\Reader\XLSX\Reader;
use RuntimeException;
use Throwable;

/**
 * Import data karyawan dari Excel dengan format seperti "data upload hrms.xlsx".
 *
 * - Header boleh 1 atau 2 baris (sel di-merge); kolom dicari berdasarkan nama header, bukan posisi.
 * - Kolom yang tidak dikenal (mis. TANGGAL/BULAN/TAHUN) diabaikan; tanggal lahir diambil dari TGL LAHIR.
 * - DEPT dicocokkan ke tabel departments (tanpa beda huruf besar/kecil). Dept yang belum terdaftar
 *   tidak menggagalkan baris: karyawan tetap diimport tanpa departemen dan dilaporkan sebagai peringatan.
 * - NIK yang sudah ada akan diperbarui, NIK baru ditambahkan.
 * - Baris yang tidak valid dilewati dan dilaporkan, baris lain tetap diimport.
 */
class KaryawanImport
{
    /** Header Excel => kolom tabel. */
    public const COLUMNS = [
        'NIK' => 'nik',
        'NAMA' => 'nama',
        'JENIS KELAMIN' => 'jenis_kelamin',
        'TEMPAT LAHIR' => 'tempat_lahir',
        'TGL LAHIR' => 'tgl_lahir',
        'JABATAN' => 'jabatan',
        'TANGGAL MASUK' => 'tanggal_masuk',
        'STATUS' => 'status',
        'DEPT' => 'department',
        'GROUP' => 'group',
    ];

    /** Kolom yang boleh tidak ada di file; kalau tidak ada, data lama tidak diubah. */
    public const OPTIONAL_COLUMNS = ['DEPT', 'GROUP'];

    private const NIK_PAD_LENGTH = 4;

    /** @var array<string, int> nama departemen huruf kecil => id */
    private array $departmentIds = [];

    /** @var array<string, string> jabatan huruf kecil => ejaan yang dipakai */
    private array $jabatanSpelling = [];

    /**
     * @return array{created: int, updated: int, unchanged: int, failed: list<array{row: int, nik: ?string, nama: ?string, errors: list<string>}>, warnings: list<array{row: int, nik: ?string, message: string}>, unknown_departments: array<string, int>}
     */
    public function import(string $path): array
    {
        $rows = $this->readRows($path);
        [$headerIndex, $columnMap] = $this->locateHeader($rows);

        $this->jabatanSpelling = Karyawan::query()->whereNotNull('jabatan')->distinct()->pluck('jabatan')
            ->mapWithKeys(fn ($jabatan) => [Str::lower($jabatan) => $jabatan])->all();
        $this->departmentIds = Department::pluck('id', 'name')
            ->mapWithKeys(fn ($id, $name) => [Str::lower($name) => $id])->all();

        $result = ['created' => 0, 'updated' => 0, 'unchanged' => 0, 'failed' => [], 'warnings' => [], 'unknown_departments' => []];
        $valid = [];
        $seenNik = [];

        foreach (array_slice($rows, $headerIndex['dataStart'], preserve_keys: true) as $rowNumber => $cells) {
            if ($this->isBlankRow($cells)) {
                continue;
            }

            $raw = [];
            foreach ($columnMap as $attribute => $index) {
                $raw[$attribute] = $cells[$index] ?? null;
            }

            [$data, $warnings] = $this->normalize($raw);
            foreach ($warnings as $warning) {
                $result['warnings'][] = ['row' => $rowNumber, 'nik' => $data['nik'], 'message' => $warning];
            }

            $errors = $this->validate($data);
            if ($data['nik'] !== null && isset($seenNik[$data['nik']])) {
                $errors[] = "NIK {$data['nik']} sudah muncul di baris {$seenNik[$data['nik']]}.";
            }

            if ($errors) {
                $result['failed'][] = ['row' => $rowNumber, 'nik' => $data['nik'], 'nama' => $data['nama'], 'errors' => $errors];

                continue;
            }

            if (array_key_exists('department', $data)) {
                // Dept yang belum terdaftar: karyawan tetap diimport tanpa departemen.
                $department = $data['department'];
                $data['department_id'] = $department === null ? null : ($this->departmentIds[Str::lower($department)] ?? null);

                if ($department !== null && $data['department_id'] === null) {
                    $result['unknown_departments'][$department] = ($result['unknown_departments'][$department] ?? 0) + 1;
                    $result['warnings'][] = ['row' => $rowNumber, 'nik' => $data['nik'],
                        'message' => "Departemen \"{$department}\" belum terdaftar, karyawan diimport tanpa departemen."];
                }

                unset($data['department']);
            }

            $seenNik[$data['nik']] = $rowNumber;
            $valid[] = $data;
        }

        DB::transaction(function () use ($valid, &$result) {
            foreach ($valid as $data) {
                $karyawan = Karyawan::firstOrNew(['nik' => $data['nik']]);
                $karyawan->fill($data);

                if (! $karyawan->exists) {
                    $karyawan->save();
                    $result['created']++;
                } elseif ($karyawan->isDirty()) {
                    $karyawan->save();
                    $result['updated']++;
                } else {
                    $result['unchanged']++;
                }
            }
        });

        return $result;
    }

    /**
     * Baca sheet pertama. Kunci array = nomor baris Excel (mulai 1).
     *
     * @return array<int, list<mixed>>
     */
    private function readRows(string $path): array
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
                $number = 0;
                foreach ($sheet->getRowIterator() as $row) {
                    $rows[++$number] = $row->toArray();
                }
                break;
            }
        } finally {
            $reader->close();
        }

        return $rows;
    }

    /**
     * Cari baris header (yang berisi "NIK"), gabungkan dengan sub-header di bawahnya bila ada.
     *
     * @param  array<int, list<mixed>>  $rows
     * @return array{0: array{dataStart: int}, 1: array<string, int>}
     */
    private function locateHeader(array $rows): array
    {
        foreach (array_slice($rows, 0, 10, true) as $number => $cells) {
            $labels = array_map(fn ($value) => $this->headerLabel($value), $cells);
            $nikIndex = array_search('NIK', $labels, true);

            if ($nikIndex === false) {
                continue;
            }

            // Baris berikutnya dianggap sub-header kalau kolom NIK-nya kosong tapi ada teks lain.
            $next = $rows[$number + 1] ?? [];
            $hasSubHeader = $this->isBlank($next[$nikIndex] ?? null) && ! $this->isBlankRow($next);
            if ($hasSubHeader) {
                foreach ($next as $index => $value) {
                    if (($labels[$index] ?? '') === '') {
                        $labels[$index] = $this->headerLabel($value);
                    }
                }
            }

            $columnMap = [];
            foreach (self::COLUMNS as $label => $attribute) {
                $index = array_search($label, $labels, true);
                if ($index === false && in_array($label, self::OPTIONAL_COLUMNS, true)) {
                    continue;
                }
                if ($index === false) {
                    $required = array_diff(array_keys(self::COLUMNS), self::OPTIONAL_COLUMNS);
                    throw new RuntimeException("Kolom \"{$label}\" tidak ditemukan di file. Kolom wajib: ".implode(', ', $required).'.');
                }
                $columnMap[$attribute] = $index;
            }

            // array_slice memakai offset 0-based, sedangkan kunci baris dimulai dari 1.
            return [['dataStart' => $number + ($hasSubHeader ? 1 : 0)], $columnMap];
        }

        throw new RuntimeException('Header tidak ditemukan. Pastikan ada kolom "NIK" di 10 baris pertama.');
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array{0: array<string, mixed>, 1: list<string>}
     */
    private function normalize(array $raw): array
    {
        $warnings = [];

        $nik = $raw['nik'];
        if (is_int($nik) || is_float($nik)) {
            // Excel menyimpan NIK sebagai angka sehingga nol di depan hilang (mis. 774 => 0774).
            $padded = str_pad((string) (int) $nik, self::NIK_PAD_LENGTH, '0', STR_PAD_LEFT);
            $warnings[] = "NIK tersimpan sebagai angka di Excel ({$nik}), diimport sebagai {$padded}. Mohon dicek.";
            $nik = $padded;
        }

        $data = [
            'nik' => $this->text($nik),
            'nama' => $this->text($raw['nama']),
            'jenis_kelamin' => $this->jenisKelamin($raw['jenis_kelamin']),
            'tempat_lahir' => $this->text($raw['tempat_lahir']),
            'tgl_lahir' => $this->date($raw['tgl_lahir']),
            'jabatan' => $this->jabatan($raw['jabatan']),
            'tanggal_masuk' => $this->date($raw['tanggal_masuk']),
            'status' => $this->status($raw['status']),
        ];

        // Hanya diisi kalau kolom DEPT ada di file, supaya import tanpa kolom DEPT tidak mengosongkan departemen.
        if (array_key_exists('department', $raw)) {
            $data['department'] = $this->text($raw['department']);
        }

        // Grup disimpan huruf besar supaya "ns" dan "NS" dianggap sama.
        if (array_key_exists('group', $raw)) {
            $group = $this->text($raw['group']);
            $data['group'] = $group === null ? null : Str::upper($group);
        }

        return [$data, $warnings];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    private function validate(array $data): array
    {
        $validator = Validator::make($data, [
            'nik' => ['required', 'regex:/^\d+$/', 'max:20'],
            'nama' => ['required', 'max:255'],
            'jenis_kelamin' => ['required', Rule::in(Karyawan::JENIS_KELAMIN)],
            'tempat_lahir' => ['nullable', 'max:255'],
            'tgl_lahir' => ['nullable', 'date'],
            'jabatan' => ['nullable', 'max:255'],
            'tanggal_masuk' => ['nullable', 'date'],
            'status' => ['required', Rule::in(Karyawan::STATUS)],
            'group' => ['nullable', 'max:20'],
        ], [
            'nik.required' => 'NIK kosong.',
            'nik.regex' => 'NIK hanya boleh berisi angka.',
            'nama.required' => 'Nama kosong.',
            'jenis_kelamin.required' => 'Jenis kelamin kosong.',
            'jenis_kelamin.in' => 'Jenis kelamin harus Laki-laki atau Perempuan.',
            'tgl_lahir.date' => 'Format TGL LAHIR tidak dikenali.',
            'tanggal_masuk.date' => 'Format TANGGAL MASUK tidak dikenali.',
            'status.required' => 'Status kosong.',
            'status.in' => 'Status harus salah satu dari: '.implode(', ', Karyawan::STATUS).'.',
            'max' => 'Isian :attribute terlalu panjang (maks. :max karakter).',
        ]);

        return $validator->errors()->all();
    }

    private function headerLabel(mixed $value): string
    {
        return Str::upper(Str::squish((string) $value));
    }

    private function text(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        $text = Str::squish((string) $value);

        return $text === '' ? null : $text;
    }

    private function jenisKelamin(mixed $value): ?string
    {
        $text = $this->text($value);

        return match (Str::lower(str_replace(' ', '-', (string) $text))) {
            'laki-laki', 'l', 'pria', 'laki' => 'Laki-laki',
            'perempuan', 'p', 'wanita' => 'Perempuan',
            default => $text,
        };
    }

    private function status(mixed $value): ?string
    {
        $text = $this->text($value);
        $key = Str::lower(str_replace(' ', '', (string) $text));

        foreach (Karyawan::STATUS as $status) {
            if (Str::lower(str_replace(' ', '', $status)) === $key) {
                return $status;
            }
        }

        return $text;
    }

    /**
     * Samakan ejaan jabatan yang hanya beda huruf besar/kecil (mis. DIREKTUR => Direktur).
     */
    private function jabatan(mixed $value): ?string
    {
        $text = $this->text($value);
        if ($text === null) {
            return null;
        }

        return $this->jabatanSpelling[Str::lower($text)] ??= $text;
    }

    /**
     * Tanggal dari Excel bisa berupa objek tanggal, angka serial Excel, atau teks.
     */
    private function date(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_int($value) || is_float($value)) {
            // Angka serial Excel: 1 = 1900-01-01 (dengan bug tahun kabisat 1900 → offset 25569 ke epoch Unix).
            return Carbon::createFromTimestampUTC((int) round(($value - 25569) * 86400))->format('Y-m-d');
        }

        $text = $this->text($value);
        if ($text === null) {
            return null;
        }

        foreach (['d/m/Y', 'd-m-Y', 'Y-m-d', 'd-M-Y', 'd M Y', 'j/n/Y'] as $format) {
            try {
                $date = Carbon::createFromFormat('!'.$format, $text);
                if ($date && $date->format($format) === $text) {
                    return $date->format('Y-m-d');
                }
            } catch (Throwable) {
                // coba format berikutnya
            }
        }

        // Biarkan apa adanya supaya validasi melaporkan formatnya salah.
        return $text;
    }

    /**
     * @param  list<mixed>  $cells
     */
    private function isBlankRow(array $cells): bool
    {
        foreach ($cells as $value) {
            if (! $this->isBlank($value)) {
                return false;
            }
        }

        return true;
    }

    private function isBlank(mixed $value): bool
    {
        return $value === null || (is_string($value) && trim($value) === '');
    }
}
