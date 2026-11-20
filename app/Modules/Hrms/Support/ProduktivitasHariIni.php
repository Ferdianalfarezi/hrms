<?php

namespace App\Modules\Hrms\Support;

use App\Modules\Hrms\Models\Absensi;
use App\Modules\Hrms\Models\Karyawan;
use App\Modules\Hrms\Models\ShiftRotasi;
use Closure;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Kartu "produktivitas hari ini" di dashboard HRMS: man-hour, keterlambatan, dan kehadiran per shift (dipakai kartu Absensi hari ini).
 * Semua kartu memakai satu potret absensi hari ini untuk karyawan yang boleh dilihat user,
 * sama seperti kartu Absensi hari ini: tidak hadir = kode tidak hadir dari admin, atau tidak absen di mesin dan belum ada keterangan.
 */
class ProduktivitasHariIni
{
    /** Jam kerja normal per karyawan per hari (07.00 - 16.00 dikurangi 1 jam istirahat). */
    public const JAM_KERJA = 8;

    /** Kode yang masuk kartu keterlambatan; karyawannya tetap dihitung hadir. */
    public const KODE_TELAT = ['DT<', 'DT>', 'PC<', 'PC>'];

    /** @var Collection<int, Karyawan> */
    private Collection $karyawans;

    /** @var array<int, string> karyawan_id => kategori Absensi::KATEGORI (belum ada keterangan dihitung Alpa) */
    private array $tidakHadir = [];

    /** @var Collection<int, Absensi> absensi hari ini dengan kode KODE_TELAT */
    private Collection $telat;

    private function __construct(Closure $visible, private Carbon $today)
    {
        $this->karyawans = $visible()->with('department:id,name')
            ->get(['id', 'nik', 'group', 'department_id'])
            ->keyBy('id');

        $absensis = Absensi::whereIn('karyawan_id', $this->karyawans->keys())
            ->whereDate('tanggal', $today->toDateString())
            ->get(['karyawan_id', 'kode', 'menit_terlambat']);

        foreach ($absensis as $absensi) {
            if (in_array($absensi->kode, Absensi::kodeTidakHadir(), true)) {
                $this->tidakHadir[$absensi->karyawan_id] = Absensi::kategori($absensi->kode);
            }
        }
        foreach (array_keys(KomparasiAbsensi::selBelumAdaKeterangan($this->karyawans->values(), $today, $today)) as $karyawanId) {
            // Tidak absen di mesin dan belum ada keterangan = Alpa.
            $this->tidakHadir[$karyawanId] ??= 'Alpa';
        }

        $this->telat = $absensis->whereIn('kode', self::KODE_TELAT)->values();
    }

    public static function build(Closure $visible): array
    {
        $hariIni = new self($visible, Carbon::today());

        return [
            'manHour' => $hariIni->manHour(),
            'keterlambatan' => $hariIni->keterlambatan(),
            'perShift' => $hariIni->perShift(),
        ];
    }

    /**
     * Jam kerja tersedia (karyawan x JAM_KERJA) dikurangi jam yang hilang karena tidak hadir, datang terlambat, dan pulang cepat.
     * WFH tetap bekerja, jadi tidak mengurangi.
     */
    private function manHour(): array
    {
        $tersedia = $this->karyawans->count() * self::JAM_KERJA;
        $jamTidakHadir = collect($this->tidakHadir)->reject(fn (string $kategori) => $kategori === 'WFH')->count() * self::JAM_KERJA;
        $jamTerlambat = $this->menitTerlambat() / 60;
        $jamPulangCepat = $this->menitPulangCepat() / 60;
        $efektif = max($tersedia - $jamTidakHadir - $jamTerlambat - $jamPulangCepat, 0);

        return [
            'efektif' => $efektif,
            'tersedia' => $tersedia,
            'persen' => $tersedia > 0 ? floor($efektif / $tersedia * 1000) / 10 : null,
            'hilang' => [
                ['label' => 'Tidak hadir', 'jam' => $jamTidakHadir, 'tone' => 'danger'],
                ['label' => 'Terlambat', 'jam' => $jamTerlambat, 'tone' => 'warning'],
                ['label' => 'Pulang cepat', 'jam' => $jamPulangCepat, 'tone' => 'primary-soft'],
            ],
        ];
    }

