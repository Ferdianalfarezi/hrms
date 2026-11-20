<?php

namespace App\Modules\Hrms\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Hrms\Support\JapanResumeBulanan;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Throwable;

/**
 * Japan Resume: rekap kehadiran harian (Total, Hadir, Cuti, S+I, rate) per bulan, seperti file HRD "2026 summary.xlsx".
 * Hanya Admin HRMS.
 */
class AbsensiJapanResumeController extends Controller
{
    public function __invoke(Request $request): View
    {
        Gate::authorize('manage-module', 'hrms');

        return view('hrms.absensi.japan-resume', JapanResumeBulanan::build(
            $this->parseMonth((string) $request->query('bulan')) ?? Carbon::today(),
            Carbon::today(),
        ));
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
