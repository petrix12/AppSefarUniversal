<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RedirectCoordinatorWithoutContract
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        // Si no está logueado, no hacemos nada
        if (!$user) {
            return $next($request);
        }

        // Solo aplica a coordinadores
        if (!$user->hasRole('Coord. de Nacionalidad y Genealogía')) {
            return $next($request);
        }

        // Si ya firmó, pasa
        if ((int) $user->contrato === 1) {
            return $next($request);
        }

        // rutas permitidas
        if (
            $request->routeIs('contrato.coordinador.*') ||
            $request->routeIs('logout') ||
            $request->is('livewire/*') ||
            $request->is('api/*')
        ) {
            return $next($request);
        }

        // TODO lo demás → bloqueado
        return redirect()->route('contrato.coordinador.form');
    }
}
