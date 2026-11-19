<?php

namespace App\Modules\Hrms\Support;

use App\Models\Application;
use App\Models\User;
use App\Modules\Hrms\Models\Absensi;
use App\Modules\Hrms\Models\AbsensiMesin;
use App\Modules\Hrms\Models\Karyawan;
use App\Modules\Hrms\Models\ShiftRotasi;
use App\Support\ModuleDashboard;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Dashboard HRMS berbasis tabel karyawans. Semua angka mengikuti hak akses user:
 * Admin Departemen hanya melihat karyawan di departemennya.
 */
class HrmsDashboard
{
    /**
     * Rincian tidak hadir di kartu absensi hari ini dan grafik tren kehadiran: kategori Absensi::KATEGORI,
     * ditambah "tanpa" = tidak absen di mesin dan belum ada keterangan.
     *
     * @var array<string, array{label: string, tone: string}>
     */
    private const KATEGORI_TIDAK_HADIR = [
        'S' => ['label' => 'Sakit', 'tone' => 'warning'],
        'I' => ['label' => 'Izin', 'tone' => 'primary'],
        'CT' => ['label' => 'Cuti', 'tone' => 'primary-soft'],
        'WFH' => ['label' => 'WFH', 'tone' => 'soft'],
        'Alpa' => ['label' => 'Alpa', 'tone' => 'navy'],
        'tanpa' => ['label' => 'Tanpa keterangan', 'tone' => 'danger'],
    ];

    /** Jumlah hari kerja (Senin - Jumat) di grafik tren kehadiran, berakhir hari ini. */
    private const HARI_TREN = 7;

    /** Ukuran viewBox SVG grafik garis tren kehadiran. */
    public const TREN_LEBAR = 700;

    public const TREN_TINGGI = 240;

    public static function build(User $viewer): array
    {
        $application = Application::where('code', 'hrms')->firstOrFail();
        $visible = fn () => Karyawan::query()->visibleTo($viewer);
        $managedDepartments = $viewer->managedDepartmentList();
        $departmentLabel = $managedDepartments->isNotEmpty() ? __('Departemen').' '.$managedDepartments->pluck('name')->implode(', ') : null;

        $total = $visible()->count();
        $statusCounts = $visible()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $tetap = (int) ($statusCounts['Tetap'] ?? 0);
        $pkwt = (int) $statusCounts->only(['PKWT 1', 'PKWT 2', 'PKWT 3'])->sum();
        $magang = (int) ($statusCounts['Pemagangan'] ?? 0);
        $perempuan = $visible()->where('jenis_kelamin', 'Perempuan')->count();
        $hariIni = ProduktivitasHariIni::build($visible);

        return [
            'application' => $application,
            'subtitle' => $departmentLabel ?? $application->description,
            'myApplications' => $viewer->accessibleApplications(),
            'bigStats' => [
                ...self::shiftAktif(),
                ['label' => 'Karyawan', 'value' => $total, 'icon' => 'users'],
                ['label' => 'Karyawan Tetap', 'value' => $tetap, 'icon' => 'badge'],
                ['label' => __('Masuk :tahun', ['tahun' => now()->year]), 'value' => $visible()->whereYear('tanggal_masuk', now()->year)->count(), 'icon' => 'calendar'],
            ],
            'pills' => collect([
                ['label' => 'Tetap', 'count' => $tetap],
                ['label' => 'PKWT', 'count' => $pkwt],
                ['label' => 'Pemagangan', 'count' => $magang],
            ])->filter(fn ($item) => $item['count'] > 0)
                ->map(fn ($item) => [...$item, 'percent' => ModuleDashboard::percent($item['count'], $total)])
                ->values()->all(),
            'pillOutlines' => $total > 0 ? [
                ['label' => 'Laki-laki', 'count' => $total - $perempuan],
                ['label' => 'Perempuan', 'count' => $perempuan],
            ] : [],
            // Admin Departemen: daftar tugas absensi yang belum diisi; admin HRMS: tren kehadiran 7 hari kerja.
            'growth' => $viewer->isDepartmentAdmin() ? self::belumAdaKeterangan($visible) : null,
            'tren' => $viewer->isDepartmentAdmin() ? null : self::trenKehadiran($visible),
            // Admin Departemen: persentase kehadiran tiap karyawan di departemennya bulan ini.
            'composition' => $viewer->isDepartmentAdmin()
                ? self::kehadiranKaryawan($visible, $departmentLabel)
                : self::absensiHariIni($visible, $hariIni['perShift']),
            // Karyawan yang berulang tahun minggu ini: kartu di baris bawah (bukan di kolom kanan), jadi kartu absensi memakai seluruh kolom kanan.
            'people' => [...self::ulangTahunMingguIni($visible), 'side' => false],
            'hariIni' => $hariIni,
        ];
    }

