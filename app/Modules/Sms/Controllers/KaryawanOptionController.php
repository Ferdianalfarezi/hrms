<?php

namespace App\Modules\Sms\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Hrms\Models\Karyawan;
use App\Modules\Sms\Support\KaryawanPicker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Pencarian karyawan untuk isian PIC / PIC Patrol / Patroler. Dipanggil saat user mengetik,
 * jadi halaman Patrol tidak perlu memuat seluruh data karyawan sekaligus.
 */
class KaryawanOptionController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        Gate::authorize('sms-patrol');

        $search = trim((string) $request->query('q'));
        $like = addcslashes($search, '%_\\');
        $exclude = collect(explode(',', (string) $request->query('exclude')))->filter(fn ($id) => ctype_digit($id))->all();

        $karyawans = Karyawan::query()
            ->with('department:id,name')
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('nama', 'like', "%{$like}%")
                ->orWhere('nik', 'like', "{$like}%")))
            ->whereNotIn('id', $exclude)
            ->orderBy('nama')
            ->limit(KaryawanPicker::SEARCH_LIMIT)
            ->get(['id', 'nik', 'nama', 'department_id']);

        return response()->json($karyawans->map(fn (Karyawan $karyawan) => [
            'id' => $karyawan->id,
            'nama' => $karyawan->nama,
            'info' => $karyawan->nik.' · '.($karyawan->department?->name ?? '-'),
        ]));
    }
}
