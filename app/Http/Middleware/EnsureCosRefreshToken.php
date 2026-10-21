<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class EnsureCosRefreshToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $request->bearerToken() || ! ($user?->currentAccessToken() instanceof PersonalAccessToken)) {
            return response()->json(['message' => 'Se requiere un Bearer token de API.'], 401);
        }

        if (! $user->tokenCan('cos:refresh') || $user->hasRole('Cliente')) {
            return response()->json([
                'message' => 'Se requiere un token de usuario interno con permiso cos:refresh.',
            ], 403);
        }

        return $next($request);
    }
}
