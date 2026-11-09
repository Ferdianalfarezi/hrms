<?php

use App\Modules\Iso\Models\IsoDocument;
use App\Modules\Iso\Support\IsoThumbnail;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Buat thumbnail kartu dokumen ISO lebih dulu supaya halaman tidak menunggu dibuat satu per satu.
// --ulang: coba lagi dokumen yang sebelumnya gagal (mis. setelah Ghostscript baru dipasang).
Artisan::command('iso:thumbnails {--ulang : Coba lagi thumbnail yang sebelumnya gagal dibuat}', function () {
    if ($this->option('ulang')) {
        IsoThumbnail::forgetFailures();
    }

    $documents = IsoDocument::query()->whereIn('extension', IsoDocument::PREVIEWABLE)->get();
    $failed = 0;

    $this->withProgressBar($documents, function (IsoDocument $document) use (&$failed) {
        if (IsoThumbnail::for($document) === null) {
            $failed++;
        }
    });
    $this->newLine();

    $this->info(($documents->count() - $failed).' thumbnail siap, '.$failed.' gagal (kartu memakai ikon).');
})->purpose('Buat thumbnail kartu dokumen ISO (gambar & PDF)');