    /**
     * Grup A/B yang shift pagi dan malam hari ini menurut jadwal rotasi (diatur di Laporan Detail).
     *
     * @return list<array{label: string, value: string, icon: string}>
     */
    private static function shiftAktif(): array
    {
        $grupPagi = ShiftRotasi::grupPagi(Carbon::today());

        if ($grupPagi === null) {
            return [['label' => 'Rotasi shift belum diatur', 'value' => '-', 'icon' => 'sun']];
        }

        return [
            ['label' => 'Shift Pagi', 'value' => $grupPagi, 'icon' => 'sun'],
            ['label' => 'Shift Malam', 'value' => ShiftRotasi::lawan($grupPagi), 'icon' => 'moon'],
        ];
    }

    /**
     * Persentase kehadiran tiap karyawan bulan ini, hari kerja Senin - Jumat sampai hari ini (mulai tanggal masuk kalau masuk bulan ini).
     * Tidak hadir = kode tidak hadir dari admin, atau tidak absen di mesin dan belum ada keterangan. Urut dari yang paling rendah.
     */
    private static function kehadiranKaryawan(\Closure $visible, ?string $departmentLabel): array
    {
        $today = Carbon::today();
        $mulai = $today->copy()->startOfMonth();
        $karyawans = $visible()->orderBy('nama')->get(['id', 'nik', 'nama', 'group', 'tanggal_masuk']);

        // [karyawan_id][Y-m-d] => true
        $tidakHadir = KomparasiAbsensi::selBelumAdaKeterangan($karyawans, $mulai, $today);
        Absensi::whereIn('karyawan_id', $karyawans->pluck('id'))
            ->where('tanggal', '>=', $mulai->toDateString())
            ->where('tanggal', '<', $today->copy()->addDay()->toDateString())
            ->whereIn('kode', Absensi::kodeTidakHadir())
            ->get(['karyawan_id', 'tanggal'])
            ->each(function (Absensi $absensi) use (&$tidakHadir) {
                $tidakHadir[$absensi->karyawan_id][$absensi->tanggal->toDateString()] = true;
            });

        $totalHariKerja = 0;
        $totalHadir = 0;
        $rows = $karyawans->map(function (Karyawan $karyawan) use ($mulai, $today, $tidakHadir, &$totalHariKerja, &$totalHadir) {
            $dari = $karyawan->tanggal_masuk?->gt($mulai) ? $karyawan->tanggal_masuk->copy()->startOfDay() : $mulai->copy();
            $hariKerja = 0;
            $absen = 0;
            for ($day = $dari; $day->lte($today); $day->addDay()) {
                if ($day->isWeekday()) {
                    $hariKerja++;
                    $absen += isset($tidakHadir[$karyawan->id][$day->toDateString()]) ? 1 : 0;
                }
            }

            $totalHariKerja += $hariKerja;
            $totalHadir += $hariKerja - $absen;

            return ['karyawan' => $karyawan, 'hariKerja' => $hariKerja, 'hadir' => $hariKerja - $absen];
        })->filter(fn (array $row) => $row['hariKerja'] > 0)
            ->sortBy(fn (array $row) => $row['hadir'] / $row['hariKerja'])
            ->values();

        return [
            'title' => __('Kehadiran :bulan', ['bulan' => $today->translatedFormat('M')]),
            'subtitle' => $departmentLabel,
            'value' => $totalHariKerja > 0 ? self::persenHadir($totalHadir, $totalHariKerja).'%' : '-',
            'unit' => 'hari',
            'bar' => false,
            'scroll' => true,
            'segments' => $rows->map(fn (array $row) => [
                'label' => $row['karyawan']->nama,
                'count' => $row['hadir'],
                'text' => self::persenHadir($row['hadir'], $row['hariKerja']).'%',
                'title' => "{$row['hadir']} dari {$row['hariKerja']} hari kerja",
                'tone' => match (true) {
                    $row['hadir'] === $row['hariKerja'] => 'success',
                    ($row['hariKerja'] - $row['hadir']) / $row['hariKerja'] * 100 > LaporanAbsensiShift::TARGET_PERSEN => 'danger',
                    default => 'navy',
                },
                'always' => true,
            ])->all(),
            'note' => $rows->isEmpty()
                ? 'Belum ada hari kerja bulan ini.'
                : __('Hari kerja Senin - Jumat, 1 - :tanggal. Merah = tidak hadir di atas :target%.', ['tanggal' => $today->translatedFormat('d M'), 'target' => LaporanAbsensiShift::TARGET_PERSEN]),
        ];
    }

