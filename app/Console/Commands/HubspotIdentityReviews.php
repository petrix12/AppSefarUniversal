<?php

namespace App\Console\Commands;

use App\Models\CrmIdentityReview;
use App\Models\Lead;
use App\Models\Negocio;
use App\Models\Scopes\BlockedClientRecordScope;
use App\Models\User;
use App\Services\HubspotObjectRecordStore;
use App\Services\HubspotSyncOrigin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class HubspotIdentityReviews extends Command
{
    protected $signature = 'crm:hubspot-identity-reviews
        {--resolve= : HubSpot contact ID to resolve}
        {--as= : Resolve as client, lead, or ignore}
        {--local-id= : Existing local user or lead ID}
        {--note= : Short Systems review note}
        {--limit=100 : Maximum pending reviews to display}
        {--write : Apply a reviewed decision locally}';

    protected $description = 'Lists or resolves HubSpot contact identity cases held for Systems review.';

    public function handle(): int
    {
        if (filled($this->option('resolve'))) {
            return $this->resolveReview((string) $this->option('resolve'));
        }

        $limit = max(1, min(500, (int) $this->option('limit')));
        $reviews = CrmIdentityReview::query()->where('provider', 'hubspot')
            ->where('object_type', 'contacts')->where('status', 'pending')
            ->orderBy('id')->limit($limit)->get();

        $this->info('Revisiones de identidad HubSpot pendientes: '.$reviews->count());
        $this->table(
            ['ID', 'HubSpot contact ID', 'Motivo', 'Usuarios candidatos', 'Leads candidatos', 'Campos coincidentes'],
            $reviews->map(fn (CrmIdentityReview $review) => [
                $review->id,
                $review->external_id,
                $review->reason_code,
                implode(',', $review->candidate_user_ids ?? []),
                implode(',', $review->candidate_lead_ids ?? []),
                implode(',', $review->matched_fields ?? []),
            ])->all(),
        );
        $this->comment('No se muestran correos ni pasaportes. Resolver requiere una decisión explícita de Sistemas con --write.');

        return self::SUCCESS;
    }

    private function resolveReview(string $hubspotId): int
    {
        if (! $this->option('write')) {
            $this->error('La resolución requiere --write.');
            return self::INVALID;
        }

        $resolution = (string) $this->option('as');
        if (! in_array($resolution, ['client', 'lead', 'ignore'], true)) {
            $this->error('--as debe ser client, lead o ignore.');
            return self::INVALID;
        }

        $review = CrmIdentityReview::query()->where('provider', 'hubspot')
            ->where('object_type', 'contacts')->where('external_id', $hubspotId)->first();
        if (! $review || $review->status !== 'pending') {
            $this->error('No existe una revisión pendiente para ese contacto HubSpot.');
            return self::FAILURE;
        }

        try {
            DB::transaction(function () use ($review, $hubspotId, $resolution): void {
                $localId = filled($this->option('local-id')) ? (int) $this->option('local-id') : null;

                if ($resolution === 'client') {
                    $this->assignClient($hubspotId, $localId);
                } elseif ($resolution === 'lead') {
                    $localId = HubspotSyncOrigin::fromHubspot(fn () => $this->assignLead($hubspotId, $localId));
                }

                $review->forceFill([
                    'status' => 'resolved',
                    'resolution' => $resolution,
                    'local_entity_id' => $resolution === 'ignore' ? null : $localId,
                    'reviewed_by_user_id' => auth()->id(),
                    'review_note' => $this->option('note'),
                    'reviewed_at' => now(),
                ])->save();
            }, 3);
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());
            return self::FAILURE;
        }

        $this->info("Revisión {$hubspotId} resuelta como {$resolution}. La decisión se aplicará en la siguiente sincronización del contacto.");
        return self::SUCCESS;
    }

    private function assignClient(string $hubspotId, ?int $localId): void
    {
        if (! $localId) {
            throw new \InvalidArgumentException('--local-id es obligatorio para asignar un representado.');
        }

        $user = User::query()->lockForUpdate()->find($localId);
        if (! $user || ! $user->hasRole('Cliente')) {
            throw new \InvalidArgumentException('El usuario elegido no tiene rol Representado.');
        }
        if (! Negocio::query()->withoutGlobalScope(BlockedClientRecordScope::class)->where('user_id', $user->id)->exists()) {
            throw new \InvalidArgumentException('Solo se pueden vincular representados con al menos un negocio local.');
        }
        if (filled($user->hs_id) && (string) $user->hs_id !== $hubspotId) {
            throw new \InvalidArgumentException('El representado ya está vinculado a otro contacto de HubSpot.');
        }
        if (User::query()->where('hs_id', $hubspotId)->where('id', '!=', $user->id)->exists()
            || Lead::query()->where('hubspot_id', $hubspotId)->exists()) {
            throw new \InvalidArgumentException('El ID de HubSpot ya está asignado a otro registro local.');
        }

        if ((string) $user->hs_id !== $hubspotId) {
            HubspotSyncOrigin::fromHubspot(function () use ($user, $hubspotId): void {
                $user->forceFill(['hs_id' => $hubspotId])->save();
            });
        }
    }

    private function assignLead(string $hubspotId, ?int $localId): int
    {
        if (User::query()->where('hs_id', $hubspotId)->limit(2)->count() > 1) {
            throw new \InvalidArgumentException('El ID de HubSpot está repetido en varios usuarios; resuelve primero ese cruce.');
        }

        $mirror = app(HubspotObjectRecordStore::class)->find('hubspot', 'contacts', $hubspotId);
        if (! $mirror) {
            throw new \InvalidArgumentException('Falta el snapshot del contacto HubSpot; no se puede crear el Lead.');
        }

        $lead = $localId ? Lead::query()->lockForUpdate()->find($localId) : Lead::query()->where('hubspot_id', $hubspotId)->first();
        if ($lead && filled($lead->hubspot_id) && (string) $lead->hubspot_id !== $hubspotId) {
            throw new \InvalidArgumentException('El Lead elegido ya está vinculado a otro ID de HubSpot.');
        }
        if (Lead::query()->where('hubspot_id', $hubspotId)->when($lead, fn ($query) => $query->where('id', '!=', $lead->id))->exists()) {
            throw new \InvalidArgumentException('El ID de HubSpot ya está vinculado a otro Lead.');
        }

        $properties = $mirror->properties ?? [];
        $name = trim(implode(' ', array_filter([
            trim((string) data_get($properties, 'firstname', '')),
            trim((string) data_get($properties, 'lastname', '')),
        ])));
        $lead ??= new Lead();
        $lead->forceFill([
            'hubspot_id' => $hubspotId,
            'nombre' => $name ?: (trim((string) data_get($properties, 'email', '')) ?: 'Contacto HubSpot '.$hubspotId),
            'email' => data_get($properties, 'email'),
            'telefono' => data_get($properties, 'phone'),
            'passport' => data_get($properties, 'numero_de_pasaporte'),
            'origen' => $lead->origen ?: 'HubSpot',
            'etapa' => $lead->etapa ?: Lead::STAGE_NEW,
            'estado' => $lead->estado ?: Lead::STATUS_OPEN,
        ])->save();

        return (int) $lead->id;
    }
}
