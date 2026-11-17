<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Bahasa tampilan yang bisa dipilih di navbar. Teks asli aplikasi berbahasa Indonesia,
     * terjemahannya ada di lang/{locale}.json.
     *
     * @var array<string, string>
     */
    public const LOCALES = ['id' => 'ID', 'ja' => 'JP'];

    public const COOKIE = 'locale';

    /**
     * Pakai bahasa dari cookie pilihan user; selain itu bahasa bawaan aplikasi.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->cookie(self::COOKIE);

        if (is_string($locale) && array_key_exists($locale, self::LOCALES)) {
            app()->setLocale($locale);
        }

        Carbon::setLocale(app()->getLocale());

        return $next($request);
    }
}
