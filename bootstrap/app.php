<?php

use App\Http\Middleware\EnsureModuleAccess;
use App\Http\Middleware\EnsureSuperadmin;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'module' => EnsureModuleAccess::class,
            'superadmin' => EnsureSuperadmin::class,
        ]);

        // Bahasa tampilan (ID / JP) dipilih dari navbar dan disimpan di cookie.
        $middleware->web(append: [SetLocale::class]);

        $middleware->redirectUsersTo(fn () => route('portal'));

        // Pilihan tampilan Kartu / List menu ISO ditulis langsung oleh JavaScript, jadi tidak dienkripsi.
        $middleware->encryptCookies(except: ['iso_view']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Halaman kedaluwarsa (419, token CSRF tidak cocok / sesi habis) di modul mana pun
        // tidak menampilkan error, tapi diarahkan ke halaman login.
        $exceptions->render(function (HttpException $e, Request $request) {
            if ($e->getStatusCode() !== 419) {
                return null;
            }

            $message = __('Sesi Anda telah berakhir. Silakan login kembali.');

            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['message' => $message, 'redirect' => route('login')], 419);
            }

            return redirect()->route('login')->withErrors(['nik' => $message]);
        });

        // Server error (500) saat membuka halaman (mis. pindah menu / ganti filter): kembali ke halaman sebelumnya
        // dengan pesan, bukan menampilkan halaman error. Error tetap tercatat di log. Tidak berlaku untuk JSON / submit form.
        // Kalau tidak ada halaman sebelumnya yang aman (dibuka langsung / di-refresh, atau halaman sebelumnya juga error),
        // diarahkan ke portal. Halaman error biasa hanya tampil kalau portal sendiri yang error.
        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            if ($response->getStatusCode() !== 500
                || ! $request->hasSession()
                || ! $request->isMethod('GET')
                || $request->expectsJson()
                || $request->prefetch()) {
                return $response;
            }

            $portal = route('portal');
            $previous = url()->previous();
            $previousIsUnsafe = $previous === $request->fullUrl() || $request->session()->has('server_error_redirect');
            $target = $previousIsUnsafe ? $portal : $previous;

            if ($target === $request->fullUrl()) {
                return $response;
            }

            $message = __('Terjadi kesalahan di server saat membuka halaman tersebut, Anda dikembalikan ke halaman sebelumnya. Silakan coba lagi.');

            return redirect()->to($target)->with([
                'error' => config('app.debug') ? $message.' ('.class_basename($e).': '.$e->getMessage().')' : $message,
                'server_error_redirect' => true,
            ]);
        });
    })->create();
