<?php

namespace App\Modules\Hrms\Support;

use App\Modules\Hrms\Models\Absensi;
use App\Modules\Hrms\Models\AbsensiMesin;
use App\Modules\Hrms\Models\JapanResume;
use App\Modules\Hrms\Models\Karyawan;
use App\Modules\Hrms\Models\ShiftRotasi;
use Illuminate\Support\Carbon;

/**
 * Japan Resume satu bulan, seperti sheet bulanan di file HRD "2026 summary.xlsx":
 * per hari kerja Total MP, Hadir, Cuti, S+I, dan rate (persentase tidak hadir).
 *
 * Sampai tanggal data tersimpan terakhir (hasil seeder dari Excel) dipakai data tersimpan apa adanya.
 * Setelah itu dihitung otomatis dari absensi, sama seperti laporan tanggal X di menu Resume:
 *  - Total = MP Shift 1 (grup pagi + NS tanggal X) + MP Shift 2 (grup malam tanggal X-1).
 *  - Cuti  = kategori CT (CT, CK, CH, CB, IMP).
 *  - S+I   = Sakit + Izin + Alpa (termasuk tidak absen di mesin tanpa keterangan). WFH dihitung hadir.
 * Hari otomatis hanya hari yang ada data absensi / mesin absen (hari libur tidak tampil), dan bukan cuti bersama.
 */
class JapanResumeBulanan
{
    /** Baris yang dihitung dari absensi (tidak tersimpan). */
    public const SUMBER_OTOMATIS = 'otomatis';

    /** Hari dianggap cuti bersama (tidak tampil) kalau minimal sebagian ini dari total MP berkode CB. */
    public const BATAS_CUTI_BERSAMA = 0.5;

