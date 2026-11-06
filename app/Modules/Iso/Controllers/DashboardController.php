<?php

namespace App\Modules\Iso\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Iso\Support\IsoDashboard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View|RedirectResponse
    {
        Gate::authorize('iso-documents');

        // Guest tidak punya dashboard: halaman awal modul ISO langsung ke Procedures.
        if (Gate::denies('iso-dashboard')) {
            return redirect()->route('iso.procedures.index');
        }

        return view('iso.dashboard', IsoDashboard::build($request->user()));
    }
}
