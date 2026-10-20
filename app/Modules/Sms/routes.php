<?php

use App\Modules\Sms\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', DashboardController::class)->name('dashboard');
