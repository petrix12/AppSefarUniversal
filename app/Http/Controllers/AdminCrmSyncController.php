<?php

namespace App\Http\Controllers;

use App\Models\CrmContactSnapshot;
use App\Models\CrmWebhookEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Services\TeamleaderWebhookSubscriptionService;

class AdminCrmSyncController extends Controller
{
    public function __invoke(Request $request, TeamleaderWebhookSubscriptionService $subscriptions)
    {
        abort_unless($request->user()->hasRole('Administrador') && ! $request->user()->hasRole('Cliente'), 403);
        $installed = Schema::hasTable('crm_webhook_events') && Schema::hasTable('crm_contact_snapshots');
        $counts = $installed ? CrmContactSnapshot::selectRaw('provider, classification, count(*) as total')->groupBy('provider', 'classification')->get() : collect();
        $events = $installed ? CrmWebhookEvent::orderByDesc('id')->limit(30)->get(['id', 'provider', 'status', 'attempts', 'error_class', 'created_at', 'processed_at']) : collect();
        $teamleaderMissing = $subscriptions->missingConfiguration();
        $hubspotReadiness = [
            'Secreto de aplicación' => filled(config('crm_sync.hubspot.client_secret')),
            'Portal autorizado' => filled(config('crm_sync.hubspot.portal_id')),
        ];
        return response()->view('admin.crm-sync', compact('installed', 'counts', 'events', 'teamleaderMissing', 'hubspotReadiness'))->header('Cache-Control', 'private, no-store');
    }

    public function registerTeamleader(Request $request, TeamleaderWebhookSubscriptionService $subscriptions)
    {
        abort_unless($request->user()->hasRole('Administrador') && ! $request->user()->hasRole('Cliente'), 403);
        if (! Schema::hasTable('crm_webhook_events') || ! Schema::hasTable('crm_contact_snapshots')) {
            return back()->with('crm_error', 'Primero instala las tablas de sincronización CRM.');
        }
        if ($subscriptions->missingConfiguration() !== []) {
            return back()->with('crm_error', 'Falta configurar: '.implode(', ', $subscriptions->missingConfiguration()));
        }
        $lock = Cache::lock('crm.teamleader.register', 120);
        if (! $lock->get()) return back()->with('crm_error', 'Ya hay un registro de suscripciones en curso.');
        try {
            $subscriptions->register(true);
            Log::info('CRM: suscripciones Teamleader verificadas', ['actor_id' => $request->user()->id]);
            return back()->with('crm_success', 'Las doce suscripciones de Teamleader están registradas y verificadas. Falta comprobar una entrega real.');
        } catch (\Throwable $exception) {
            Log::warning('CRM: registro Teamleader fallido', ['exception_class' => $exception::class]);
            return back()->with('crm_error', 'Teamleader no permitió verificar el registro. Revisa la conexión y la configuración; no se modificaron otros destinos.');
        } finally { $lock->release(); }
    }
}
