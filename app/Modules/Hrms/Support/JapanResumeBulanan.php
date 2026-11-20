<?php

namespace App\Modules\Hrms\Support;

use App\Modules\Hrms\Models\Absensi;
use App\Modules\Hrms\Models\JapanResume;
use App\Modules\Hrms\Models\Karyawan;
use App\Modules\Hrms\Models\ShiftRotasi;
use Illuminate\Support\Carbon;

/**
 * Japan Resume satu bulan, seperti sheet bulanan di file HRD "2026 summary.xlsx":
 * per hari kerja Total MP, Hadir, Cuti, S+I, dan rate (persentase tidak hadir).
 *
 * Sumber angka per tanggal, urut prioritas:
 *  1. Angka yang disesuaikan admin (baris "manual").
 *  2. Hitungan dari menu Absensi Harian, kalau tanggal itu sudah ada data absensinya:
 *     - Total = semua karyawan (sama seperti "dari :count karyawan" di Harian).
 *     - Cuti  = kategori CT (CT, CK, CH, CB, IMP).
 *     - S+I   = kode tidak hadir lainnya, jadi Cuti + S+I = baris "Total tidak hadir" di Harian.
 *  3. Data dari file Excel HRD (hasil seeder), untuk tanggal yang belum ada data absensinya di sistem.
 * Hari tanpa data absensi sama sekali dianggap libur, hari yang sebagian besar karyawannya CB dianggap cuti bersama.
 */
class JapanResumeBulanan
{
    /** Baris yang dihitung dari absensi Harian (tidak tersimpan). */
    public const SUMBER_OTOMATIS = 'otomatis';

    /** Hari dianggap cuti bersama (tidak tampil) kalau minimal sebagian ini dari total MP berkode CB. */
    public const BATAS_CUTI_BERSAMA = 0.5;

    /**
     * @return array{bulan: Carbon, rows: list<array<string, mixed>>, total: array{total: int, hadir: int, cuti: int, sakit_izin: int, rate: ?float, persen_cuti: ?float, persen_sakit_izin: ?float}}
     */
    public static function build(Carbon $bulan, Carbon $hariIni): array
    {
        $start = $bulan->copy()->startOfMonth();
        $end = $bulan->copy()->endOfMonth();

        $tersimpan = JapanResume::with('editor:id,name')
            ->whereDate('tanggal', '>=', $start->toDateString())
            ->whereDate('tanggal', '<=', $end->toDateString())
            ->get()
            ->mapWithKeys(fn (JapanResume $resume) => [$resume->tanggal->toDateString() => self::row(
                $resume->tanggal, $resume->shift, $resume->total, $resume->cuti, $resume->sakit_izin, $resume->sumber, $resume->editor?->name, $resume->id,
            )]);

        // Data Excel dulu, ditimpa hitungan Harian untuk tanggal yang ada data absensinya, lalu ditimpa angka manual.
        $rows = $tersimpan->where('sumber', JapanResume::SUMBER_EXCEL)->all();
        $sampai = min($end, $hariIni->copy()->startOfDay());
        if ($start->lte($sampai)) {
            foreach (self::hitungDariHarian($start, $sampai) as $tanggal => $row) {
                $rows[$tanggal] = $row;
            }
        }
        $rows = [...$rows, ...$tersimpan->where('sumber', JapanResume::SUMBER_MANUAL)->all()];

        ksort($rows);
        $rows = array_values($rows);

        return ['bulan' => $start, 'rows' => $rows, 'total' => self::total($rows)];
    }

