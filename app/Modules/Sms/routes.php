<?php

use App\Modules\Sms\Controllers\CrossPatrolController;
use App\Modules\Sms\Controllers\DailyPatrolController;
use App\Modules\Sms\Controllers\DashboardController;
use App\Modules\Sms\Controllers\KecelakaanController;
use Illuminate\Support\Facades\Route;

Route::get('/', DashboardController::class)->name('dashboard');

// Daily Patrol: tambah/edit/hapus lewat modal di halaman daftar.
Route::resource('daily-patrols', DailyPatrolController::class)->only(['index', 'store', 'update', 'destroy']);

// Cross Patrol: sama seperti Daily Patrol, plus export Excel format "Form Temuan Safety Patrol".
Route::get('cross-patrols/export', [CrossPatrolController::class, 'export'])->name('cross-patrols.export');
// Halaman cetak kartu temuan (A4, maksimal 25 kotak per halaman), ditampilkan di modal preview lewat iframe.
Route::get('cross-patrols/print', [CrossPatrolController::class, 'print'])->name('cross-patrols.print');
Route::resource('cross-patrols', CrossPatrolController::class)->only(['index', 'store', 'update', 'destroy']);

// Laporan kecelakaan: tambah/edit/hapus lewat modal di halaman daftar.
Route::resource('kecelakaan', KecelakaanController::class)->only(['index', 'store', 'update', 'destroy']);
