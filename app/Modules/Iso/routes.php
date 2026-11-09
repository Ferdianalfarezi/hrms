<?php

use App\Modules\Iso\Controllers\DashboardController;
use App\Modules\Iso\Controllers\DocumentController;
use App\Modules\Iso\Controllers\FolderController;
use App\Modules\Iso\Controllers\RecipientController;
use App\Modules\Iso\Models\IsoDocument;
use Illuminate\Support\Facades\Route;

Route::get('/', DashboardController::class)->name('dashboard');

// Menu Email: penerima notifikasi pembaruan dokumen (khusus Admin ISO).
Route::patch('emails/{recipient}/status', [RecipientController::class, 'status'])->name('emails.status');
Route::resource('emails', RecipientController::class)->only(['index', 'store', 'update', 'destroy'])->parameters(['emails' => 'recipient']);

// Procedures, Forms & SOP memakai controller yang sama; kategori diisi lewat defaults supaya tiap menu punya nama route sendiri
// (iso.procedures.*, iso.forms.*, iso.sop.*) dan tab menunya menyala sendiri-sendiri.
foreach (array_keys(IsoDocument::KATEGORI) as $kategori) {
    Route::prefix($kategori)->name("{$kategori}.")->controller(DocumentController::class)->group(function () use ($kategori) {
        Route::get('/', 'index')->name('index')->defaults('kategori', $kategori);
        Route::post('/', 'store')->name('store')->defaults('kategori', $kategori);
        Route::put('{document}', 'update')->name('update')->defaults('kategori', $kategori);
        Route::delete('{document}', 'destroy')->name('destroy')->defaults('kategori', $kategori);
        // Toggle aktif / nonaktif (khusus Admin ISO).
        Route::patch('{document}/status', 'status')->name('status')->defaults('kategori', $kategori);
        // Pindah folder lewat drag & drop kartu file.
        Route::patch('{document}/move', 'move')->name('move')->defaults('kategori', $kategori);
        // Kirim email notifikasi pembaruan ke penerima di menu Email (khusus Admin ISO, dokumen aktif).
        Route::post('{document}/notify', 'notify')->name('notify')->defaults('kategori', $kategori);
        // Buka file di browser (PDF/gambar) atau unduh (?download=1 dan jenis file lainnya).
        Route::get('{document}/file', 'file')->name('file')->defaults('kategori', $kategori);

        // Folder (bisa bertingkat); isi folder dibuka lewat ?folder=ID di halaman index.
        Route::post('folders', [FolderController::class, 'store'])->name('folders.store')->defaults('kategori', $kategori);
        Route::put('folders/{folder}', [FolderController::class, 'update'])->name('folders.update')->defaults('kategori', $kategori);
        Route::delete('folders/{folder}', [FolderController::class, 'destroy'])->name('folders.destroy')->defaults('kategori', $kategori);
    });
}