    /**
     * Karyawan yang tidak absen di mesin tapi belum diberi kode oleh Admin Departemen, per hari kerja (Senin - Jumat).
     * Hari ini ikut dihitung; kalau hari ini Sabtu/Minggu, grafik berakhir di Jumat terakhir.
     */
    private static function belumAdaKeterangan(\Closure $visible): array
    {
        $today = Carbon::today();
        $days = collect();
        for ($day = $today->isWeekend() ? $today->copy()->previousWeekday() : $today->copy(); $days->count() < 7; $day->subWeekday()) {
            $days->prepend($day->copy());
        }

        $sel = KomparasiAbsensi::selBelumAdaKeterangan($visible()->get(['id', 'nik', 'group']), $days->first(), $days->last());
        $perTanggal = collect($sel)->flatMap(fn (array $tanggal) => array_keys($tanggal))->countBy();

        $bars = $days->map(fn (Carbon $day) => [
            'label' => $day->translatedFormat('D'),
            'count' => $perTanggal[$day->toDateString()] ?? 0,
            'highlight' => $day->isToday(),
        ]);

        return [
            'title' => 'Belum Ada Keterangan',
            'total' => $bars->sum('count'),
            'caption' => "absensi belum diisi\n7 hari kerja terakhir",
            'bubble' => 'hari ini',
            'bars' => $bars->all(),
            'link' => route('hrms.absensi.index'),
        ];
    }

    /**
     * Ringkasan absensi hari ini seperti di halaman Absensi Harian, ditambah hasil komparasi mesin absen.
     * Rinciannya ditampilkan per shift (Shift 1 di atas, Shift 2 di bawah).
     *
     * @param  list<array{label: string, sub: ?string, total: int, hadir: int, perKategori: array<string, int>}>  $perShift  dari ProduktivitasHariIni
     */
    private static function absensiHariIni(\Closure $visible, array $perShift): array
    {
        $today = Carbon::today();
        $karyawans = $visible()->get(['id', 'nik', 'group']);
        $total = $karyawans->count();

        $kodes = Absensi::whereIn('karyawan_id', $karyawans->pluck('id'))
            ->whereDate('tanggal', $today->toDateString())
            ->whereIn('kode', Absensi::kodeTidakHadir())
            ->pluck('kode', 'karyawan_id');

        $tanpaKeterangan = count(KomparasiAbsensi::selBelumAdaKeterangan($karyawans, $today, $today));
        // Admin input tidak hadir, padahal mesin mencatat karyawannya absen (kemungkinan salah input).
        $cekUlang = collect(KomparasiAbsensi::selHadirDiMesin($karyawans, $today, $today))
            ->keys()->filter(fn ($id) => isset($kodes[$id]))->count();
        $hadir = max($total - $kodes->count() - $tanpaKeterangan, 0);

        $note = AbsensiMesin::whereDate('tanggal', $today->toDateString())->exists()
            ? ($cekUlang > 0 ? __(':count kode tidak hadir perlu dicek ulang (hadir di mesin absen).', ['count' => $cekUlang]) : __('Data mesin absen hari ini sudah diupload.'))
            : __('Data mesin absen hari ini belum diupload.');

        return [
            'title' => __('Absensi').' '.$today->translatedFormat('d M'),
            'value' => self::persenHadir($hadir, $total).'%',
            'unit' => 'orang',
            'segments' => self::segmenAbsensi($total, $hadir, [...$kodes->countBy(fn (string $kode) => Absensi::kategori($kode))->all(), 'tanpa' => $tanpaKeterangan]),
            'shifts' => collect($perShift)->map(fn (array $shift) => [
                'label' => $shift['label'],
                'sub' => $shift['sub'],
                'value' => $shift['total'] > 0 ? self::persenHadir($shift['hadir'], $shift['total']).'%' : '-',
                'segments' => self::segmenAbsensi($shift['total'], $shift['hadir'], $shift['perKategori']),
            ])->all(),
            'note' => $note,
        ];
    }

