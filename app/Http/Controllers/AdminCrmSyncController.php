<?php

namespace App\Http\Controllers;

use App\Models\CrmContactSnapshot;
use App\Models\CrmWebhookEvent;
use App\Models\User;
use App\Services\CosBackfillReviews;
use App\Services\CosPortfolioBackfill;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
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
        $cosProgress = CosPortfolioBackfill::report();
        return response()->view('admin.crm-sync', compact('installed', 'counts', 'events', 'teamleaderMissing', 'hubspotReadiness', 'cosProgress'))->header('Cache-Control', 'private, no-store');
    }

    public function cosProgress(Request $request)
    {
        abort_unless($request->user()->hasRole('Administrador') && ! $request->user()->hasRole('Cliente'), 403);

        return response()->view('admin.partials.cos-backfill-progress', ['cosProgress' => CosPortfolioBackfill::report()])
            ->header('Cache-Control', 'private, no-store');
    }

    public function cosReviews(Request $request, CosBackfillReviews $reviews)
    {
        abort_unless($request->user()->hasRole('Administrador') && ! $request->user()->hasRole('Cliente'), 403);

        $reasons = [
            'different_contact_id' => 'HubSpot devolvió un ID de contacto distinto',
            'conflict' => 'Conflicto de identidad',
            'missing' => 'Contacto ausente o archivado en HubSpot',
            'missing_deal' => 'Falta un negocio asociado en HubSpot',
            'unlinked' => 'Identificador de HubSpot inválido',
        ];
        $filters = $request->validate([
            'motivo' => ['nullable', Rule::in(array_keys($reasons))],
            'representado' => ['nullable', 'integer', 'min:1'],
        ]);
        $state = CosPortfolioBackfill::state();
        $recorded = collect($reviews->forRun($state));
        $filtered = $recorded->filter(fn (array $case) =>
            (empty($filters['motivo']) || $case['reason'] === $filters['motivo'])
            && (empty($filters['representado']) || $case['user_id'] === (int) $filters['representado'])
        )->sortByDesc('user_id')->values();

        $page = LengthAwarePaginator::resolveCurrentPage();
        $perPage = 25;
        $cases = new LengthAwarePaginator($filtered->forPage($page, $perPage), $filtered->count(), $perPage, $page, [
            'path' => $request->url(),
            'query' => array_filter($filters),
        ]);
        $names = User::query()->clients()->whereIn('id', $cases->pluck('user_id'))->pluck('name', 'id');
        $undetailed = max(0, (int) ($state['hubspot_review'] ?? 0) - $recorded->count());

        return response()->view('admin.cos-backfill-reviews', compact('cases', 'names', 'reasons', 'filters', 'undetailed'))
            ->header('Cache-Control', 'private, no-store');
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
