<?php

namespace App\Http\Controllers;

use App\Jobs\SyncUserDealsJob;
use App\Models\Negocio;
use App\Models\User;
use Illuminate\Http\Request;

class UserSyncController extends Controller
{
    public function sync(Request $request, User $user): \Illuminate\Http\RedirectResponse
    {
        // Solo roles autorizados pueden sincronizar/consultar el estatus.
        $rolId = auth()->user()->roles[0]->id;
        abort_if(!in_array($rolId, [1, 2, 3, 4, 5]), 403);

        // Vendedores y clientes solo pueden sincronizar su propio usuario.
        if (in_array($rolId, [2, 3, 4, 5])) {
            abort_if(auth()->id() !== $user->id, 403);
        }

        // Limpiar negocios previos.
        Negocio::where('user_id', $user->id)->delete();

        // Sincronizar inmediatamente (sin cola).
        SyncUserDealsJob::dispatchSync($user);

        $message = $rolId === 5
            ? 'Tu estatus ya muestra la informacion mas reciente.'
            : "Sincronizacion completada para {$user->name}";

        return back()
            ->with('sync_success', $message);
    }
}