    private function keterlambatan(): array
    {
        $perKode = $this->telat->countBy('kode');
        $kode = Absensi::kodeTampil();

        return [
            'orang' => $this->telat->count(),
            'menit' => $this->menitTerlambat(),
            'rows' => collect(self::KODE_TELAT)->map(fn (string $item) => [
                'kode' => $item,
                'label' => $kode[$item]['label'],
                'count' => (int) ($perKode[$item] ?? 0),
            ])->all(),
            'departemen' => $this->telat
                ->countBy(fn (Absensi $absensi) => $this->karyawans[$absensi->karyawan_id]->department?->name ?? __('Tanpa departemen'))
                ->sortDesc()->take(3)->all(),
        ];
    }

    /**
     * Shift 1 = grup shift pagi + NS, Shift 2 = grup shift malam (menurut rotasi). Tanpa rotasi: per grup apa adanya.
     */
    private function perShift(): array
    {
        $grupPagi = ShiftRotasi::grupPagi($this->today);

        if ($grupPagi === null) {
            $grups = $this->karyawans->pluck('group')->map(fn (?string $grup) => $grup ?: '-')->unique()->sort()->values();

            return array_values(array_filter($this->rekap(
                fn (Karyawan $karyawan) => $karyawan->group ?: '-',
                $grups->mapWithKeys(fn (string $grup) => [$grup => ['label' => $grup === '-' ? __('Tanpa grup') : __('Grup :grup', ['grup' => $grup])]])->all(),
            ), fn (array $row) => $row['total'] > 0));
        }

        $grupMalam = ShiftRotasi::lawan($grupPagi);
        $rows = $this->rekap(
            fn (Karyawan $karyawan) => match ($karyawan->group) {
                $grupPagi, 'NS' => 'pagi',
                $grupMalam => 'malam',
                default => 'lain',
            },
            [
                'pagi' => ['label' => __('Shift 1 · Pagi'), 'sub' => __('Grup :grup + NS · 07.00 - 16.00', ['grup' => $grupPagi])],
                'malam' => ['label' => __('Shift 2 · Malam'), 'sub' => __('Grup :grup · 20.05 - 05.00', ['grup' => $grupMalam])],
                'lain' => ['label' => __('Tanpa grup')],
            ],
        );

        return array_values(array_filter($rows, fn (array $row) => $row['total'] > 0 || $row['kunci'] !== 'lain'));
    }

    /**
     * Kehadiran hari ini per kelompok. $kunci memetakan karyawan ke kunci di $labels; karyawan dengan kunci lain diabaikan.
     *
     * @param  array<string, array{label: string, sub?: string}>  $labels
     * @return list<array{kunci: string, label: string, sub: ?string, total: int, hadir: int, tidakHadir: int, perKategori: array<string, int>, persen: ?string, diAtasTarget: bool}>
     */
    private function rekap(Closure $kunci, array $labels): array
    {
        $anggota = $this->karyawans->groupBy(fn (Karyawan $karyawan) => (string) $kunci($karyawan));

        return collect($labels)->map(function (array $info, string $key) use ($anggota) {
            $karyawans = $anggota->get($key, collect());
            $total = $karyawans->count();
            $perKategori = $karyawans->map(fn (Karyawan $karyawan) => $this->tidakHadir[$karyawan->id] ?? null)->filter()->countBy()->all();
            $tidakHadir = array_sum($perKategori);

            return [
                'kunci' => $key,
                'label' => $info['label'],
                'sub' => $info['sub'] ?? null,
                'total' => $total,
                'hadir' => $total - $tidakHadir,
                'tidakHadir' => $tidakHadir,
                'perKategori' => $perKategori,
                'persen' => $total > 0 ? HrmsDashboard::persenHadir($total - $tidakHadir, $total) : null,
                'diAtasTarget' => $total > 0 && $tidakHadir / $total * 100 > LaporanAbsensiShift::TARGET_PERSEN,
            ];
        })->values()->all();
    }

    private function menitTerlambat(): int
    {
        return $this->totalMenit(fn (Absensi $absensi) => Absensi::isTerlambat($absensi->kode));
    }

    private function menitPulangCepat(): int
    {
        return $this->totalMenit(fn (Absensi $absensi) => Absensi::isPulangCepat($absensi->kode));
    }

    private function totalMenit(Closure $filter): int
    {
        return (int) $this->telat
            ->filter($filter)
            ->sum(fn (Absensi $absensi) => Absensi::menitUntuk($absensi->kode, $absensi->menit_terlambat) ?? 0);
    }
}