    /**
     * Angka per tanggal dari data menu Absensi Harian.
     *
     * @return array<string, array<string, mixed>> [Y-m-d] => baris
     */
    private static function hitungDariHarian(Carbon $mulai, Carbon $sampai): array
    {
        $range = fn ($query) => $query->whereDate('tanggal', '>=', $mulai->toDateString())->whereDate('tanggal', '<=', $sampai->toDateString());

        // [Y-m-d][kolom] => jumlah karyawan, kolom: cuti / sakit_izin / cb.
        $absen = [];
        Absensi::query()->tap($range)
            ->whereIn('kode', Absensi::kodeTidakHadir())
            ->get(['tanggal', 'kode'])
            ->each(function (Absensi $absensi) use (&$absen) {
                $tanggal = $absensi->tanggal->toDateString();
                $kolom = Absensi::kategori($absensi->kode) === 'CT' ? 'cuti' : 'sakit_izin';
                $absen[$tanggal][$kolom] = ($absen[$tanggal][$kolom] ?? 0) + 1;
                if ($absensi->kode === 'CB') {
                    $absen[$tanggal]['cb'] = ($absen[$tanggal]['cb'] ?? 0) + 1;
                }
            });

        // Hari kerja = tanggal yang sudah ada data absensinya (kode apa pun, termasuk DT / PC).
        $adaData = Absensi::query()->tap($range)->distinct()->pluck('tanggal')
            ->mapWithKeys(fn ($tanggal) => [Carbon::parse($tanggal)->toDateString() => true]);

        $total = Karyawan::count();
        $rotasis = ShiftRotasi::orderBy('tanggal_mulai')->get();
        $rows = [];

        for ($day = $mulai->copy(); $day->lte($sampai); $day->addDay()) {
            $tanggal = $day->toDateString();
            $cutiBersama = $absen[$tanggal]['cb'] ?? 0;
            if ($total === 0 || ! isset($adaData[$tanggal]) || $cutiBersama >= $total * self::BATAS_CUTI_BERSAMA) {
                continue;
            }

            // Ada jadwal rotasi grup A/B = shift pagi & malam masuk (両直), selain itu hanya satu shift (一直).
            $shift = ShiftRotasi::grupPagi($day, $rotasis) ? JapanResume::SHIFT_DUA : JapanResume::SHIFT_SATU;
            $rows[$tanggal] = self::row($day->copy(), $shift, $total, $absen[$tanggal]['cuti'] ?? 0, $absen[$tanggal]['sakit_izin'] ?? 0, self::SUMBER_OTOMATIS);
        }

        return $rows;
    }

    /**
     * @param  string  $sumber  excel / manual (tersimpan) atau otomatis (dihitung dari absensi Harian)
     * @return array{id: ?int, tanggal: Carbon, shift: string, total: int, hadir: int, cuti: int, sakit_izin: int, rate: ?float, sumber: string, otomatis: bool, editor: ?string}
     */
    private static function row(Carbon $tanggal, string $shift, int $total, int $cuti, int $sakitIzin, string $sumber, ?string $editor = null, ?int $id = null): array
    {
        $hadir = $total - $cuti - $sakitIzin;

        return [
            'id' => $id,
            'tanggal' => $tanggal,
            'shift' => $shift,
            'total' => $total,
            'hadir' => $hadir,
            'cuti' => $cuti,
            'sakit_izin' => $sakitIzin,
            'rate' => self::persen($total - $hadir, $total),
            'sumber' => $sumber,
            'otomatis' => $sumber === self::SUMBER_OTOMATIS,
            // Nama admin yang terakhir menyesuaikan angka baris ini (null = belum pernah diedit).
            'editor' => $editor,
        ];
    }

    /**
     * Baris "Total" bulan ini + persentase Cuti dan S+I terhadap total MP (baris paling atas di Excel).
     *
     * @param  list<array{total: int, hadir: int, cuti: int, sakit_izin: int}>  $rows
     * @return array{total: int, hadir: int, cuti: int, sakit_izin: int, rate: ?float, persen_cuti: ?float, persen_sakit_izin: ?float}
     */
    private static function total(array $rows): array
    {
        $sum = fn (string $kolom) => array_sum(array_column($rows, $kolom));
        $total = $sum('total');

        return [
            'total' => $total,
            'hadir' => $sum('hadir'),
            'cuti' => $sum('cuti'),
            'sakit_izin' => $sum('sakit_izin'),
            'rate' => self::persen($total - $sum('hadir'), $total),
            'persen_cuti' => self::persen($sum('cuti'), $total),
            'persen_sakit_izin' => self::persen($sum('sakit_izin'), $total),
        ];
    }

    private static function persen(int $jumlah, int $total): ?float
    {
        return $total > 0 ? round($jumlah / $total * 100, 1) : null;
    }
}
