<?php

namespace App\Modules\Hrms\Support;

use App\Modules\Hrms\Models\Absensi;
use App\Modules\Hrms\Models\Karyawan;
use App\Modules\Hrms\Models\ShiftRotasi;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Laporan detail absensi harian per shift. Laporan tanggal X berisi:
 *  - Shift 2 (malam, 20.05 - 05.00) tanggal X-1: grup A/B yang sedang shift malam.
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

    /** Jabatan yang masuk bar "Asst. Leader up" di grafik. */
    public const JABATAN_ASST_LEADER_UP = [
        'Asst. Leader', 'Senior Staff', 'Leader', 'Supervisor', 'Section Chief', 'Asst. Manager',
        'Manager', 'Ast. General Manager', 'GM', 'Direktur',
    ];

    private Collection $rotasis;

    /** @var array<string, int> jumlah karyawan per grup */
    private array $headcount;

    /**
     * @param  bool  $hitungCuti  false = kategori Cuti (CT) tidak dihitung tidak hadir: tidak masuk daftar, total, persen, maupun grafik.
     */
    public function __construct(private bool $denganGrafik = true, private bool $hitungCuti = true)
    {
        $this->rotasis = ShiftRotasi::orderBy('tanggal_mulai')->get();
        $this->headcount = Karyawan::whereNotNull('group')
            ->selectRaw('`group`, count(*) as total')->groupBy('group')
            ->pluck('total', 'group')->map(fn ($total) => (int) $total)->all();
    }

    public static function build(Carbon $tanggal, bool $hitungCuti = true): array
    {
        return (new self(hitungCuti: $hitungCuti))->report($tanggal);
    }

    /**
     * Sama seperti build(), tanpa menghitung grafik bulanan ('grafik' => null).
     */
    public static function buildTanpaGrafik(Carbon $tanggal, bool $hitungCuti = true): array
    {
        return (new self(denganGrafik: false, hitungCuti: $hitungCuti))->report($tanggal);
    }

    /**
     * Kode absen yang dihitung tidak hadir di laporan ini.
     *
     * @return list<string>
     */
    private function kodeDihitung(): array
    {
        return $this->hitungCuti
            ? Absensi::kodeTidakHadir()
            : array_values(array_diff(Absensi::kodeTidakHadir(), Absensi::KATEGORI['CT']));
    }

    private function report(Carbon $tanggal): array
    {
        $malam = $tanggal->copy()->subDay();

        return [
            'grupPagi' => ShiftRotasi::grupPagi($tanggal, $this->rotasis),
            'rotasiTerakhir' => $this->rotasis->last(),
            'sections' => [
                $this->section('malam', 'Shift 2', 'Shift malam · 20.05 - 05.00', $malam),
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
            ->whereIn('kode', $this->kodeDihitung())
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

    private function headcountOf(array $grups, ?array $headcount = null): int
    {
        $headcount ??= $this->headcount;

        return array_sum(array_map(fn ($grup) => $headcount[$grup] ?? 0, $grups));
    }

    public static function isAsstLeaderUp(?string $jabatan): bool
    {
        return in_array($jabatan, self::JABATAN_ASST_LEADER_UP, true);
    }

    /**
     * Jumlah hari kerja tidak hadir berturut-turut (termasuk tanggal laporan) per karyawan, tanpa batas panjang.
     * Hari tanpa input tidak memutus hitungan kalau itu akhir pekan atau hari libur (lihat hariLibur()).
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
            ->get(['karyawan_id', 'tanggal'])
            ->groupBy('karyawan_id')
            ->map(fn (Collection $items) => $items->mapWithKeys(fn (Absensi $absensi) => [$absensi->tanggal->toDateString() => true])->all());

        $allDates = $dates->flatMap(fn (array $set) => array_keys($set));
        $libur = $this->hariLibur($allDates->min(), $allDates->max());

        return $dates->map(function (array $set) use ($tanggal, $libur) {
            $total = 1;
            [$first, $last] = [min(array_keys($set)), max(array_keys($set))];

            foreach ([-1, 1] as $step) {
                $day = $tanggal->copy()->addDays($step);

                while ($day->toDateString() >= $first && $day->toDateString() <= $last) {
                    $key = $day->toDateString();

                    if (isset($set[$key])) {
                        $total++;
                    } elseif (! $day->isWeekend() && ! isset($libur[$key])) {
                        break;
                    }

                    $day->addDays($step);
                }
            }

            return $total;
        })->all();
    }

    /**
     * Hari kerja (Senin - Jumat) yang tidak punya satu pun data absensi dari seluruh karyawan dianggap libur
     * (tanggal merah / cuti bersama), karena di hari kerja biasa selalu ada yang diinput.
     *
     * @return array<string, true>
     */
    private function hariLibur(string $mulai, string $sampai): array
    {
        $adaData = Absensi::query()
            ->whereBetween('tanggal', [$mulai, $sampai])
            ->distinct()
            ->pluck('tanggal')
            ->mapWithKeys(fn ($tanggal) => [Carbon::parse($tanggal)->toDateString() => true])
            ->all();

        $libur = [];

        for ($day = Carbon::parse($mulai); $day->toDateString() <= $sampai; $day->addDay()) {
            if (! $day->isWeekend() && ! isset($adaData[$day->toDateString()])) {
                $libur[$day->toDateString()] = true;
            }
        }

        return $libur;
    }

    /**
     * Persentase ketidakhadiran shift ini per hari kerja (Senin - Jumat) dalam bulan tanggal laporan.
     * "tanpa_cuti" dan "leader" (Asst. Leader up) selalu tidak menghitung cuti. Hari setelah tanggal laporan dibiarkan kosong.
     */
    private function grafik(string $shift, Carbon $tanggal): array
    {
        $start = $tanggal->copy()->startOfMonth();
        $end = $tanggal->copy()->endOfMonth();

        // [Y-m-d][grup] => jumlah karyawan tidak hadir: semua kode, tanpa cuti, dan Asst. Leader up tanpa cuti.
        $counts = [];
        $tanpaCutiCounts = [];
        $leaderCounts = [];
        $tambah = function (string $key, string $grup, ?string $jabatan, bool $cuti) use (&$counts, &$tanpaCutiCounts, &$leaderCounts) {
            $counts[$key][$grup] = ($counts[$key][$grup] ?? 0) + 1;

            if ($cuti) {
                return;
            }

            $tanpaCutiCounts[$key][$grup] = ($tanpaCutiCounts[$key][$grup] ?? 0) + 1;

            if (self::isAsstLeaderUp($jabatan)) {
                $leaderCounts[$key][$grup] = ($leaderCounts[$key][$grup] ?? 0) + 1;
            }
        };

        Absensi::query()
            ->join('karyawans', 'karyawans.id', '=', 'absensis.karyawan_id')
            ->whereNotNull('karyawans.group')
            ->whereIn('absensis.kode', Absensi::kodeTidakHadir())
            ->whereBetween('absensis.tanggal', [$start->toDateString(), $end->toDateString()])
            ->get(['absensis.tanggal', 'absensis.kode', 'karyawans.group', 'karyawans.jabatan'])
            ->each(fn ($row) => $tambah($row->tanggal->toDateString(), $row->group, $row->jabatan, in_array($row->kode, Absensi::KATEGORI['CT'], true)));

        // Ditambah karyawan yang tidak absen di mesin dan belum ada keterangan (dihitung Alpa).
        $karyawans = Karyawan::whereNotNull('group')->get(['id', 'nik', 'group', 'jabatan'])->keyBy('id');
        foreach (KomparasiAbsensi::selBelumAdaKeterangan($karyawans, $start, $end) as $karyawanId => $dates) {
            foreach (array_keys($dates) as $key) {
                $tambah($key, $karyawans[$karyawanId]->group, $karyawans[$karyawanId]->jabatan, false);
            }
        }

        $leaderHeadcount = $karyawans->filter(fn (Karyawan $karyawan) => self::isAsstLeaderUp($karyawan->jabatan))
            ->countBy('group')->all();
        $persen = fn (array $daily, array $grups, int $headcount) => $headcount > 0
            ? round(array_sum(array_map(fn ($grup) => $daily[$grup] ?? 0, $grups)) / $headcount * 100, 1)
            : null;

        $points = [];

        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            if ($day->isWeekend()) {
                continue;
            }

            $actual = null;
            $tanpaCuti = null;
            $leader = null;

            if ($day->lte($tanggal)) {
                $grups = $this->grups($shift, $day);
                $key = $day->toDateString();
                $tanpaCuti = $persen($tanpaCutiCounts[$key] ?? [], $grups, $this->headcountOf($grups));
                $actual = $this->hitungCuti ? $persen($counts[$key] ?? [], $grups, $this->headcountOf($grups)) : $tanpaCuti;
                $leader = $persen($leaderCounts[$key] ?? [], $grups, $this->headcountOf($grups, $leaderHeadcount));
            }

            $points[] = [
                'day' => $day->day,
                'actual' => $actual,
                'tanpa_cuti' => $tanpaCuti,
                'leader' => $leader,
                'selected' => $day->isSameDay($tanggal),
            ];
        }

        return ['bulan' => $start, 'target' => self::TARGET_PERSEN, 'points' => $points];
    }
}
