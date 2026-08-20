<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMcpReadToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->tokenCan('mcp:read')) {
            return response()->json([
                'message' => 'El token no tiene permiso mcp:read.',
            ], 403);
        }

        if ($user->hasRole('Cliente')) {
            return response()->json([
                'message' => 'El MCP no esta disponible para usuarios con rol Cliente.',
            ], 403);
        }

        return $next($request);
    }
}
