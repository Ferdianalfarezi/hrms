<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureModuleAccess
{
    /**
     * Pemakaian di route: ->middleware('module:hrms')
     */
    public function handle(Request $request, Closure $next, string $module): Response
    {
        abort_unless($request->user()?->canAccessApplication($module), 403, 'Anda tidak punya akses ke modul ini.');

        return $next($request);
    }
}
