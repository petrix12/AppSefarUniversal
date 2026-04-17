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
        // Solo roles 1, 2, 3, 4 pueden sincronizar
        $rolId = auth()->user()->roles[0]->id;
        abort_if(!in_array($rolId, [1, 2, 3, 4]), 403);

        // Vendedores solo pueden sincronizar su propio usuario
        if (in_array($rolId, [2, 3, 4])) {
            abort_if(auth()->id() !== $user->id, 403);
        }

        // Limpiar negocios previos
        Negocio::where('user_id', $user->id)->delete();

        // Sincronizar inmediatamente (sin cola)
        SyncUserDealsJob::dispatchSync($user);

        return back()
            ->with('sync_success', "✅ Sincronización completada para {$user->name}");
    }
}
