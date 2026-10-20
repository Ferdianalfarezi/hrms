<?php

namespace App\Modules\Hrms\Support;

use App\Models\Application;
use App\Models\User;
use App\Modules\Hrms\Models\Absensi;
use App\Modules\Hrms\Models\AbsensiMesin;
use App\Modules\Hrms\Models\Karyawan;
use App\Modules\Hrms\Models\ShiftRotasi;
use App\Modules\Hrms\Notifications\AbsensiBelumAdaKeterangan;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Karyawan yang tidak absen di mesin tapi belum diberi keterangan oleh Admin Departemen.
 *
 * Hari kerja tanggal X = shift malam tanggal X-1 + shift pagi tanggal X (sama seperti laporan Detail).
 * Jadi untuk grup yang shift malam pada X-1, keterangan admin dicari di tanggal X-1; karyawan lain di tanggal X.
 */
class KomparasiAbsensi
{
    /**
     * @return Collection<int, array{karyawan: Karyawan, tanggalLaporan: Carbon}>
     */
    public static function belumAdaKeterangan(Carbon $tanggal): Collection
    {
        $rotasis = ShiftRotasi::orderBy('tanggal_mulai')->get();

        // NIK di mesin yang tidak ada di data karyawan diabaikan.
        $karyawans = Karyawan::with('department')
            ->whereIn('nik', AbsensiMesin::whereDate('tanggal', $tanggal->toDateString())->select('nik'))
            ->orderByRaw('LENGTH(nik)')->orderBy('nik')
            ->get();

        $sudahDiisi = self::sudahDiisi($karyawans->pluck('id')->all(), $tanggal->copy()->subDay(), $tanggal);

        return $karyawans
            ->map(fn (Karyawan $karyawan) => [
                'karyawan' => $karyawan,
                'tanggalLaporan' => self::tanggalLaporan($tanggal, $karyawan->group, $rotasis),
            ])
            ->reject(fn (array $item) => isset($sudahDiisi[$item['karyawan']->id][$item['tanggalLaporan']->toDateString()]))
            ->values();
    }

    /**
     * Kirim notifikasi ke Admin Departemen yang karyawannya belum ada keterangan pada tanggal data mesin ini.
     * Notifikasi lama untuk tanggal yang sama yang belum ditutup diganti (upload ulang = data terbaru).
     *
     * @return int Jumlah Admin Departemen yang diberi notifikasi.
     */
    public static function beritahuAdminDepartemen(Carbon $tanggal): int
    {
        $type = (new AbsensiBelumAdaKeterangan($tanggal, []))->databaseType(new User);
        DatabaseNotification::where('type', $type)->whereNull('read_at')->where('data->tanggal', $tanggal->toDateString())->delete();

        $application = Application::where('code', 'hrms')->first();
        $role = $application?->roles()->where('code', 'dept_admin')->first();
        if (! $role) {
            return 0;
        }

        $dikirim = 0;
        $perDepartemen = self::belumAdaKeterangan($tanggal)
            ->filter(fn (array $item) => $item['karyawan']->department_id !== null)
            ->groupBy(fn (array $item) => $item['karyawan']->department_id);

        foreach ($perDepartemen as $departmentId => $items) {
            $admins = User::where('is_active', true)
                ->whereHas('applications', fn ($query) => $query->whereKey($application->id)
                    ->where('user_app_roles.role_id', $role->id)
                    ->where('user_app_roles.department_id', $departmentId))
                ->get();

            Notification::send($admins, new AbsensiBelumAdaKeterangan($tanggal, $items->map(fn (array $item) => [
                'nik' => $item['karyawan']->nik,
                'nama' => $item['karyawan']->nama,
                'tanggal' => $item['tanggalLaporan']->toDateString(),
            ])->values()->all()));
            $dikirim += $admins->count();
        }

        return $dikirim;
    }

