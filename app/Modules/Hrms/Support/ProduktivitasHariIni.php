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

        $this->tidakHadir = $this->tidakHadirPada($today, $this->karyawans, $absensis);
        $this->telat = $absensis->whereIn('kode', self::KODE_TELAT)->values();
    }

    /**
     * Karyawan yang tidak hadir pada tanggal itu: kode tidak hadir dari admin, atau tidak absen di mesin dan belum ada keterangan (Alpa).
     *
     * @param  Collection<int, Karyawan>  $karyawans
     * @param  ?Collection<int, Absensi>  $absensis  absensi tanggal itu kalau sudah diambil
     * @return array<int, string> karyawan_id => kategori
     */
    private function tidakHadirPada(Carbon $tanggal, Collection $karyawans, ?Collection $absensis = null): array
    {
        $absensis ??= Absensi::whereIn('karyawan_id', $karyawans->keys())
            ->whereDate('tanggal', $tanggal->toDateString())
            ->get(['karyawan_id', 'kode']);

        $tidakHadir = [];
        foreach ($absensis as $absensi) {
            if (in_array($absensi->kode, Absensi::kodeTidakHadir(), true)) {
                $tidakHadir[$absensi->karyawan_id] = Absensi::kategori($absensi->kode);
            }
        }
        foreach (array_keys(KomparasiAbsensi::selBelumAdaKeterangan($karyawans->values(), $tanggal, $tanggal)) as $karyawanId) {
            $tidakHadir[$karyawanId] ??= 'Alpa';
        }

        return $tidakHadir;
    }

    /**
     * Ringkasan hadir / tidak hadir satu hari kerja: Shift 1 pagi tanggal itu + Shift 2 malam sebelumnya (sama seperti kartu per shift).
     *
     * @return array{total: int, tidakHadir: int}
     */
    public static function ringkasanHariKerja(Closure $visible, Carbon $tanggal): array
    {
        $jumlah = self::rekapHariKerja($visible, $tanggal);

        return ['total' => $jumlah['total'], 'tidakHadir' => $jumlah['total'] - $jumlah['hadir']];
    }

    /**
     * Rekap satu hari kerja (gabungan Shift 1 + Shift 2) lengkap dengan rincian kategori tidak hadir.
     *
     * @return array{total: int, hadir: int, perKategori: array<string, int>}
     */
    public static function rekapHariKerja(Closure $visible, Carbon $tanggal): array
    {
        return self::jumlahkanShift((new self($visible, $tanggal))->perShift());
    }

    /**
     * Gabungan beberapa rekap shift jadi satu angka hari kerja.
     *
     * @param  list<array{total: int, hadir: int, perKategori: array<string, int>}>  $perShift
     * @return array{total: int, hadir: int, perKategori: array<string, int>}
     */
    public static function jumlahkanShift(array $perShift): array
    {
        $perKategori = [];
        foreach ($perShift as $shift) {
            foreach ($shift['perKategori'] as $kategori => $jumlah) {
                $perKategori[$kategori] = ($perKategori[$kategori] ?? 0) + $jumlah;
            }
        }

        return [
            'total' => array_sum(array_column($perShift, 'total')),
            'hadir' => array_sum(array_column($perShift, 'hadir')),
            'perKategori' => $perKategori,
        ];
    }

    /**
     * Grup & tanggal yang dihitung untuk satu hari kerja: grup pagi (+ NS) di tanggal itu, grup malam di tanggal shift malamnya.
     * pagi & malam null = rotasi belum diatur (semua karyawan dihitung di tanggal itu).
     *
     * @param  Collection<int, ShiftRotasi>|null  $rotasis  jadwal rotasi yang sudah dimuat, supaya tidak query per hari
     * @return array{tanggal: string, pagi: ?string, malam: ?string, tanggalMalam: string}
     */
    public static function jadwalHariKerja(Carbon $tanggal, ?Collection $rotasis = null): array
    {
        $rotasis ??= ShiftRotasi::orderBy('tanggal_mulai')->get();
        $tanggalMalam = ShiftRotasi::tanggalShiftMalam($tanggal);

        return [
            'tanggal' => $tanggal->toDateString(),
            'pagi' => ShiftRotasi::grupPagi($tanggal, $rotasis),
            'malam' => ShiftRotasi::grupMalam($tanggalMalam, $rotasis),
            'tanggalMalam' => $tanggalMalam->toDateString(),
        ];
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
     * Seperti Laporan Detail: Shift 1 = grup shift pagi + NS hari ini, Shift 2 = grup shift malam kemarin
     * (mis. hari ini 20 Nov: Shift 1 pagi 20 Nov, Shift 2 malam 19 Nov). Tanpa rotasi: per grup apa adanya.
     */
    private function perShift(): array
    {
        $jadwal = self::jadwalHariKerja($this->today);
        $grupPagi = $jadwal['pagi'];
        $kemarin = Carbon::parse($jadwal['tanggalMalam']);
        $grupMalamKemarin = $jadwal['malam'];

        if ($grupPagi === null && $grupMalamKemarin === null) {
            $grups = $this->karyawans->pluck('group')->map(fn (?string $grup) => $grup ?: '-')->unique()->sort()->values();

            return $grups->map(fn (string $grup) => $this->rekap($grup, $grup === '-' ? __('Tanpa grup') : __('Grup :grup', ['grup' => $grup]), null,
                $this->karyawans->filter(fn (Karyawan $karyawan) => ($karyawan->group ?: '-') === $grup)))
                ->filter(fn (array $row) => $row['total'] > 0)->values()->all();
        }

        $isMalam = fn (Karyawan $karyawan) => $grupMalamKemarin !== null && $karyawan->group === $grupMalamKemarin;
        // Shift 1: grup pagi hari ini dan NS. Karyawan tanpa grup tidak dihitung di shift mana pun.
        $isPagi = fn (Karyawan $karyawan) => $karyawan->group === 'NS' || ($grupPagi !== null && $karyawan->group === $grupPagi);
        $pagi = $this->karyawans->filter($isPagi);
        $malam = $this->karyawans->filter($isMalam);

        // Grup yang sama bisa malam kemarin dan pagi hari ini (hari pergantian rotasi), jadi tiap shift punya anggota sendiri.
        return [
            $this->rekap('pagi', __('Shift 1 · Pagi').' · '.$this->today->translatedFormat('d M'),
                __('Grup :grup + NS · 07.00 - 16.00', ['grup' => $grupPagi ?? '-']), $pagi),
            $this->rekap('malam', __('Shift 2 · Malam').' · '.$kemarin->translatedFormat('d M'),
                __('Grup :grup · 20.05 - 05.00', ['grup' => $grupMalamKemarin ?? '-']), $malam, $this->tidakHadirPada($kemarin, $malam)),
        ];
    }

    /**
     * Kehadiran satu kelompok karyawan.
     *
     * @param  Collection<int, Karyawan>  $karyawans
     * @param  ?array<int, string>  $tidakHadir  karyawan_id => kategori; default absensi hari ini
     * @return array{kunci: string, label: string, sub: ?string, total: int, hadir: int, tidakHadir: int, perKategori: array<string, int>, persen: ?string, diAtasTarget: bool}
     */
    private function rekap(string $kunci, string $label, ?string $sub, Collection $karyawans, ?array $tidakHadir = null): array
    {
        $tidakHadir ??= $this->tidakHadir;
        $total = $karyawans->count();
        $perKategori = $karyawans->map(fn (Karyawan $karyawan) => $tidakHadir[$karyawan->id] ?? null)->filter()->countBy()->all();
        $jumlahTidakHadir = array_sum($perKategori);

        return [
            'kunci' => $kunci,
            'label' => $label,
            'sub' => $sub,
            'total' => $total,
            'hadir' => $total - $jumlahTidakHadir,
            'tidakHadir' => $jumlahTidakHadir,
            'perKategori' => $perKategori,
            'persen' => $total > 0 ? HrmsDashboard::persenHadir($total - $jumlahTidakHadir, $total) : null,
            'diAtasTarget' => $total > 0 && $jumlahTidakHadir / $total * 100 > LaporanAbsensiShift::TARGET_PERSEN,
        ];
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
