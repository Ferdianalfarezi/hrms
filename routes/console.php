<?php

use App\Modules\Hrms\Imports\AbsensiBulananImport;
use App\Modules\Iso\Models\IsoDocument;
use App\Modules\Iso\Support\IsoThumbnail;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Carbon;
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

// Samakan absensi satu bulan dengan sheet bulanan di rekap Excel HRD. Tanpa --simpan hanya menampilkan perubahan (dry-run).
Artisan::command('hrms:import-absensi {file : Path file .xlsx / .xlsm} {sheet : Nama sheet, mis. "Nop\'26"} {--bulan= : Bulan YYYY-MM kalau tidak terbaca dari nama sheet} {--simpan : Simpan perubahan ke database} {--hapus : Hapus absensi karyawan di sheet yang selnya kosong (sama persis dengan sheet)}', function () {
    $bulan = $this->option('bulan') ? Carbon::createFromFormat('!Y-m', (string) $this->option('bulan')) : null;
    $simpan = (bool) $this->option('simpan');
    $hapus = (bool) $this->option('hapus');

    try {
        $hasil = (new AbsensiBulananImport)->import((string) $this->argument('file'), (string) $this->argument('sheet'), $simpan, $bulan, $hapus);
    } catch (RuntimeException $exception) {
        $this->error($exception->getMessage());

        return 1;
    }

    $this->info(($simpan ? 'Import' : 'Cek (dry-run)').' absensi '.$hasil['bulan']->translatedFormat('F Y').' dari sheet "'.$this->argument('sheet').'"');
    $this->table(['Sel berkode di sheet', 'Ditambah', 'Diubah', 'Sudah sama', 'Ada di DB, kosong di sheet'.($hapus ? ($simpan ? ' (dihapus)' : ' (akan dihapus)') : ' (tidak dihapus)')], [[
        $hasil['sel'], $hasil['ditambah'], $hasil['diubah'], $hasil['sama'], count($hasil['hanya_di_database']),
    ]]);

    if ($hasil['contoh_diubah'] !== []) {
        $this->line('Kode yang diganti:');
        foreach ($hasil['contoh_diubah'] as $baris) {
            $this->line("  {$baris}");
        }
    }

    if ($hasil['hanya_di_database'] !== []) {
        $this->line('Ada di database, kosong di sheet'.($hapus ? ($simpan ? ' (dihapus):' : ' (akan dihapus):') : ' (pakai --hapus untuk menghapus):'));
        foreach ($hasil['hanya_di_database'] as $baris) {
            $this->line("  {$baris}");
        }
    }

    if ($hasil['nik_tidak_terdaftar'] !== []) {
        $this->warn(count($hasil['nik_tidak_terdaftar']).' NIK belum terdaftar sebagai karyawan, absensinya dilewati: '.implode(', ', $hasil['nik_tidak_terdaftar']));
    }

    if ($hasil['kode_tidak_dikenal'] !== []) {
        $this->warn('Kode tidak dikenal, dilewati: '.implode('; ', $hasil['kode_tidak_dikenal']));
    }

    if ($hasil['menunggu_approval'] !== []) {
        $this->warn('Masih menunggu approval, tidak diubah/dihapus: '.implode('; ', $hasil['menunggu_approval']));
    }

    if (! $simpan) {
        $this->comment('Belum ada yang disimpan. Jalankan ulang dengan --simpan untuk menyimpan.');
    }

    return 0;
})->purpose('Samakan absensi satu bulan dengan sheet rekap Excel HRD');
