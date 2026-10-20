<?php

namespace App\Modules\Hrms\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Hrms\Support\HrmsDashboard;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        return view('hrms.dashboard', HrmsDashboard::build($request->user()));
    }
}
