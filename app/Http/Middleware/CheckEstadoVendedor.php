<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class CheckEstadoVendedor
{
    public function handle($request, Closure $next)
    {
        $user = Auth::user();

        if ($user && $user->hasRole('Proveedor')) {
            if ($user->estado_vendedor !== 'Activo') {
                Auth::logout();

                return redirect()->route('login')
                    ->withErrors([
                        'email' => 'Tu cuenta aún no ha sido activada por el equipo de Sefar.'
                    ]);
            }
        }

        return $next($request);
    }
}
