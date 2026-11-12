<?php

namespace App\Http\Controllers;

use App\Models\ClientRestrictionEvent;
use App\Models\ClientRestrictionScan;
use App\Models\ClientRestrictionSync;
use App\Models\User;
use App\Services\ClientRestrictionService;
use App\Services\HubspotService;
use App\Services\TestClientRestrictionScanner;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ClientRestrictionController extends Controller
{
    public function listing(Request $request, ClientRestrictionService $restrictions)
    {
        abort_unless($restrictions->canReview($request->user()), 403);
        $data = $request->validate(['q' => ['nullable', 'string', 'max:200'], 'scan' => ['nullable', 'uuid']]);
        $clients = User::whereHas('accessBlock')->with('accessBlock')
            ->when($data['q'] ?? null, fn ($q, $term) => $q->where(fn ($q) => $q->where('name', 'like', '%'.$term.'%')->orWhere('email', 'like', '%'.$term.'%')))
            ->orderBy('id')->paginate(25)->withQueryString();
        $scan = isset($data['scan']) ? ClientRestrictionScan::whereKey($data['scan'])->where('actor_id', $request->user()->id)->firstOrFail() : null;

        return response()->view('admin.client-restrictions', [
            'clients' => $clients, 'scan' => $scan,
            'syncs' => ClientRestrictionSync::whereIn('user_id', $clients->pluck('id'))->get()->groupBy('user_id'),
            'events' => ClientRestrictionEvent::latest('id')->limit(30)->get(),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function scan(Request $request, TestClientRestrictionScanner $scanner)
    {
        $this->administrator($request);
        $data = $request->validate(['partial' => ['nullable', 'boolean']]);
        $scan = $scanner->preview($request->user(), (bool) ($data['partial'] ?? false));

        return redirect()->route('admin.client-restrictions.index', ['scan' => $scan->id]);
    }

    public function apply(Request $request, ClientRestrictionScan $scan, TestClientRestrictionScanner $scanner)
    {
        $this->administrator($request);
        $data = $request->validate(['ids' => ['required_without:apply_all', 'array', 'min:1'], 'ids.*' => ['integer', 'distinct'],
            'apply_all' => ['sometimes', 'accepted'], 'confirm' => ['accepted']]);
        $ids = $request->boolean('apply_all') ? array_column($scan->candidates, 'id') : $data['ids'];
        $count = $scanner->apply($scan, $request->user(), $ids);

        return redirect()->route('admin.client-restrictions.index')->with('success', $count.' clientes bloqueados. Las marcas externas quedan pendientes de sincronización.');
    }

    public function unblock(Request $request, User $user, ClientRestrictionService $restrictions)
    {
        $this->administrator($request);
        $data = $request->validate(['explanation' => ['required', 'string', 'min:10', 'max:2000']]);
        if ($user->accessBlock?->source === 'hubspot' && filled($user->hs_id)) {
            $state = $this->retirement($user);
            $active = $restrictions->isAffirmative(data_get($state, 'contact.properties.retirado')) ||
                collect($state['deals'])->contains(fn ($deal) => $restrictions->isAffirmative(data_get($deal, 'properties.el_cliente_se_encuentra_retirado')));
            if ($active) {
                throw ValidationException::withMessages(['explanation' => 'HubSpot todavía indica que el cliente está retirado. Resuelve esa situación antes de desbloquearlo.']);
            }
        }
        $restrictions->unblock($user, $request->user(), $data['explanation']);

        return back()->with('success', 'Acceso restaurado y resolución registrada. Marcas externas pendientes de sincronización.');
    }

    public function refresh(Request $request, User $user, ClientRestrictionService $restrictions)
    {
        $this->administrator($request);
        abort_if(blank($user->hs_id), 422, 'Cliente sin ID de HubSpot.');
        $state = $this->retirement($user);
        $restrictions->detectHubspotRetirement($user, $state['contact'], $state['deals']);

        return back()->with('success', 'Estado de retiro comprobado. Los bloqueos existentes requieren resolución manual.');
    }

    private function administrator(Request $request): void
    {
        abort_unless($request->user()?->hasRole('Administrador'), 403);
    }

    private function retirement(User $user): array
    {
        try {
            return app(HubspotService::class)->clientRetirement($user->hs_id);
        } catch (\Throwable $exception) {
            \Log::warning('No se pudo comprobar el retiro del cliente', ['user_id' => $user->id, 'error' => $exception->getMessage()]);
            throw ValidationException::withMessages(['hubspot' => 'No se pudo comprobar HubSpot. El bloqueo se mantiene; revisa la conexión y vuelve a intentarlo.']);
        }
    }
}
