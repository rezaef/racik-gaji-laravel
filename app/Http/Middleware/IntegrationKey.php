<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Mengamankan endpoint integrasi server-ke-server dengan header X-Integration-Key. */
class IntegrationKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = config('services.attendance_integration.key');

        abort_unless($key && hash_equals($key, (string) $request->header('X-Integration-Key')), 401, 'Kunci integrasi tidak valid.');

        return $next($request);
    }
}
