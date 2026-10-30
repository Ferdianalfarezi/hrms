<?php

namespace App\Modules\Hrms\Support;

use App\Modules\Hrms\Models\Absensi;
use App\Modules\Hrms\Models\Karyawan;
use App\Modules\Hrms\Models\ShiftRotasi;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Laporan detail absensi harian per shift. Laporan tanggal X berisi:
 *  - Shift 2 (malam, 20.00 - 05.00) tanggal X-1: grup A/B yang sedang shift malam.
 *  - Shift 1 (pagi, 07.00 - 16.00) tanggal X: grup A/B yang sedang shift pagi + grup NS.
 *
 * Karyawan yang tidak absen di mesin tapi belum diberi keterangan oleh Admin Departemen dihitung sebagai Alpa.
 */
class LaporanAbsensiShift
{
    /** Target maksimal persentase ketidakhadiran per shift (garis "Target" di grafik). */
    public const TARGET_PERSEN = 3;

    /** Keterangan baris Alpa otomatis dari data mesin absen. */
    public const KETERANGAN_TANPA_KETERANGAN = 'Tidak absen di mesin, belum ada keterangan';

    /** Batas hari ke belakang/depan saat menghitung durasi libur berturut-turut. */
    private const DURASI_MAKS = 60;

    private Collection $rotasis;

    /** @var array<string, int> jumlah karyawan per grup */
    private array $headcount;

    public function __construct(private bool $denganGrafik = true)
    {
        $this->rotasis = ShiftRotasi::orderBy('tanggal_mulai')->get();
        $this->headcount = Karyawan::whereNotNull('group')
            ->selectRaw('`group`, count(*) as total')->groupBy('group')
            ->pluck('total', 'group')->map(fn ($total) => (int) $total)->all();
    }

    public static function build(Carbon $tanggal): array
    {
        return (new self)->report($tanggal);
    }

    /**
     * Sama seperti build(), tanpa menghitung grafik bulanan ('grafik' => null).
     */
    public static function buildTanpaGrafik(Carbon $tanggal): array
    {
        return (new self(denganGrafik: false))->report($tanggal);
    }

    private function report(Carbon $tanggal): array
    {
        $malam = $tanggal->copy()->subDay();

        return [
            'grupPagi' => ShiftRotasi::grupPagi($tanggal, $this->rotasis),
            'rotasiTerakhir' => $this->rotasis->last(),
            'sections' => [
                $this->section('malam', 'Shift 2', 'Shift malam · 20.00 - 05.00', $malam),
                $this->section('pagi', 'Shift 1', 'Shift pagi & non shift · 07.00 - 16.00', $tanggal),
            ],
        ];
    }

    /**
     * Grup yang bekerja di shift tersebut pada tanggal itu.
     *
     * @return list<string>
     */
    private function grups(string $shift, Carbon $tanggal): array
    {
        $grupPagi = ShiftRotasi::grupPagi($tanggal, $this->rotasis);

        if ($shift === 'malam') {
            return $grupPagi ? [ShiftRotasi::lawan($grupPagi)] : [];
        }

        return array_values(array_filter([$grupPagi, 'NS']));
    }

    private function section(string $shift, string $judul, string $keterangan, Carbon $tanggal): array
    {
        $grups = $this->grups($shift, $tanggal);
        $kategoris = array_keys(Absensi::KATEGORI);
        $urutan = array_flip($kategoris);

        $absensis = Absensi::query()
            ->with('karyawan.department')
            ->whereDate('tanggal', $tanggal->toDateString())
            ->whereIn('kode', Absensi::kodeTidakHadir())
            ->whereHas('karyawan', fn ($query) => $query->whereIn('group', $grups))
            ->get();

        $durasi = $this->durasi($absensis->pluck('karyawan_id')->all(), $tanggal);

        // Tidak absen di mesin + belum ada kode dari admin = Alpa.
        $karyawans = Karyawan::with('department')->whereIn('group', $grups)->get();
        $tanpaKeterangan = KomparasiAbsensi::selBelumAdaKeterangan($karyawans, $tanggal, $tanggal);

        $rows = $absensis
            ->map(fn (Absensi $absensi) => [
                'karyawan' => $absensi->karyawan,
                'kode' => $absensi->kode,
                'kategori' => Absensi::kategori($absensi->kode),
                'keterangan' => $absensi->keterangan,
                'durasi' => $durasi[$absensi->karyawan_id] ?? 1,
            ])
            ->concat($karyawans->filter(fn (Karyawan $karyawan) => isset($tanpaKeterangan[$karyawan->id]))
                ->map(fn (Karyawan $karyawan) => [
                    'karyawan' => $karyawan,
                    'kode' => 'A',
                    'kategori' => 'Alpa',
                    'keterangan' => self::KETERANGAN_TANPA_KETERANGAN,
                    'durasi' => 1,
                ]))
            // Urut seperti di Excel: per kategori (CT, S, I, WFH, Alpa), lalu NIK.
            ->sortBy([
                fn ($a, $b) => $urutan[$a['kategori']] <=> $urutan[$b['kategori']],
                fn ($a, $b) => [strlen($a['karyawan']->nik), $a['karyawan']->nik] <=> [strlen($b['karyawan']->nik), $b['karyawan']->nik],
            ])->values();

        $totals = collect($kategoris)->mapWithKeys(fn ($kategori) => [$kategori => $rows->where('kategori', $kategori)->count()])->all();
        $headcount = $this->headcountOf($grups);

        return [
            'shift' => $shift,
            'judul' => $judul,
            'keterangan' => $keterangan,
            'tanggal' => $tanggal,
            'grups' => $grups,
            'rows' => $rows,
            'totals' => $totals,
            'headcount' => $headcount,
            'persen' => $headcount > 0 ? round($rows->count() / $headcount * 100, 1) : null,
            'grafik' => $this->denganGrafik ? $this->grafik($shift, $tanggal) : null,
        ];
    }

