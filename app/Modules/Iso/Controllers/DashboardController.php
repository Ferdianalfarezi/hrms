<?php

namespace App\Modules\Iso\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Iso\Support\IsoDashboard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        Gate::authorize('iso-documents');

        return view('iso.dashboard', IsoDashboard::build($request->user()));
    }
}
