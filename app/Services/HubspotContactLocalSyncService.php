<?php

namespace App\Services;

use App\Models\CrmIdentityReview;
use App\Models\CrmObjectMirror;
use App\Models\Lead;
use App\Models\Negocio;
use App\Models\Scopes\BlockedClientRecordScope;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Projects contacts by stable HubSpot ID and parks ambiguous identities for Systems review. */
class HubspotContactLocalSyncService
{
    /**
     * @return array{classification: string, user: ?User}
     */
    public function resolveContact(string $hubspotId, array $contact, array $deals = []): array
    {
        $review = CrmIdentityReview::query()
            ->where('provider', 'hubspot')->where('object_type', 'contacts')->where('external_id', $hubspotId)
            ->first();

        if ($review?->status === 'pending') {
            return $this->resolution('review');
        }

        if ($review?->status === 'resolved') {
            $resolved = $this->resolvedIdentity($review, $hubspotId);
            if ($resolved !== null) {
                return $resolved;
            }
        }

        $users = User::query()->where('hs_id', $hubspotId)->limit(2)->get();
        $leads = Lead::query()->where('hubspot_id', $hubspotId)->limit(2)->get();

        if ($users->count() > 1 || $leads->count() > 1) {
            $this->recordReview($hubspotId, 'hubspot_id_collision', $users->modelKeys(), $leads->modelKeys(), ['hs_id']);
            return $this->resolution('review');
        }

        $user = $users->first();
        $lead = $leads->first();
        if ($deals === []) {
            // HubSpot contacts without deals are Leads even when an old App
            // account has the same external ID. Keep that account untouched;
            // a local business linked to it makes the disagreement ambiguous.
            if ($user && (! $user->hasRole('Cliente') || $this->isQualifiedClient($user))) {
                $this->recordReview($hubspotId, 'hubspot_contact_without_deal_has_local_client', [$user->id], $lead ? [$lead->id] : [], ['hs_id']);
                return $this->resolution('review');
            }

            $matches = $this->matchingLocalIdentities($contact, $user?->id, $lead?->id);
            if ($matches['user_ids'] !== [] || $matches['lead_ids'] !== []) {
                $this->recordReview(
                    $hubspotId,
                    'email_or_passport_collision',
                    $matches['user_ids'],
                    $matches['lead_ids'],
                    $matches['fields'],
                );
                return $this->resolution('review');
            }

            return $this->resolution('lead');
        }

        if ($user && $lead) {
            $this->recordReview($hubspotId, 'hubspot_id_collision', [$user->id], [$lead->id], ['hs_id']);
            return $this->resolution('review');
        }

        $matches = $this->matchingLocalIdentities($contact, $user?->id, $lead?->id);
        if ($matches['user_ids'] !== [] || $matches['lead_ids'] !== []) {
            $this->recordReview(
                $hubspotId,
                'email_or_passport_collision',
                $matches['user_ids'],
                $matches['lead_ids'],
                $matches['fields'],
            );
            return $this->resolution('review');
        }

        if ($user && $user->hasRole('Cliente') && $this->isQualifiedClient($user)) {
            return $this->resolution('client', $user);
        }

        $this->recordReview(
            $hubspotId,
            $lead ? 'deal_contact_is_existing_lead' : ($user ? 'client_has_no_local_deal' : 'deal_contact_without_client_id'),
            $user ? [$user->id] : [],
            $lead ? [$lead->id] : [],
            $user || $lead ? ['hs_id'] : [],
        );

        return $this->resolution('review');
    }

