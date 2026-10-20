<?php

use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PortalController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::get('/', PortalController::class)->name('portal');
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
    Route::get('/notifications/toasts', [NotificationController::class, 'toasts'])->name('notifications.toasts');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');

    Route::middleware('superadmin')->prefix('admin')->name('admin.')->group(function () {
        Route::resource('users', UserController::class)->except('show');
    });

    // Setiap modul punya prefix, nama route, dan pengecekan akses sendiri.
    foreach (['hrms', 'sms'] as $module) {
        Route::middleware("module:{$module}")
            ->prefix($module)
            ->name("{$module}.")
            ->group(app_path('Modules/'.ucfirst($module).'/routes.php'));
    }
});
