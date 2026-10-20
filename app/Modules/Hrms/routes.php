<?php

use App\Modules\Hrms\Controllers\AbsensiController;
use App\Modules\Hrms\Controllers\AbsensiDetailController;
use App\Modules\Hrms\Controllers\AbsensiKomparasiController;
use App\Modules\Hrms\Controllers\DashboardController;
use App\Modules\Hrms\Controllers\DepartmentController;
use App\Modules\Hrms\Controllers\KaryawanController;
use Illuminate\Support\Facades\Route;

Route::get('/', DashboardController::class)->name('dashboard');

// Tambah/edit/hapus lewat modal di halaman daftar, jadi tidak ada halaman create/edit terpisah.
Route::resource('departments', DepartmentController::class)->only(['index', 'store', 'update', 'destroy']);

Route::get('karyawans/template', [KaryawanController::class, 'template'])->name('karyawans.template');
Route::post('karyawans/import', [KaryawanController::class, 'import'])->name('karyawans.import');
Route::resource('karyawans', KaryawanController::class)->only(['index', 'store', 'update', 'destroy']);

// Absensi: lihat matriks bulanan, simpan per sel (karyawan + tanggal) lewat fetch JSON.
Route::get('absensi', [AbsensiController::class, 'index'])->name('absensi.index');
Route::post('absensi', [AbsensiController::class, 'store'])->name('absensi.store');
// Admin Departemen: timeline bulanan (matriks) khusus karyawan departemennya.
Route::get('absensi/timeline', [AbsensiController::class, 'timeline'])->name('absensi.timeline');

// Admin Departemen: tambah/edit/hapus lewat tabel CRUD biasa (form + redirect).
Route::post('absensi/entries', [AbsensiController::class, 'storeEntry'])->name('absensi.entries.store');
Route::put('absensi/entries/{absensi}', [AbsensiController::class, 'updateEntry'])->name('absensi.entries.update');
Route::delete('absensi/entries/{absensi}', [AbsensiController::class, 'destroyEntry'])->name('absensi.entries.destroy');

// Laporan detail per shift (khusus Admin HRMS) + pengaturan rotasi grup A/B.
Route::get('absensi/detail', [AbsensiDetailController::class, 'index'])->name('absensi.detail');
Route::get('absensi/detail/export', [AbsensiDetailController::class, 'export'])->name('absensi.detail.export');
Route::post('absensi/rotasi', [AbsensiDetailController::class, 'storeRotasi'])->name('absensi.rotasi');

// Komparasi data mesin absen: upload data mesin + daftar yang belum ada keterangan (khusus Admin HRMS).
Route::get('absensi/komparasi', [AbsensiKomparasiController::class, 'index'])->name('absensi.komparasi');
Route::post('absensi/komparasi', [AbsensiKomparasiController::class, 'store'])->name('absensi.komparasi.store');