    public function syncLead(string $hubspotId, array $contact, ?array $baselineProperties = null): ?Lead
    {
        $sameIdUser = User::query()->where('hs_id', $hubspotId)->first();
        $reviewResolvedAsLead = CrmIdentityReview::query()
            ->where('provider', 'hubspot')->where('object_type', 'contacts')->where('external_id', $hubspotId)
            ->where('status', 'resolved')->where('resolution', 'lead')->exists();
        if ($sameIdUser && (! $sameIdUser->hasRole('Cliente') || $this->isQualifiedClient($sameIdUser)) && ! $reviewResolvedAsLead) {
            $this->recordReview($hubspotId, 'hubspot_contact_without_deal_has_local_client', [$sameIdUser->id], [], ['hs_id']);
            return null;
        }

        $properties = $contact['properties'] ?? [];
        $lead = Lead::query()->where('hubspot_id', $hubspotId)->first();
        if ($lead?->converted_at) {
            return $lead;
        }
        $matches = $this->matchingLocalIdentities($contact, $sameIdUser?->id, $lead?->id);
        if ($matches['user_ids'] !== [] || $matches['lead_ids'] !== []) {
            $this->recordReview(
                $hubspotId,
                'email_or_passport_collision',
                $matches['user_ids'],
                $matches['lead_ids'],
                $matches['fields'],
            );
            return null;
        }

        $remoteUpdatedAt = $this->remoteUpdatedAt($hubspotId, 'contacts');
        $firstName = trim((string) ($properties['firstname'] ?? ''));
        $lastName = trim((string) ($properties['lastname'] ?? ''));
        $name = trim($firstName.' '.$lastName);
        $values = ['hubspot_id' => $hubspotId];
        $localNameParts = $lead ? (preg_split('/\s+/', trim((string) $lead->nombre), 2) ?: []) : [];
        $firstName = $this->shouldApplyRemoteField('firstname', $localNameParts[0] ?? '', $properties, $baselineProperties)
            ? $firstName : (string) ($localNameParts[0] ?? '');
        $lastName = $this->shouldApplyRemoteField('lastname', $localNameParts[1] ?? '', $properties, $baselineProperties)
            ? $lastName : (string) ($localNameParts[1] ?? '');
        if ($name !== '' || $lead) {
            $mergedName = trim($firstName.' '.$lastName);
            if ($mergedName !== '') $values['nombre'] = $mergedName;
        } elseif (! $lead) {
            $values['nombre'] = trim((string) ($properties['email'] ?? '')) ?: 'Contacto HubSpot '.$hubspotId;
        }

        $localProperties = [
            'firstname' => $localNameParts[0] ?? '',
            'lastname' => $localNameParts[1] ?? '',
            'email' => $lead?->email,
            'phone' => $lead?->telefono,
            'numero_de_pasaporte' => $lead?->passport,
        ];
        $this->recordFieldConflicts('lead', $lead, $hubspotId, $localProperties, $baselineProperties, $properties, $remoteUpdatedAt);
        foreach (['email' => 'email', 'phone' => 'telefono', 'numero_de_pasaporte' => 'passport'] as $source => $target) {
            if (array_key_exists($source, $properties)
                && $this->shouldApplyRemoteField($source, $lead?->getAttribute($target), $properties, $baselineProperties)) {
                $values[$target] = $properties[$source];
            }
        }

        if (! $lead) {
            $values += ['origen' => 'HubSpot', 'etapa' => Lead::STAGE_NEW, 'estado' => Lead::STATUS_OPEN];
            $lead = new Lead();
        }

        $lead->forceFill($values);
        if ($lead->isDirty()) {
            $lead->save();
        }

        return $lead;
    }

    public function parkContactEmailConflict(string $hubspotId, array $contact, ?int $excludeUserId = null, ?int $excludeLeadId = null): bool
    {
        $matches = $this->matchingLocalIdentities($contact, $excludeUserId, $excludeLeadId);
        if ($matches['user_ids'] === [] && $matches['lead_ids'] === []) {
            return false;
        }

        $this->recordReview(
            $hubspotId,
            'email_or_passport_collision',
            $matches['user_ids'],
            $matches['lead_ids'],
            $matches['fields'],
            true,
        );

        return true;
    }

