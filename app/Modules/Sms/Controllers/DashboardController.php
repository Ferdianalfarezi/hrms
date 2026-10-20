<?php

namespace App\Modules\Sms\Controllers;

use App\Http\Controllers\Controller;
use App\Support\ModuleDashboard;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        return view('sms.dashboard', ModuleDashboard::build('sms', $request->user()));
    }
}
