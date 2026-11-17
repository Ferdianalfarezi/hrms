<?php

namespace App\Modules\Hrms\Support;

use App\Modules\Hrms\Models\Karyawan;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Resume absensi harian per shift, dipisah per plant (departemen PSD, PWD, PHD, dan sisanya).
 * Data ketidakhadiran sama dengan laporan Detail (LaporanAbsensiShift), termasuk Alpa dari komparasi mesin.
 */
class ResumeAbsensi
{
    /** Departemen yang punya baris sendiri; departemen lain digabung ke baris "ALL MP". */
    public const PLANTS = ['PSD', 'PWD', 'PHD'];

    /**
     * Kotak tanda tangan di PDF resume, urut kiri ke kanan.
     *
     * @var list<array{jabatan: string, nama: string}>
     */
    public const PENANDATANGAN = [
        ['jabatan' => 'Direktur', 'nama' => 'Sigit WH'],
        ['jabatan' => 'GM HRD/GA/Safety', 'nama' => 'Adi Winata Kp'],
        ['jabatan' => 'SPV HRD', 'nama' => 'Isnur L'],
    ];

    /** Kolom kategori absen => kategori di Absensi::KATEGORI. WFH tidak dihitung absen di resume. */
    public const KATEGORI = [
        'cuti' => 'CT',
        'sakit' => 'S',
        'ijin' => 'I',
        'alpa' => 'Alpa',
    ];

    /**
     * @return array{tanggal: Carbon, sections: list<array{judul: string, tanggal: Carbon, grups: list<string>, rows: list<array<string, mixed>>, total: array<string, mixed>}>}
     */
    public static function build(Carbon $tanggal, bool $hitungCuti = true): array
    {
        $laporan = LaporanAbsensiShift::buildTanpaGrafik($tanggal, $hitungCuti);

        // Urutan seperti format Excel: Shift 1 (pagi hari ini) dulu, lalu Shift 2 (malam kemarin).
        $sections = collect($laporan['sections'])->sortBy(fn (array $section) => $section['judul'])->values();

        return [
            'tanggal' => $tanggal,
            'hitungCuti' => $hitungCuti,
            'sections' => $sections->map(fn (array $section) => self::section($section))->all(),
        ];
    }

    /**
     * @param  array{judul: string, tanggal: Carbon, grups: list<string>, rows: Collection<int, array<string, mixed>>}  $section
     */
    private static function section(array $section): array
    {
        $plantOf = fn (?string $department) => in_array($department, self::PLANTS, true) ? $department : 'ALL';

        $jumlah = Karyawan::query()
            ->whereIn('group', $section['grups'])
            ->with('department:id,name')
            ->get(['id', 'department_id'])
            ->countBy(fn (Karyawan $karyawan) => $plantOf($karyawan->department?->name));

        $absen = $section['rows']->groupBy(fn (array $row) => $plantOf($row['karyawan']->department?->name));

        $rows = collect([...self::PLANTS, 'ALL'])->map(function (string $plant, int $index) use ($jumlah, $absen) {
            $items = $absen->get($plant, collect());

            return self::hitung([
                'no' => $index + 1,
                'label' => $plant === 'ALL' ? 'ALL MP Ex '.implode(', ', self::PLANTS) : "MP {$plant}",
                'jumlah' => $jumlah->get($plant, 0),
                ...collect(self::KATEGORI)->map(fn (string $kategori) => $items->where('kategori', $kategori)->count())->all(),
                'skd' => $items->where('kode', 'SD')->count(),
                'ct' => $items->where('kode', 'CT')->count(),
            ]);
        });

        $total = self::hitung(['label' => 'TOTAL', ...collect(['jumlah', ...array_keys(self::KATEGORI), 'skd', 'ct'])
            ->mapWithKeys(fn (string $key) => [$key => $rows->sum($key)])->all()]);

        return [
            'judul' => $section['judul'],
            'tanggal' => $section['tanggal'],
            'grups' => $section['grups'],
            'rows' => $rows->all(),
            'total' => $total,
        ];
    }

    /**
     * Tambah total absen, persen (dengan & tanpa cuti), dan jumlah karyawan yang bekerja.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private static function hitung(array $row): array
    {
        $totalAbsen = array_sum(array_map(fn (string $key) => $row[$key], array_keys(self::KATEGORI)));

        return [
            ...$row,
            'total_absen' => $totalAbsen,
            'persen' => $row['jumlah'] > 0 && $totalAbsen > 0 ? $totalAbsen / $row['jumlah'] * 100 : null,
            'persen_tanpa_cuti' => $row['jumlah'] > 0 && $totalAbsen - $row['cuti'] > 0 ? ($totalAbsen - $row['cuti']) / $row['jumlah'] * 100 : null,
            'kerja' => $row['jumlah'] - $totalAbsen,
        ];
    }
}
