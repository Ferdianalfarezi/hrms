<?php

namespace App\Modules\Hrms\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Hrms\Models\Absensi;
use App\Modules\Hrms\Models\Department;
use App\Modules\Hrms\Models\Karyawan;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Throwable;

/**
 * Rekap potongan gaji karena datang terlambat (DT< / DT>) per karyawan dalam satu bulan.
 * Potongan = kelipatan 30 menit (dibulatkan ke atas) x potongan absen per 30 menit milik karyawan.
 */
class AbsensiPotonganController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $isAdmin = $user->isModuleAdmin('hrms');
        $bulan = $this->parseMonth((string) $request->query('bulan')) ?? Carbon::today()->startOfMonth();
        $department = $isAdmin ? (string) $request->query('department') : '';
        $search = trim((string) $request->query('q'));
        $showAll = $request->boolean('semua');

        $karyawans = Karyawan::query()
            ->visibleTo($user)
            ->with('department')
            ->when($department === 'none', fn ($query) => $query->whereNull('department_id'))
            ->when(ctype_digit($department), fn ($query) => $query->where('department_id', $department))
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('nik', 'like', "%{$search}%")->orWhere('nama', 'like', "%{$search}%")))
            ->orderByRaw('LENGTH(nik)')
            ->orderBy('nik')
            ->get();

        $terlambat = Absensi::query()
            ->with('pendingApproval')
            ->whereIn('karyawan_id', $karyawans->pluck('id'))
            ->whereIn('kode', Absensi::KODE_TERLAMBAT)
            ->whereBetween('tanggal', [$bulan->toDateString(), $bulan->copy()->endOfMonth()->toDateString()])
            ->orderBy('tanggal')
            ->get()
            ->groupBy('karyawan_id');

        $rows = $karyawans->map(function (Karyawan $karyawan) use ($terlambat) {
            $items = ($terlambat[$karyawan->id] ?? collect())->map(fn (Absensi $absensi) => [
                'tanggal' => $absensi->tanggal->translatedFormat('D, d M Y'),
                'kode' => $absensi->kode,
                'menit' => $absensi->menit_terlambat,
                'blok' => Absensi::blokPotongan($absensi->menit_terlambat),
                'potongan' => Absensi::blokPotongan($absensi->menit_terlambat) * $karyawan->potongan_absen,
                'keterangan' => $absensi->keterangan,
                'pending' => $absensi->pendingApproval !== null,
            ])->values();

            return [
                'karyawan' => $karyawan,
                'items' => $items,
                'jumlah' => $items->count(),
                'menit' => $items->sum('menit'),
                'blok' => $items->sum('blok'),
                'potongan' => $items->sum('potongan'),
                // DT lama (sebelum ada kolom menit) yang belum diisi menitnya: belum ikut terhitung.
                'belumDiisi' => $items->whereNull('menit')->count(),
                'pending' => $items->where('pending', true)->count(),
            ];
        });

        if (! $showAll) {
            $rows = $rows->filter(fn (array $row) => $row['jumlah'] > 0)->values();
        }

        $managedDepartmentId = $user->managedDepartmentId();

        return view('hrms.absensi.potongan', [
            'bulan' => $bulan,
            'rows' => $rows,
            'search' => $search,
            'showAll' => $showAll,
            'department' => $department,
            'departments' => $isAdmin ? Department::orderBy('name')->get() : collect(),
            'isAdmin' => $isAdmin,
            'lockedDepartment' => $managedDepartmentId ? Department::find($managedDepartmentId) : null,
            'missingDepartment' => $user->isDepartmentAdmin() && $managedDepartmentId === null,
        ]);
    }

    private function parseMonth(string $value): ?Carbon
    {
        try {
            return preg_match('/^\d{4}-\d{2}$/', $value) ? Carbon::createFromFormat('!Y-m', $value) : null;
        } catch (Throwable) {
            return null;
        }
    }
}
