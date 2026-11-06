<?php

use App\Modules\Iso\Controllers\DashboardController;
use App\Modules\Iso\Controllers\DocumentController;
use App\Modules\Iso\Models\IsoDocument;
use Illuminate\Support\Facades\Route;

Route::get('/', DashboardController::class)->name('dashboard');

// Procedures, Forms & SOP memakai controller yang sama; kategori diisi lewat defaults supaya tiap menu punya nama route sendiri
// (iso.procedures.*, iso.forms.*, iso.sop.*) dan tab menunya menyala sendiri-sendiri.
foreach (array_keys(IsoDocument::KATEGORI) as $kategori) {
    Route::prefix($kategori)->name("{$kategori}.")->controller(DocumentController::class)->group(function () use ($kategori) {
        Route::get('/', 'index')->name('index')->defaults('kategori', $kategori);
        Route::post('/', 'store')->name('store')->defaults('kategori', $kategori);
        Route::put('{document}', 'update')->name('update')->defaults('kategori', $kategori);
        Route::delete('{document}', 'destroy')->name('destroy')->defaults('kategori', $kategori);
        // Buka file di browser (PDF/gambar) atau unduh (?download=1 dan jenis file lainnya).
        Route::get('{document}/file', 'file')->name('file')->defaults('kategori', $kategori);
    });
}
