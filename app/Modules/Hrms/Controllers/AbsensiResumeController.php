<?php

namespace App\Modules\Hrms\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Hrms\Support\LaporanAbsensiShift;
use App\Modules\Hrms\Support\ResumeAbsensi;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Throwable;

/**
 * Resume absensi harian per shift dan per plant (MP PSD, PWD, PHD, ALL MP). Hanya Admin HRMS.
 */
class AbsensiResumeController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('manage-module', 'hrms');

        return view('hrms.absensi.resume', [
            ...ResumeAbsensi::build($this->parseDate((string) $request->query('tanggal')) ?? Carbon::today()),
            'target' => LaporanAbsensiShift::TARGET_PERSEN,
        ]);
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