    private function headcountOf(array $grups): int
    {
        return array_sum(array_map(fn ($grup) => $this->headcount[$grup] ?? 0, $grups));
    }

    /**
     * Jumlah hari tidak hadir berturut-turut (termasuk tanggal laporan) per karyawan.
     *
     * @return array<int, int>
     */
    private function durasi(array $karyawanIds, Carbon $tanggal): array
    {
        if ($karyawanIds === []) {
            return [];
        }

        $dates = Absensi::whereIn('karyawan_id', $karyawanIds)
            ->whereIn('kode', Absensi::kodeTidakHadir())
            ->whereBetween('tanggal', [
                $tanggal->copy()->subDays(self::DURASI_MAKS)->toDateString(),
                $tanggal->copy()->addDays(self::DURASI_MAKS)->toDateString(),
            ])
            ->get(['karyawan_id', 'tanggal'])
            ->groupBy('karyawan_id')
            ->map(fn (Collection $items) => $items->mapWithKeys(fn (Absensi $absensi) => [$absensi->tanggal->toDateString() => true])->all());

        return $dates->map(function (array $set) use ($tanggal) {
            $total = 1;

            foreach ([-1, 1] as $step) {
                $day = $tanggal->copy()->addDays($step);

                while (isset($set[$day->toDateString()])) {
                    $total++;
                    $day->addDays($step);
                }
            }

            return $total;
        })->all();
    }

    /**
     * Persentase ketidakhadiran shift ini per hari kerja (Senin - Jumat) dalam bulan tanggal laporan.
     * Hari setelah tanggal laporan dibiarkan kosong.
     */
    private function grafik(string $shift, Carbon $tanggal): array
    {
        $start = $tanggal->copy()->startOfMonth();
        $end = $tanggal->copy()->endOfMonth();

        // [Y-m-d][grup] => jumlah karyawan tidak hadir
        $counts = [];
        Absensi::query()
            ->join('karyawans', 'karyawans.id', '=', 'absensis.karyawan_id')
            ->whereNotNull('karyawans.group')
            ->whereIn('absensis.kode', Absensi::kodeTidakHadir())
            ->whereBetween('absensis.tanggal', [$start->toDateString(), $end->toDateString()])
            ->get(['absensis.tanggal', 'karyawans.group'])
            ->each(function ($row) use (&$counts) {
                $key = $row->tanggal->toDateString();
                $counts[$key][$row->group] = ($counts[$key][$row->group] ?? 0) + 1;
            });

        // Ditambah karyawan yang tidak absen di mesin dan belum ada keterangan (dihitung Alpa).
        $karyawans = Karyawan::whereNotNull('group')->get(['id', 'nik', 'group']);
        $groupOf = $karyawans->pluck('group', 'id');
        foreach (KomparasiAbsensi::selBelumAdaKeterangan($karyawans, $start, $end) as $karyawanId => $dates) {
            foreach (array_keys($dates) as $key) {
                $counts[$key][$groupOf[$karyawanId]] = ($counts[$key][$groupOf[$karyawanId]] ?? 0) + 1;
            }
        }

        $points = [];

        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            if ($day->isWeekend()) {
                continue;
            }

            $actual = null;

            if ($day->lte($tanggal)) {
                $grups = $this->grups($shift, $day);
                $headcount = $this->headcountOf($grups);
                $absen = array_sum(array_map(fn ($grup) => $counts[$day->toDateString()][$grup] ?? 0, $grups));
                $actual = $headcount > 0 ? round($absen / $headcount * 100, 1) : null;
            }

            $points[] = ['day' => $day->day, 'actual' => $actual, 'selected' => $day->isSameDay($tanggal)];
        }

        return ['bulan' => $start, 'target' => self::TARGET_PERSEN, 'points' => $points];
    }
}
