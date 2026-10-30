<?php

use App\Http\Middleware\EnsureModuleAccess;
use App\Http\Middleware\EnsureSuperadmin;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
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

        $middleware->redirectUsersTo(fn () => route('portal'));
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

            $message = 'Sesi Anda telah berakhir. Silakan login kembali.';

            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['message' => $message, 'redirect' => route('login')], 419);
            }

            return redirect()->route('login')->withErrors(['nik' => $message]);
        });
    })->create();