    /**
     * @return array{bulan: Carbon, rows: list<array<string, mixed>>, total: array{total: int, hadir: int, cuti: int, sakit_izin: int, rate: ?float, persen_cuti: ?float, persen_sakit_izin: ?float}, dataSampai: ?Carbon}
     */
    public static function build(Carbon $bulan, Carbon $hariIni): array
    {
        $start = $bulan->copy()->startOfMonth();
        $end = $bulan->copy()->endOfMonth();
        $dataSampai = JapanResume::where('sumber', JapanResume::SUMBER_EXCEL)->max('tanggal');
        $dataSampai = $dataSampai ? Carbon::parse($dataSampai)->startOfDay() : null;

        // Baris tersimpan (data Excel + angka yang disesuaikan admin) selalu dipakai untuk tanggalnya.
        $rows = JapanResume::with('editor:id,name')
            ->whereDate('tanggal', '>=', $start->toDateString())
            ->whereDate('tanggal', '<=', $end->toDateString())
            ->get()
            ->mapWithKeys(fn (JapanResume $resume) => [$resume->tanggal->toDateString() => self::row(
                $resume->tanggal, $resume->shift, $resume->total, $resume->cuti, $resume->sakit_izin, $resume->sumber, $resume->editor?->name, $resume->id,
            )])
            ->all();

        // Hari setelah data Excel terakhir sampai hari ini dihitung dari absensi, kecuali yang sudah disesuaikan admin.
        $otomatisMulai = $dataSampai ? max($start, $dataSampai->copy()->addDay()) : $start;
        $otomatisSampai = min($end, $hariIni->copy()->startOfDay());
        if ($otomatisMulai->lte($otomatisSampai)) {
            foreach (self::hitungDariAbsensi($otomatisMulai, $otomatisSampai) as $row) {
                $rows[$row['tanggal']->toDateString()] ??= $row;
            }
        }

        ksort($rows);
        $rows = array_values($rows);

        return ['bulan' => $start, 'rows' => $rows, 'total' => self::total($rows), 'dataSampai' => $dataSampai];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function hitungDariAbsensi(Carbon $mulai, Carbon $sampai): array
    {
        // Shift 2 di laporan tanggal X adalah malam tanggal X-1, jadi absensi sehari sebelumnya ikut dibaca.
        $dariTanggal = $mulai->copy()->subDay();
        $rotasis = ShiftRotasi::orderBy('tanggal_mulai')->get();
        $karyawans = Karyawan::whereNotNull('group')->get(['id', 'nik', 'group']);
        $headcount = $karyawans->countBy('group')->all();

        // [Y-m-d][grup][kolom] => jumlah karyawan tidak hadir.
        $absen = [];
        $tambah = function (string $tanggal, string $grup, string $kolom) use (&$absen) {
            $absen[$tanggal][$grup][$kolom] = ($absen[$tanggal][$grup][$kolom] ?? 0) + 1;
        };

        Absensi::query()
            ->join('karyawans', 'karyawans.id', '=', 'absensis.karyawan_id')
            ->whereNotNull('karyawans.group')
            ->whereIn('absensis.kode', Absensi::kodeTidakHadir())
            ->whereDate('absensis.tanggal', '>=', $dariTanggal->toDateString())
            ->whereDate('absensis.tanggal', '<=', $sampai->toDateString())
            ->get(['absensis.tanggal', 'absensis.kode', 'karyawans.group'])
            ->each(function ($row) use ($tambah) {
                $tanggal = Carbon::parse($row->tanggal)->toDateString();
                $kolom = self::kolom($row->kode);
                if ($kolom !== null) {
                    $tambah($tanggal, $row->group, $kolom);
                }
                if ($row->kode === 'CB') {
                    $tambah($tanggal, $row->group, 'cb');
                }
            });

        // Tidak absen di mesin dan belum ada keterangan = Alpa (masuk S+I).
        $grupOf = $karyawans->pluck('group', 'id');
        foreach (KomparasiAbsensi::selBelumAdaKeterangan($karyawans, $dariTanggal, $sampai) as $karyawanId => $dates) {
            foreach (array_keys($dates) as $tanggal) {
                $tambah($tanggal, $grupOf[$karyawanId], 'sakit_izin');
            }
        }

        $adaData = self::tanggalAdaData($mulai, $sampai);
        $jumlah = fn (array $grups, string $tanggal, string $kolom) => array_sum(array_map(fn ($grup) => $absen[$tanggal][$grup][$kolom] ?? 0, $grups));
        $mp = fn (array $grups) => array_sum(array_map(fn ($grup) => $headcount[$grup] ?? 0, $grups));
        $rows = [];

        for ($day = $mulai->copy(); $day->lte($sampai); $day->addDay()) {
            if (! isset($adaData[$day->toDateString()])) {
                continue;
            }

            $kemarin = $day->copy()->subDay();
            $grupPagi = ShiftRotasi::grupPagi($day, $rotasis);
            $grupMalamKemarin = ShiftRotasi::grupPagi($kemarin, $rotasis);
            $shifts = [
                [$day->toDateString(), array_values(array_filter([$grupPagi, 'NS']))],
                [$kemarin->toDateString(), $grupMalamKemarin ? [ShiftRotasi::lawan($grupMalamKemarin)] : []],
            ];

            $total = 0;
            $cuti = 0;
            $sakitIzin = 0;
            $cutiBersama = 0;
            $shiftMasuk = 0;
            foreach ($shifts as [$tanggal, $grups]) {
                $total += $mp($grups);
                $cuti += $jumlah($grups, $tanggal, 'cuti');
                $sakitIzin += $jumlah($grups, $tanggal, 'sakit_izin');
                $cutiBersama += $jumlah($grups, $tanggal, 'cb');
                $shiftMasuk += $mp($grups) > 0 ? 1 : 0;
            }

            if ($total === 0 || $cutiBersama >= $total * self::BATAS_CUTI_BERSAMA) {
                continue;
            }

            $rows[] = self::row($day->copy(), $shiftMasuk === 2 ? JapanResume::SHIFT_DUA : JapanResume::SHIFT_SATU, $total, $cuti, $sakitIzin, self::SUMBER_OTOMATIS);
        }

        return $rows;
    }

    /**
     * Kolom Japan Resume untuk kode absensi; null = tetap dihitung hadir (WFH, DT, PC).
     */
    private static function kolom(string $kode): ?string
    {
        return match (Absensi::kategori($kode)) {
            'CT' => 'cuti',
            'S', 'I', 'Alpa' => 'sakit_izin',
            default => null,
        };
    }

    /**
     * Tanggal yang punya data absensi atau data mesin absen (hari kerja). Hari tanpa data sama sekali = libur.
     *
     * @return array<string, true>
     */
    private static function tanggalAdaData(Carbon $mulai, Carbon $sampai): array
    {
        $range = fn ($query) => $query->whereDate('tanggal', '>=', $mulai->toDateString())->whereDate('tanggal', '<=', $sampai->toDateString());

        return Absensi::query()->tap($range)->distinct()->pluck('tanggal')
            ->concat(AbsensiMesin::query()->tap($range)->distinct()->pluck('tanggal'))
            ->mapWithKeys(fn ($tanggal) => [Carbon::parse($tanggal)->toDateString() => true])
            ->all();
    }

    /**
     * @param  string  $sumber  excel / manual (tersimpan) atau otomatis (dihitung dari absensi)
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
