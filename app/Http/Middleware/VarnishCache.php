<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VarnishCache
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Si el usuario está autenticado o hay sesión activa
        if (auth()->check() || $request->hasSession()) {
            // NO cachear en Varnish
            $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate, private');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');

            // Importante para Varnish
            $response->headers->set('X-Accel-Buffering', 'no');
        } else {
            // Usuarios no autenticados: cache mínimo
            $response->headers->set('Cache-Control', 'public, max-age=0, must-revalidate');
        }

        // Header Vary para que Varnish distinga por Cookie
        $response->headers->set('Vary', 'Cookie, Accept-Encoding');

        return $response;
    }
}