    /**
     * Baris absensi: Hadir, Tidak hadir, lalu rincian per kategori. Semua kategori selalu tampil (walau 0) supaya rinciannya lengkap.
     * "Tidak hadir" = jumlah rincian di bawahnya, jadi tidak ikut bar ("stack" => false). Persen tidak hadir = sisa persen hadir, supaya jumlahnya 100%.
     *
     * @param  array<string, int>  $perKategori  kategori KATEGORI_TIDAK_HADIR => jumlah
     * @return list<array{label: string, count: int, tone: string, always: bool, text: string, stack?: bool}>
     */
    private static function segmenAbsensi(int $total, int $hadir, array $perKategori): array
    {
        $keterangan = fn (int $jumlah, string $persen) => number_format($jumlah, 0, ',', '.').' '.__('orang').' · '.$persen.'%';

        return [
            ['label' => 'Hadir', 'count' => $hadir, 'tone' => 'success', 'always' => true, 'text' => $keterangan($hadir, self::persenHadir($hadir, $total))],
            ['label' => 'Tidak hadir', 'count' => $total - $hadir, 'tone' => 'outline', 'always' => true, 'stack' => false,
                'text' => $keterangan($total - $hadir, self::persenSisa($hadir, $total))],
            ...self::rincianTidakHadir($perKategori)
                ->map(fn (array $kategori) => [...$kategori, 'always' => true, 'text' => $keterangan($kategori['count'], self::persen($kategori['count'], $total))])->all(),
        ];
    }

    /**
     * Tren kehadiran 7 hari kerja terakhir sampai hari ini (Sabtu/Minggu: sampai Jumat terakhir), per jenis kehadiran.
     * Dipakai grafik garis, batang, pie, dan tabel di kartu yang sama.
     */
    private static function trenKehadiran(\Closure $visible): array
    {
        $today = Carbon::today();
        $days = collect();
        for ($day = $today->isWeekend() ? $today->copy()->previousWeekday() : $today->copy(); $days->count() < self::HARI_TREN; $day->subWeekday()) {
            $days->prepend($day->copy());
        }
        $rekap = self::rekapPerHari($visible, $days);

        $series = collect([['label' => 'Hadir', 'tone' => 'success', 'counts' => $rekap->pluck('hadir')->all()]])
            ->concat(collect(array_values(self::KATEGORI_TIDAK_HADIR))->map(fn (array $info, int $index) => [
                ...$info,
                'counts' => $rekap->map(fn (array $hari) => $hari['rincian'][$index]['count'])->all(),
            ]));
        $grandTotal = $series->sum(fn (array $item) => array_sum($item['counts']));

        $skala = self::skalaSumbu((int) $series->flatMap(fn (array $item) => $item['counts'])->max());
        $series = $series->map(function (array $item) use ($skala, $grandTotal) {
            $titik = collect($item['counts'])->map(fn (int $count, int $index) => [
                $index / (self::HARI_TREN - 1) * self::TREN_LEBAR,
                self::TREN_TINGGI - $count / $skala['max'] * self::TREN_TINGGI,
            ])->all();

            return [
                ...$item,
                'total' => array_sum($item['counts']),
                'persen' => self::persen(array_sum($item['counts']), $grandTotal),
                'path' => self::garisHalus($titik),
            ];
        });

        $totalHariOrang = $rekap->sum('total');

        return [
            'periode' => $days->first()->translatedFormat('d M').' - '.$days->last()->translatedFormat('d M Y'),
            // Rata-rata persen hadir selama 7 hari (hari-orang hadir / hari-orang total).
            'value' => $totalHariOrang > 0 ? self::persenHadir($rekap->sum('hadir'), $totalHariOrang).'%' : '-',
            'days' => $rekap->map(fn (array $hari) => [
                'label' => $hari['day']->translatedFormat('D'),
                'date' => $hari['day']->translatedFormat('d M'),
                'total' => $hari['total'],
                'tidakHadir' => $hari['tidakHadir'],
                'persen' => $hari['total'] > 0 ? self::persenHadir($hari['hadir'], $hari['total']) : null,
                'diAtasTarget' => $hari['total'] > 0 && $hari['tidakHadir'] / $hari['total'] * 100 > LaporanAbsensiShift::TARGET_PERSEN,
                'today' => $hari['day']->isToday(),
            ])->all(),
            'series' => $series->all(),
            'ticks' => $skala['ticks'],
            'max' => $skala['max'],
            'target' => LaporanAbsensiShift::TARGET_PERSEN,
            'link' => route('hrms.absensi.index'),
        ];
    }

