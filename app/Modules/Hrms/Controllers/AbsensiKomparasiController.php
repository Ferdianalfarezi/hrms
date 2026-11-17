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

        // Default hari ini, supaya status upload tidak menyesatkan. Setelah upload, redirect membawa tanggal hari kerja file.
        $tanggal = $this->parseDate((string) $request->query('tanggal')) ?? Carbon::today();

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
            'file.required' => __('Pilih file Attendance Report terlebih dahulu.'),
            'file.extensions' => __('File harus berformat .xls atau .xlsx.'),
            'file.max' => __('Ukuran file maksimal 10 MB.'),
        ]);

        $file = $request->file('file');

        try {
            $result = $importer->import($file->getRealPath(), $file->getClientOriginalExtension(), $request->user()->id);
        } catch (RuntimeException $exception) {
            return redirect()->route('hrms.absensi.komparasi')->withErrors(['file' => $exception->getMessage()]);
        }

        $hariKerja = Carbon::createFromFormat('!Y-m-d', $result['tanggal']);
        $diberitahu = KomparasiAbsensi::beritahuAdminDepartemen($hariKerja);

        return redirect()
            ->route('hrms.absensi.komparasi', ['tanggal' => $hariKerja->toDateString()])
            ->with('status', __('Data mesin absen hari kerja :tanggal berhasil diupload: :hadir karyawan hadir, :total karyawan tidak absen', ['tanggal' => $hariKerja->format('d/m/Y'), 'hadir' => $result['hadir'], 'total' => $result['total']])
                .($result['dilewati'] > 0 ? __(', :count Employee Code dilewati karena tidak ada di data karyawan.', ['count' => $result['dilewati']]) : '.')
                .($result['terlambat'] > 0 ? ' '.__(':count karyawan datang terlambat dicatat ke Potongan Absen.', ['count' => $result['terlambat']]) : '')
                .($diberitahu > 0 ? ' '.__('Notifikasi dikirim ke :count Admin Departemen.', ['count' => $diberitahu]) : '')
                .($result['peringatan'] ? " {$result['peringatan']}" : ''));
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
