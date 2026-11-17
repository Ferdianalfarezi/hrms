<?php

namespace App\Modules\Hrms\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Hrms\Support\LaporanAbsensiShift;
use App\Modules\Hrms\Support\PresentaseAbsensi;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Throwable;

/**
 * Persentase ketidakhadiran bulanan per hari kerja dan per kelompok karyawan. Hanya Admin HRMS.
 */
class AbsensiPresentaseController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('manage-module', 'hrms');

        return view('hrms.absensi.presentase', [
            ...PresentaseAbsensi::build($this->parseMonth((string) $request->query('bulan')) ?? Carbon::today()),
            'kelompok' => PresentaseAbsensi::KELOMPOK,
            'target' => LaporanAbsensiShift::TARGET_PERSEN,
        ]);
    }

    /**
     * Halaman cetak A4 portrait berisi 10 hari kerja terakhir sampai akhir bulan yang dipilih (atau hari ini);
     * dialog print langsung terbuka untuk disimpan sebagai PDF.
     */
    public function pdf(Request $request): View
    {
        Gate::authorize('manage-module', 'hrms');

        $bulan = $this->parseMonth((string) $request->query('bulan')) ?? Carbon::today();

        return view('hrms.absensi.presentase-pdf', [
            ...PresentaseAbsensi::hariKerjaTerakhir($bulan->copy()->endOfMonth(), PresentaseAbsensi::HARI_PDF),
            'kelompok' => PresentaseAbsensi::KELOMPOK,
            'target' => LaporanAbsensiShift::TARGET_PERSEN,
        ]);
    }

    private function parseMonth(string $value): ?Carbon
    {
        try {
            return $value !== '' ? Carbon::createFromFormat('!Y-m', $value) : null;
        } catch (Throwable) {
            return null;
        }
    }
}
