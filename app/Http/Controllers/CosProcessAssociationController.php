<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\CosProcessGroupingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class CosProcessAssociationController extends Controller
{
    public function associate(Request $request, User $user, CosProcessGroupingService $groups)
    {
        $actor = $request->user();
        abort_if($actor->hasRole('Cliente'), 403);
        if (! $actor->hasRole('Administrador') && $actor->hasAnyRole(['Ventas', 'Coord. de Nacionalidad y Genealogía'])) {
            abort_unless((int) $user->owner_id === (int) $actor->id, 403);
        }
        $input = $request->validate([
            'mode' => ['required', Rule::in(['automatic', 'manual'])],
            'deal_id' => ['required_if:mode,manual', 'nullable', 'integer'],
            'teamleader_id' => ['required_if:mode,manual', 'nullable', 'string', 'max:255'],
        ]);
        $context = $groups->loadForClient($user);
        if ($input['mode'] === 'manual') {
            $groups->associateManually($user, (int) $input['deal_id'], $input['teamleader_id'], $context);
            $message = 'Proceso agrupado. Sus pagos se muestran una sola vez.';
        } else {
            $count = $groups->associateAutomatically($context);
            $message = $count.' procesos agrupados sin IA. Los pagos repetidos se muestran una sola vez.';
        }
        Cache::forget('cos.portfolio.v1.all');
        if ($user->owner_id) Cache::forget('cos.portfolio.v1.'.$user->owner_id);
        return redirect()->to(route('crud.users.edit', $user).'#mystatus')->with('process_association_success', $message);
    }
}