    /**
     * Karyawan yang berulang tahun minggu ini (Senin - Minggu), urut tanggal lalu nama. Yang ulang tahun hari ini diberi tanda centang.
     */
    private static function ulangTahunMingguIni(\Closure $visible): array
    {
        $awalMinggu = Carbon::today()->startOfWeek();
        $days = collect(range(0, 6))->map(fn (int $offset) => $awalMinggu->copy()->addDays($offset));

        $rows = $visible()->whereNotNull('tgl_lahir')->with('department:id,name')
            ->get(['id', 'nik', 'nama', 'tgl_lahir', 'department_id'])
            ->map(fn (Karyawan $karyawan) => [
                'karyawan' => $karyawan,
                'day' => $days->first(fn (Carbon $day) => $day->month === $karyawan->tgl_lahir->month && $day->day === $karyawan->tgl_lahir->day),
            ])
            ->filter(fn (array $row) => $row['day'] !== null)
            ->sortBy(fn (array $row) => $row['day']->format('md').$row['karyawan']->nama)
            ->values();

        return [
            'title' => 'Ulang Tahun Minggu Ini',
            'counter' => $rows->count(),
            'items' => $rows->map(fn (array $row) => [
                'name' => $row['karyawan']->nama,
                'sub' => collect([
                    $row['day']->isToday() ? __('Hari ini') : $row['day']->translatedFormat('D, d M'),
                    __('ke-:umur', ['umur' => $row['day']->year - $row['karyawan']->tgl_lahir->year]),
                    $row['karyawan']->department?->name,
                ])->filter()->join(' · '),
                'check' => $row['day']->isToday(),
                'url' => route('hrms.karyawans.index', ['q' => $row['karyawan']->nik]),
            ])->all(),
            'empty' => 'Tidak ada karyawan yang berulang tahun minggu ini.',
        ];
    }

    /**
     * Rekap kehadiran per hari. Jumlah karyawan per hari hanya yang sudah masuk pada hari itu.
     * Tidak hadir = kode tidak hadir dari admin, atau tidak absen di mesin dan belum ada keterangan.
     *
     * @param  Collection<int, Carbon>  $days  urut dari yang paling lama
     * @return Collection<int, array{day: Carbon, total: int, hadir: int, tidakHadir: int, rincian: list<array{label: string, tone: string, count: int}>}>
     */
    private static function rekapPerHari(\Closure $visible, Collection $days): Collection
    {
        $mulai = $days->first();
        $sampai = $days->last();
        $karyawans = $visible()->get(['id', 'nik', 'group', 'tanggal_masuk']);

        // [Y-m-d][kategori] => jumlah karyawan
        $tidakHadir = [];
        Absensi::whereIn('karyawan_id', $karyawans->pluck('id'))
            ->where('tanggal', '>=', $mulai->toDateString())
            ->where('tanggal', '<', $sampai->copy()->addDay()->toDateString())
            ->whereIn('kode', Absensi::kodeTidakHadir())
            ->get(['karyawan_id', 'tanggal', 'kode'])
            ->unique(fn (Absensi $absensi) => $absensi->karyawan_id.'|'.$absensi->tanggal->toDateString())
            ->each(function (Absensi $absensi) use (&$tidakHadir) {
                $kategori = Absensi::kategori($absensi->kode);
                $tidakHadir[$absensi->tanggal->toDateString()][$kategori] = ($tidakHadir[$absensi->tanggal->toDateString()][$kategori] ?? 0) + 1;
            });
        foreach (KomparasiAbsensi::selBelumAdaKeterangan($karyawans, $mulai, $sampai) as $tanggal) {
            foreach (array_keys($tanggal) as $key) {
                $tidakHadir[$key]['tanpa'] = ($tidakHadir[$key]['tanpa'] ?? 0) + 1;
            }
        }

        return $days->map(function (Carbon $day) use ($karyawans, $tidakHadir) {
            $total = $karyawans->filter(fn (Karyawan $karyawan) => $karyawan->tanggal_masuk === null || $karyawan->tanggal_masuk->lte($day))->count();
            $rincian = self::rincianTidakHadir($tidakHadir[$day->toDateString()] ?? []);
            $jumlahTidakHadir = min($rincian->sum('count'), $total);

            return [
                'day' => $day,
                'total' => $total,
                'hadir' => $total - $jumlahTidakHadir,
                'tidakHadir' => $jumlahTidakHadir,
                'rincian' => $rincian->all(),
            ];
        })->values();
    }

