<?php

namespace App\Http\Middleware;

use App\Models\RequestAudit;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuditRequests
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        try {
            $user = auth()->user();

            RequestAudit::create([
                'user_id'          => $user?->id,
                'name'             => $user?->name,
                'email'            => $user?->email,
                'is_authenticated' => auth()->check(),
                'method'           => $request->method(),
                'route_name'       => optional($request->route())->getName(),
                'url'              => $request->fullUrl(),
                'path'             => $request->path(),
                'ip_address'       => $request->ip(),
                'user_agent'       => $request->userAgent(),
                'visited_at'       => now(),
            ]);
        } catch (\Throwable $e) {
            // Evita romper la app por un fallo del auditor
            \Log::error('Error guardando auditoría de request', [
                'message' => $e->getMessage(),
            ]);
        }

        return $response;
    }
}