    /**
     * Sel matriks absensi yang belum ada keterangan dalam rentang tanggal: [karyawan_id][Y-m-d] => true.
     *
     * @param  Collection<int, Karyawan>  $karyawans
     * @return array<int, array<string, true>>
     */
    public static function selBelumAdaKeterangan(Collection $karyawans, Carbon $mulai, Carbon $sampai): array
    {
        $rotasis = ShiftRotasi::orderBy('tanggal_mulai')->get();
        $byNik = $karyawans->keyBy('nik');
        $sudahDiisi = self::sudahDiisi($karyawans->pluck('id')->all(), $mulai, $sampai);
        $sel = [];

        // Hari kerja sesudah tanggal terakhir ikut dibaca: shift malamnya jatuh di tanggal terakhir.
        AbsensiMesin::whereIn('nik', $byNik->keys())
            ->where('tanggal', '>=', $mulai->toDateString())
            ->where('tanggal', '<', $sampai->copy()->addDays(2)->toDateString())
            ->get(['tanggal', 'nik'])
            ->each(function (AbsensiMesin $row) use ($byNik, $rotasis, $sudahDiisi, $mulai, $sampai, &$sel) {
                $karyawan = $byNik[$row->nik];
                $tanggal = self::tanggalLaporan($row->tanggal, $karyawan->group, $rotasis);
                $key = $tanggal->toDateString();

                if ($tanggal->between($mulai, $sampai) && ! isset($sudahDiisi[$karyawan->id][$key])) {
                    $sel[$karyawan->id][$key] = true;
                }
            });

        return $sel;
    }

    /**
     * Sel matriks yang menurut mesin karyawannya hadir: hari kerjanya sudah diupload dan NIK-nya tidak ada di data mesin.
     * Dipakai untuk menandai kode tidak hadir dari admin yang kemungkinan salah input. [karyawan_id][Y-m-d] => true.
     *
     * @param  Collection<int, Karyawan>  $karyawans
     * @return array<int, array<string, true>>
     */
    public static function selHadirDiMesin(Collection $karyawans, Carbon $mulai, Carbon $sampai): array
    {
        $rotasis = ShiftRotasi::orderBy('tanggal_mulai')->get();

        // [Y-m-d hari kerja][nik] => true
        $tidakAbsen = [];
        AbsensiMesin::where('tanggal', '>=', $mulai->toDateString())
            ->where('tanggal', '<', $sampai->copy()->addDays(2)->toDateString())
            ->get(['tanggal', 'nik'])
            ->each(function (AbsensiMesin $row) use (&$tidakAbsen) {
                $tidakAbsen[$row->tanggal->toDateString()][$row->nik] = true;
            });

        if ($tidakAbsen === []) {
            return [];
        }

        $sel = [];
        for ($day = $mulai->copy(); $day->lte($sampai); $day->addDay()) {
            // Grup yang shift malam pada tanggal ini melapor untuk hari kerja besok.
            $grupPagi = ShiftRotasi::grupPagi($day, $rotasis);
            $grupMalam = $grupPagi !== null ? ShiftRotasi::lawan($grupPagi) : null;
            $key = $day->toDateString();

            foreach ($karyawans as $karyawan) {
                $hariKerja = $grupMalam !== null && $karyawan->group === $grupMalam ? $day->copy()->addDay()->toDateString() : $key;

                if (isset($tidakAbsen[$hariKerja]) && ! isset($tidakAbsen[$hariKerja][$karyawan->nik])) {
                    $sel[$karyawan->id][$key] = true;
                }
            }
        }

        return $sel;
    }

    /**
     * Tanggal yang diisi Admin Departemen untuk hari kerja ini: grup shift malam = sehari sebelumnya.
     */
    public static function tanggalLaporan(Carbon $hariKerja, ?string $group, Collection $rotasis): Carbon
    {
        $kemarin = $hariKerja->copy()->subDay();
        $grupPagiKemarin = ShiftRotasi::grupPagi($kemarin, $rotasis);

        return $grupPagiKemarin !== null && $group === ShiftRotasi::lawan($grupPagiKemarin) ? $kemarin : $hariKerja->copy();
    }

    /**
     * @param  list<int>  $karyawanIds
     * @return array<int, array<string, true>> [karyawan_id][Y-m-d] => true
     */
    private static function sudahDiisi(array $karyawanIds, Carbon $mulai, Carbon $sampai): array
    {
        $sudahDiisi = [];

        Absensi::whereIn('karyawan_id', $karyawanIds)
            ->where('tanggal', '>=', $mulai->toDateString())
            ->where('tanggal', '<', $sampai->copy()->addDay()->toDateString())
            ->get(['karyawan_id', 'tanggal'])
            ->each(function (Absensi $absensi) use (&$sudahDiisi) {
                $sudahDiisi[$absensi->karyawan_id][$absensi->tanggal->toDateString()] = true;
            });

        return $sudahDiisi;
    }
}
