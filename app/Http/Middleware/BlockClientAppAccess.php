<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BlockClientAppAccess
{
    public function handle(Request $request, Closure $next)
    {
        // The API group can run before auth:sanctum, so check both guards.
        $user = $request->user() ?: Auth::guard('sanctum')->user();

        if (! $user || ! $user->hasRole('Cliente')) {
            return $next($request);
        }

        // Clients can sign out and see the explanation page, but cannot open app routes.
        if ($request->routeIs('client-access-blocked', 'logout')) {
            return $next($request);
        }

        $blocked = $user->hasIndividualClientBlock();
        $globallyDisabled = ! config('app.client_app_access_enabled');

        if (! $blocked && ! $globallyDisabled) {
            return $next($request);
        }

        $message = $blocked
            ? 'El acceso a tu cuenta está bloqueado. Motivo: ' . ($user->clientBlockReasonDescription() ?? 'comunícate con atención al cliente.')
            : 'El acceso a la aplicación está temporalmente restringido para clientes.';

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => $message,
                'support_email' => config('app.client_access_support_email'),
                'support_phones' => config('app.client_access_support_phones', []),
            ], 403);
        }

        return redirect()->route('client-access-blocked');
    }
}
