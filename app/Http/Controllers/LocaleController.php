<?php

namespace App\Http\Controllers;

use App\Http\Middleware\SetLocale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cookie;

class LocaleController extends Controller
{
    /**
     * Simpan pilihan bahasa di cookie (bertahan setelah logout) lalu kembali ke halaman sebelumnya.
     */
    public function __invoke(string $locale): RedirectResponse
    {
        abort_unless(array_key_exists($locale, SetLocale::LOCALES), 404);

        Cookie::queue(Cookie::forever(SetLocale::COOKIE, $locale));

        return redirect()->back(fallback: route('portal'));
    }
}
