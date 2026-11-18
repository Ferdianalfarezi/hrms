<?php

namespace App\Modules\Hrms\Support;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * QR code sebagai SVG inline (dibuat di server, jadi tetap jalan tanpa internet dan tajam saat dicetak).
 */
class QrCode
{
    public static function svg(string $text, int $size = 120): string
    {
        $svg = (new Writer(new ImageRenderer(new RendererStyle($size, 0), new SvgImageBackEnd)))->writeString($text);

        // Buang deklarasi XML supaya bisa langsung disisipkan ke HTML.
        return trim(preg_replace('/^<\?xml[^>]*\?>/', '', $svg));
    }
}
