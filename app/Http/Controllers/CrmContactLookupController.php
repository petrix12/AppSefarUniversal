<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Http\Request;

class CrmContactLookupController extends Controller
{
    public function __invoke(Request $request)
    {
        $data = $request->validate(['q' => ['required', 'string', 'min:2', 'max:120']]);
        $term = trim($data['q']);
        $like = '%' . addcslashes($term, '%_\\') . '%';

        $leads = Lead::query()
            ->where('estado', Lead::STATUS_OPEN)
            ->where(fn ($q) => $q->where('nombre', 'like', $like)->orWhere('email', 'like', $like)->orWhere('telefono', 'like', $like)->orWhere('passport', 'like', $like))
            ->orderBy('nombre')->limit(12)->get(['id', 'nombre', 'email'])
            ->map(fn (Lead $lead) => [
                'type' => 'lead', 'id' => $lead->id,
                'label' => trim($lead->nombre . ' · ' . ($lead->email ?: 'Lead #' . $lead->id)) . ' (Lead)',
            ]);

        $clients = User::clients()
            ->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('email', 'like', $like)->orWhere('phone', 'like', $like)->orWhere('passport', 'like', $like))
            ->orderBy('name')->limit(12)->get(['id', 'name', 'email'])
            ->map(fn (User $client) => [
                'type' => 'client', 'id' => $client->id,
                'label' => trim($client->name . ' · ' . ($client->email ?: 'Representado #' . $client->id)) . ' (Representado)',
            ]);

        return response()->json($leads->concat($clients)->values());
    }
}
