<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class EnsureInternalApiToken
{
    public function handle(Request $request, Closure $next, string $ability): Response
    {
        $user = $request->user();

        if (! $request->bearerToken() || ! ($user?->currentAccessToken() instanceof PersonalAccessToken)) {
            return response()->json(['message' => 'Se requiere un Bearer token de API.'], 401);
        }

        if (! $user->tokenCan($ability) || $user->hasRole('Cliente')) {
            return response()->json([
                'message' => "Se requiere un token de usuario interno con permiso {$ability}.",
            ], 403);
        }

        return $next($request);
    }
}
