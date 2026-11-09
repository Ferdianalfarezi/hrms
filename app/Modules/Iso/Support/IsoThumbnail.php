<?php

namespace App\Modules\Iso\Support;

use App\Modules\Iso\Models\IsoDocument;
use GdImage;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Thumbnail JPG kecil untuk kartu dokumen ISO (gambar & halaman pertama PDF). Dibuat sekali saat pertama diminta lalu disimpan,
 * supaya halaman tidak memuat file aslinya satu per satu. Kalau gagal dibuat, dicatat supaya tidak dicoba terus; kartu memakai ikon.
 */
class IsoThumbnail
{
    private const DISK = 'local';

    /** Lebar maksimal thumbnail (px). */
    private const WIDTH = 400;

    /** Resolusi render PDF: ±400px untuk kertas A4. */
    private const PDF_DPI = 48;

    /**
     * Jalur thumbnail di disk, atau null kalau tidak bisa dibuat.
     */
    public static function for(IsoDocument $document): ?string
    {
        if (! $document->hasThumbnail()) {
            return null;
        }

        $disk = Storage::disk(self::DISK);
        $thumbnail = self::pathFor($document->path);

        if ($disk->exists($thumbnail)) {
            return $thumbnail;
        }
        if ($disk->exists(self::failedMarker($document->path)) || ! $disk->exists($document->path)) {
            return null;
        }

        $jpeg = rescue(fn () => $document->extension === 'pdf'
            ? self::fromPdf($disk->path($document->path))
            : self::fromImage($disk->get($document->path)), null, report: false);

        if ($jpeg === null) {
            $disk->put(self::failedMarker($document->path), '');

            return null;
        }

        $disk->put($thumbnail, $jpeg);

        return $thumbnail;
    }

    /**
     * Hapus thumbnail milik file (dipanggil saat file dihapus / diganti).
     *
     * @param  string|list<string>  $documentPaths
     */
    public static function forget(string|array $documentPaths): void
    {
        $paths = [];
        foreach ((array) $documentPaths as $documentPath) {
            $paths[] = self::pathFor($documentPath);
            $paths[] = self::failedMarker($documentPath);
        }

        Storage::disk(self::DISK)->delete($paths);
    }

    /**
     * Hapus semua catatan "gagal dibuat" supaya dicoba lagi (mis. setelah Ghostscript dipasang).
     */
    public static function forgetFailures(): void
    {
        $disk = Storage::disk(self::DISK);
        $disk->delete(array_filter($disk->files('iso/thumbs'), fn (string $path) => str_ends_with($path, '.none')));
    }

    private static function pathFor(string $documentPath): string
    {
        return 'iso/thumbs/'.md5($documentPath).'.jpg';
    }

    private static function failedMarker(string $documentPath): string
    {
        return 'iso/thumbs/'.md5($documentPath).'.none';
    }

    /**
     * Halaman pertama PDF lewat Ghostscript. -dSAFER mencegah PDF membaca / menulis file lain di server.
     */
    private static function fromPdf(string $pdfPath): ?string
    {
        $output = tempnam(sys_get_temp_dir(), 'isothumb');

        try {
            $result = Process::timeout(30)->run([
                self::ghostscript(), '-q', '-dSAFER', '-dBATCH', '-dNOPAUSE', '-dFirstPage=1', '-dLastPage=1',
                '-sDEVICE=jpeg', '-dJPEGQ=75', '-r'.self::PDF_DPI, '-dTextAlphaBits=4', '-dGraphicsAlphaBits=4',
                '-sOutputFile='.$output, $pdfPath,
            ]);

            $jpeg = $result->successful() && is_file($output) ? file_get_contents($output) : '';

            return $jpeg !== '' && $jpeg !== false ? $jpeg : null;
        } catch (Throwable) {
            return null;
        } finally {
            @unlink($output);
        }
    }

    private static function fromImage(string $contents): ?string
    {
        $source = @imagecreatefromstring($contents);
        if (! $source instanceof GdImage) {
            return null;
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $scale = min(1, self::WIDTH / max($width, 1));
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));

        // Latar putih supaya PNG transparan tidak jadi hitam di JPG.
        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        ob_start();
        imagejpeg($canvas, null, 75);

        return ob_get_clean() ?: null;
    }

    private static function ghostscript(): string
    {
        return config('services.ghostscript.path') ?: (PHP_OS_FAMILY === 'Windows' ? 'gswin64c' : 'gs');
    }
}
