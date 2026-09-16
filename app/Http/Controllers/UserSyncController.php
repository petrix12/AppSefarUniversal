<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ClientCosSnapshotService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class UserSyncController extends Controller
{
    public function sync(Request $request, User $user, ClientCosSnapshotService $snapshots): RedirectResponse
    {
        // Solo roles autorizados pueden sincronizar/consultar el estatus.
        $rolId = auth()->user()->roles[0]->id;
        abort_if(!in_array($rolId, [1, 2, 3, 4, 5]), 403);

        // Vendedores y clientes solo pueden sincronizar su propio usuario.
        if (in_array($rolId, [2, 3, 4, 5])) {
            abort_if(auth()->id() !== $user->id, 403);
        }

        \App\Jobs\RefreshClientCosSnapshot::dispatch($user->id, true);

        $message = $rolId === 5
            ? 'La actualización de tu estatus está en cola. Recarga la página en unos minutos.'
            : "Actualización en cola para {$user->name}. Recarga la ficha en unos minutos.";

        return back()
            ->with('sync_success', $message);
    }
}
