<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ClientRestrictionService;
use App\Services\HubspotService;
use Illuminate\Http\Request;

class ClientRestrictionApiController extends Controller
{
    public function delivery(Request $request)
    {
        $data = $request->validate(['hubspot_id' => ['required_without_all:email,user_id', 'nullable', 'string', 'max:100'],
            'email' => ['required_without_all:hubspot_id,user_id', 'nullable', 'email'],
            'user_id' => ['required_without_all:email,hubspot_id', 'nullable', 'integer']]);
        $query = User::role('Cliente');
        foreach (['hubspot_id' => 'hs_id', 'email' => 'email', 'user_id' => 'id'] as $key => $field) {
            if (filled($data[$key] ?? null)) {
                $query->where($field, $data[$key]);
            }
        }
        $clients = $query->get();
        // An unknown or ambiguous identity must not authorize an external delivery.
        $allowed = $clients->count() === 1 && ! $clients->first()->hasIndividualClientBlock();

        return response()->json(['allowed' => $allowed,
            'reason' => $clients->count() === 1 ? $clients->first()->clientBlockReasonDescription() : 'Identidad no resuelta de forma única.'])
            ->header('Cache-Control', 'no-store');
    }

    public function refresh(Request $request, ClientRestrictionService $restrictions, HubspotService $hubspot)
    {
        $data = $request->validate(['hubspot_id' => ['required', 'string', 'regex:/^\d+$/']]);
        $clients = User::role('Cliente')->where('hs_id', $data['hubspot_id'])->get();
        abort_if($clients->isEmpty(), 404, 'Contacto sin cliente vinculado en la App.');
        try {
            $state = $hubspot->clientRetirement($data['hubspot_id']);
        } catch (\Throwable $exception) {
            \Log::warning('No se pudo verificar un evento de retiro', ['hs_id' => $data['hubspot_id'], 'error' => $exception->getMessage()]);
            return response()->json(['message' => 'No se pudo verificar HubSpot. No se han liberado bloqueos.'], 503);
        }
        foreach ($clients as $client) {
            $restrictions->detectHubspotRetirement($client, $state['contact'], $state['deals']);
        }

        return response()->json(['blocked' => $clients->contains(fn ($client) => $client->hasIndividualClientBlock())]);
    }
}
