<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class EnsureDeploymentToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $token = $user?->currentAccessToken();

        if (! $user?->hasRole('Administrador') || ! $token instanceof PersonalAccessToken ||
            array_values($token->abilities ?? []) !== ['deploy:run']) {
            return response()->json([
                'message' => 'El despliegue requiere un token exclusivo deploy:run de un usuario con rol Administrador.',
            ], 403);
        }

        return $next($request);
    }
}