    public function syncQualifiedClient(User $user, array $contact, ?array $baselineProperties = null): void
    {
        app(HubspotClientIdentityGuard::class)->assertForUser($user, $contact);
        $properties = $contact['properties'] ?? [];
        $hubspotId = (string) $contact['id'];
        $remoteUpdatedAt = $this->remoteUpdatedAt($hubspotId, 'contacts');
        $values = [];

        $localProperties = [
            'email' => $user->email,
            'firstname' => $user->nombres,
            'lastname' => $user->apellidos,
            'phone' => $user->phone,
            'numero_de_pasaporte' => $user->passport,
        ];
        $this->recordFieldConflicts('client', $user, $hubspotId, $localProperties, $baselineProperties, $properties, $remoteUpdatedAt);

        foreach (['firstname' => 'nombres', 'lastname' => 'apellidos', 'phone' => 'phone', 'numero_de_pasaporte' => 'passport'] as $source => $target) {
            if (array_key_exists($source, $properties)
                && $this->shouldApplyRemoteField($source, $user->getAttribute($target), $properties, $baselineProperties)) {
                $values[$target] = $properties[$source];
            }
        }

        if (array_key_exists('email', $properties) && filled($properties['email'])
            && $this->shouldApplyRemoteField('email', $user->email, $properties, $baselineProperties)
            && ! $this->parkContactEmailConflict($hubspotId, $contact, (int) $user->getKey())) {
            $values['email'] = $properties['email'];
        }

        $user->fill($values);
        $user->forceFill(['hs_id' => $hubspotId]);
        if ($user->isDirty()) {
            $user->save();
        }
        app(LeadConversionService::class)->convertFor($user);
    }

    /** @return array{classification: string, user: ?User} */
    private function resolvedIdentity(CrmIdentityReview $review, string $hubspotId): ?array
    {
        if ($review->resolution === 'ignore') {
            return $this->resolution('ignored');
        }
        if ($review->resolution === 'lead') {
            $users = User::query()->where('hs_id', $hubspotId)->limit(2)->get();
            if ($users->count() > 1) {
                $this->recordReview(
                    $hubspotId,
                    'lead_resolution_conflicts_with_user_id',
                    $users->modelKeys(),
                    [],
                    ['hs_id'],
                    true,
                );
                return $this->resolution('review');
            }
            return $this->resolution('lead');
        }
        if ($review->resolution !== 'client') {
            return null;
        }

        $user = User::query()->find($review->local_entity_id);
        if ($user && $user->hasRole('Cliente') && (string) $user->hs_id === $hubspotId
            && $this->isQualifiedClient($user)) {
            return $this->resolution('client', $user);
        }

        $this->recordReview($hubspotId, 'resolved_client_mapping_changed', $user ? [$user->id] : [], [], ['hs_id'], true);
        return $this->resolution('review');
    }

    /**
     * Return possible local matches for review only; email and passport are never used to link records.
     *
     * @return array{user_ids: array<int>, lead_ids: array<int>, fields: array<int, string>}
     */
    private function matchingLocalIdentities(array $contact, ?int $excludeUserId, ?int $excludeLeadId): array
    {
        $properties = $contact['properties'] ?? [];
        $email = $this->normalize($properties['email'] ?? null);
        $passport = $this->normalize($properties['numero_de_pasaporte'] ?? null);
        $userIds = [];
        $leadIds = [];
        $fields = [];

        if ($email !== '') {
            foreach (['email', 'email_2', 'email_alternativo'] as $column) {
                if (! Schema::hasColumn('users', $column)) {
                    continue;
                }
                $query = DB::table('users')->whereRaw("LOWER(TRIM({$column})) = ?", [$email]);
                if ($excludeUserId) {
                    $query->where('id', '!=', $excludeUserId);
                }
                $ids = $query->pluck('id')->map(fn ($id) => (int) $id)->all();
                if ($ids !== []) {
                    $userIds = array_merge($userIds, $ids);
                    $fields[] = $column;
                }
            }
            $query = DB::table('leads')->whereRaw('LOWER(TRIM(email)) = ?', [$email]);
            if (Schema::hasColumn('leads', 'converted_at')) {
                $query->whereNull('converted_at');
            }
            if ($excludeLeadId) {
                $query->where('id', '!=', $excludeLeadId);
            }
            $ids = $query->pluck('id')->map(fn ($id) => (int) $id)->all();
            if ($ids !== []) {
                $leadIds = array_merge($leadIds, $ids);
                $fields[] = 'lead.email';
            }
        }

        if ($passport !== '') {
            $query = DB::table('users')->whereRaw('LOWER(TRIM(passport)) = ?', [$passport]);
            if ($excludeUserId) {
                $query->where('id', '!=', $excludeUserId);
            }
            $ids = $query->pluck('id')->map(fn ($id) => (int) $id)->all();
            if ($ids !== []) {
                $userIds = array_merge($userIds, $ids);
                $fields[] = 'passport';
            }

            $query = DB::table('leads')->whereRaw('LOWER(TRIM(passport)) = ?', [$passport]);
            if (Schema::hasColumn('leads', 'converted_at')) {
                $query->whereNull('converted_at');
            }
            if ($excludeLeadId) {
                $query->where('id', '!=', $excludeLeadId);
            }
            $ids = $query->pluck('id')->map(fn ($id) => (int) $id)->all();
            if ($ids !== []) {
                $leadIds = array_merge($leadIds, $ids);
                $fields[] = 'lead.passport';
            }
        }

        return [
            'user_ids' => array_values(array_unique($userIds)),
            'lead_ids' => array_values(array_unique($leadIds)),
            'fields' => array_values(array_unique($fields)),
        ];
    }