    /**
     * Batas atas sumbu Y yang "bulat" dengan 4 interval (mis. nilai terbesar 352 -> 0, 100, 200, 300, 400).
     *
     * @return array{max: int, ticks: list<int>}
     */
    private static function skalaSumbu(int $nilaiTerbesar): array
    {
        $kasar = max($nilaiTerbesar, 4) / 4;
        $besaran = 10 ** floor(log10($kasar));
        $langkah = (int) collect([1, 2, 2.5, 5, 10])
            ->map(fn (float|int $kali) => $kali * $besaran)
            ->first(fn (float|int $kandidat) => $kandidat >= $kasar && floor($kandidat) == $kandidat, ceil($kasar));

        return ['max' => $langkah * 4, 'ticks' => array_map(fn (int $index) => $langkah * $index, range(0, 4))];
    }

    /**
     * Path SVG garis melengkung (kubik monoton) melewati semua titik; lengkungnya tidak melampaui titik di sekitarnya
     * (garis yang bernilai 0 tidak turun di bawah sumbu).
     *
     * @param  list<array{0: float|int, 1: float|int}>  $titik
     */
    private static function garisHalus(array $titik): string
    {
        $format = fn (float|int $angka) => rtrim(rtrim(number_format($angka, 2, '.', ''), '0'), '.');
        $jumlah = count($titik);
        $path = 'M'.$format($titik[0][0]).' '.$format($titik[0][1]);

        $kemiringan = [];
        for ($i = 0; $i < $jumlah - 1; $i++) {
            $kemiringan[] = ($titik[$i + 1][1] - $titik[$i][1]) / ($titik[$i + 1][0] - $titik[$i][0]);
        }
        $tangen = [];
        for ($i = 0; $i < $jumlah; $i++) {
            $tangen[] = match (true) {
                $i === 0 => $kemiringan[0],
                $i === $jumlah - 1 => $kemiringan[$jumlah - 2],
                $kemiringan[$i - 1] * $kemiringan[$i] <= 0 => 0,
                default => 2 / (1 / $kemiringan[$i - 1] + 1 / $kemiringan[$i]),
            };
        }
        for ($i = 0; $i < $jumlah - 1; $i++) {
            $jarak = ($titik[$i + 1][0] - $titik[$i][0]) / 3;
            $path .= ' C'.$format($titik[$i][0] + $jarak).' '.$format($titik[$i][1] + $tangen[$i] * $jarak)
                .' '.$format($titik[$i + 1][0] - $jarak).' '.$format($titik[$i + 1][1] - $tangen[$i + 1] * $jarak)
                .' '.$format($titik[$i + 1][0]).' '.$format($titik[$i + 1][1]);
        }

        return $path;
    }

    /**
     * @param  array<string, int>  $jumlahPerKategori  kategori KATEGORI_TIDAK_HADIR => jumlah
     * @return Collection<int, array{label: string, tone: string, count: int}>
     */
    private static function rincianTidakHadir(array $jumlahPerKategori): Collection
    {
        return collect(self::KATEGORI_TIDAK_HADIR)
            ->map(fn (array $info, string $kategori) => [...$info, 'count' => (int) ($jumlahPerKategori[$kategori] ?? 0)])
            ->values();
    }

    /**
     * Persen hadir dibulatkan ke bawah 1 desimal (mis. 560/561 = "99,8"), supaya tidak tampil 100% selama masih ada yang tidak hadir.
     */
    public static function persenHadir(int $hadir, int $total): string
    {
        if ($total === 0) {
            return '0';
        }

        $permil = intdiv($hadir * 1000, $total);

        return number_format($permil / 10, $permil % 10 === 0 ? 0 : 1, ',', '.');
    }

    /**
     * Persen sisa dari persenHadir (mis. hadir 99,8 -> tidak hadir 0,2), supaya keduanya selalu berjumlah 100%.
     */
    private static function persenSisa(int $hadir, int $total): string
    {
        if ($total === 0) {
            return '0';
        }

        $permil = 1000 - intdiv($hadir * 1000, $total);

        return number_format($permil / 10, $permil % 10 === 0 ? 0 : 1, ',', '.');
    }

    /**
     * Persen biasa dibulatkan 1 desimal (mis. 1/561 = "0,2").
     */
    private static function persen(int $jumlah, int $total): string
    {
        if ($total === 0) {
            return '0';
        }

        $persen = round($jumlah / $total * 100, 1);

        return number_format($persen, $persen == floor($persen) ? 0 : 1, ',', '.');
    }
}
