<?php

namespace App\Modules\Hrms\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Hrms\Imports\AbsensiMesinImport;
use App\Modules\Hrms\Models\AbsensiMesin;
use App\Modules\Hrms\Support\KomparasiAbsensi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

/**
 * Upload data mesin absen harian (Admin HRMS) dan daftar karyawan yang belum diberi keterangan oleh Admin Departemen.
 */
class AbsensiKomparasiController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('manage-module', 'hrms');

        // Default: tanggal upload terakhir, supaya setelah upload langsung terlihat hasilnya.
        $terakhir = AbsensiMesin::max('tanggal');
        $tanggal = $this->parseDate((string) $request->query('tanggal'))
            ?? ($terakhir ? Carbon::parse($terakhir)->startOfDay() : Carbon::today());

        return view('hrms.absensi.komparasi', [
            'tanggal' => $tanggal,
            'uploaded' => AbsensiMesin::whereDate('tanggal', $tanggal->toDateString())->exists(),
            'belum' => KomparasiAbsensi::belumAdaKeterangan($tanggal),
        ]);
    }

    public function store(Request $request, AbsensiMesinImport $importer): RedirectResponse
    {
        Gate::authorize('manage-module', 'hrms');

        $request->validate([
            'file' => ['required', 'file', 'extensions:xls,xlsx', 'max:10240'],
        ], [
            'file.required' => 'Pilih file export mesin absen terlebih dahulu.',
            'file.extensions' => 'File harus berformat .xls atau .xlsx.',
            'file.max' => 'Ukuran file maksimal 10 MB.',
        ]);

        $file = $request->file('file');

        try {
            $result = $importer->import($file->getRealPath(), $file->getClientOriginalExtension(), $request->user()->id);
        } catch (RuntimeException $exception) {
            return redirect()->route('hrms.absensi.komparasi')->withErrors(['file' => $exception->getMessage()]);
        }

        $diberitahu = collect($result['tanggal'])
            ->sum(fn (string $tanggal) => KomparasiAbsensi::beritahuAdminDepartemen(Carbon::createFromFormat('!Y-m-d', $tanggal)));

        return redirect()
            ->route('hrms.absensi.komparasi', ['tanggal' => end($result['tanggal'])])
            ->with('status', "Data mesin absen berhasil diupload: {$result['total']} karyawan disimpan"
                .($result['dilewati'] > 0 ? ", {$result['dilewati']} AC-No dilewati karena tidak ada di data karyawan." : '.')
                .($diberitahu > 0 ? " Notifikasi dikirim ke {$diberitahu} Admin Departemen." : ''));
    }

    private function parseDate(string $value): ?Carbon
    {
        try {
            return $value !== '' ? Carbon::createFromFormat('!Y-m-d', $value) : null;
        } catch (Throwable) {
            return null;
        }
    }
}