    private function recordReview(
        string $hubspotId,
        string $reason,
        array $candidateUserIds = [],
        array $candidateLeadIds = [],
        array $matchedFields = [],
        bool $reopenResolved = false,
    ): void {
        $review = CrmIdentityReview::query()->firstOrNew([
            'provider' => 'hubspot',
            'object_type' => 'contacts',
            'external_id' => $hubspotId,
        ]);

        if ($review->status === 'resolved' && ! $reopenResolved) {
            return;
        }

        $review->fill([
            'reason_code' => $reason,
            'candidate_user_ids' => array_values(array_unique(array_map('intval', $candidateUserIds))),
            'candidate_lead_ids' => array_values(array_unique(array_map('intval', $candidateLeadIds))),
            'matched_fields' => array_values(array_unique($matchedFields)),
            'status' => 'pending',
        ])->save();
    }

    /** @return array{classification: string, user: ?User} */
    private function resolution(string $classification, ?User $user = null): array
    {
        return ['classification' => $classification, 'user' => $user];
    }

    private function isQualifiedClient(User $user): bool
    {
        return $user->hasRole('Cliente') && Negocio::query()
            ->withoutGlobalScope(BlockedClientRecordScope::class)
            ->where('user_id', $user->id)->exists();
    }

    private function shouldApplyRemoteField(string $field, mixed $localValue, array $remoteProperties, ?array $baselineProperties): bool
    {
        if (! array_key_exists($field, $remoteProperties)) return false;
        if ($baselineProperties === null || ! array_key_exists($field, $baselineProperties)) return true;

        $baselineValue = $baselineProperties[$field];
        $localChanged = $this->canonical($localValue) !== $this->canonical($baselineValue);
        $remoteChanged = $this->canonical($remoteProperties[$field]) !== $this->canonical($baselineValue);

        // A local-only edit survives this inbound event and remains eligible
        // for its queued outbound update. A remote change wins same-field conflicts.
        return ! $localChanged || $remoteChanged;
    }

    private function recordFieldConflicts(
        string $entityType,
        ?\Illuminate\Database\Eloquent\Model $model,
        string $hubspotId,
        array $localProperties,
        ?array $baselineProperties,
        array $remoteProperties,
        mixed $remoteUpdatedAt,
    ): void {
        if (! $model || $baselineProperties === null || ! Schema::hasTable('crm_sync_conflicts')) return;

        app(CrmSyncConflictRecorder::class)->recordHubspotPrecedence(
            $entityType,
            $model,
            'contacts',
            $hubspotId,
            $localProperties,
            $baselineProperties,
            $remoteProperties,
            $remoteUpdatedAt,
        );
    }

    private function canonical(mixed $value): string
    {
        if ($value === null || $value === '') return 'null';
        if (is_bool($value)) return $value ? 'true' : 'false';
        if (is_array($value)) {
            if (! array_is_list($value)) ksort($value);
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        }
        return (string) $value;
    }

    private function normalize(mixed $value): string
    {
        return is_scalar($value) ? mb_strtolower(trim((string) $value)) : '';
    }

    private function remoteUpdatedAt(string $externalId, string $objectType): ?\Illuminate\Support\Carbon
    {
        $value = CrmObjectMirror::query()->where('provider', 'hubspot')
            ->where('object_type', $objectType)->where('external_id', $externalId)->value('remote_updated_at');
        return $value ? \Illuminate\Support\Carbon::parse($value) : null;
    }
}
