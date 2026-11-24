<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\Negocio;
use App\Models\CrmObjectMirror;
use App\Models\User;
use App\Models\Scopes\BlockedClientRecordScope;
use Illuminate\Support\Facades\Log;

/** Projects HubSpot contacts into Leads, or updates only already-qualified local clients. */
class HubspotContactLocalSyncService
{
    public function resolveQualifiedClient(string $hubspotId, array $contact, array $deals = []): ?User
    {
        $byHubspotId = User::query()->where('hs_id', $hubspotId)->limit(2)->get();
        if ($byHubspotId->count() > 1) {
            $this->review('HubSpot contact ID matches multiple local users.', $hubspotId);
            return null;
        }
        if ($byHubspotId->count() === 1) {
            $user = $byHubspotId->first();
            return $user->hasRole('Cliente') && ($this->isQualifiedClient($user) || $deals !== []) ? $user : null;
        }

        $properties = $contact['properties'] ?? [];
        $email = $this->normalize($properties['email'] ?? null);
        $passport = $this->normalize($properties['numero_de_pasaporte'] ?? null);
        if ($email === '' && $passport === '') return null;

        $candidates = User::role('Cliente')->where(function ($query) use ($email, $passport): void {
            if ($email !== '') $query->whereRaw('LOWER(TRIM(email)) = ?', [$email]);
            if ($passport !== '') {
                $method = $email !== '' ? 'orWhereRaw' : 'whereRaw';
                $query->{$method}('LOWER(TRIM(passport)) = ?', [$passport]);
            }
        })->limit(3)->get();

        if ($candidates->count() > 1) {
            $this->review('HubSpot contact identity matches multiple clients.', $hubspotId);
            return null;
        }
        $user = $candidates->first();
        if (! $user || (! $this->isQualifiedClient($user) && $deals === [])) return null;
        if (filled($user->hs_id) && (string) $user->hs_id !== $hubspotId) {
            $this->review('Local client already has a different HubSpot contact ID.', $hubspotId);
            return null;
        }

        return $user;
    }

    public function syncLead(string $hubspotId, array $contact): ?Lead
    {
        $properties = $contact['properties'] ?? [];
        $lead = Lead::query()->where('hubspot_id', $hubspotId)->first();
        if (! $lead) {
            $email = $this->normalize($properties['email'] ?? null);
            $passport = $this->normalize($properties['numero_de_pasaporte'] ?? null);
            $candidates = ($email !== '' || $passport !== '')
                ? Lead::query()->where(function ($query) use ($email, $passport): void {
                    if ($email !== '') $query->whereRaw('LOWER(TRIM(email)) = ?', [$email]);
                    if ($passport !== '') {
                        $method = $email !== '' ? 'orWhereRaw' : 'whereRaw';
                        $query->{$method}('LOWER(TRIM(passport)) = ?', [$passport]);
                    }
                })->limit(3)->get()
                : collect();
            if ($candidates->count() > 1) {
                $this->review('HubSpot contact identity matches multiple leads.', $hubspotId);
                return null;
            }
            $lead = $candidates->first();
            if ($lead && filled($lead->hubspot_id) && (string) $lead->hubspot_id !== $hubspotId) {
                $this->review('Local lead already has a different HubSpot contact ID.', $hubspotId);
                return null;
            }
        }

        if ($lead?->converted_at) return $lead;

        $remoteUpdatedAt = $this->remoteUpdatedAt($hubspotId, 'contacts');
        $remoteWins = ! $lead || ! $lead->updated_at || ($remoteUpdatedAt && $remoteUpdatedAt->gt($lead->updated_at));
        $firstName = trim((string) ($properties['firstname'] ?? ''));
        $lastName = trim((string) ($properties['lastname'] ?? ''));
        $name = trim($firstName.' '.$lastName);
        $values = ['hubspot_id' => $hubspotId];
        if ($remoteWins) {
            if ($name !== '') $values['nombre'] = $name;
            elseif (! $lead) $values['nombre'] = trim((string) ($properties['email'] ?? '')) ?: 'Contacto HubSpot '.$hubspotId;
            foreach (['email' => 'email', 'phone' => 'telefono', 'numero_de_pasaporte' => 'passport'] as $source => $target) {
                if (array_key_exists($source, $properties)) $values[$target] = $properties[$source];
            }
            if ($remoteUpdatedAt) $values['updated_at'] = $remoteUpdatedAt;
        }
        if (! $lead) $values += ['origen' => 'HubSpot', 'etapa' => Lead::STAGE_NEW, 'estado' => Lead::STATUS_OPEN];

        if (! $lead) {
            $lead = new Lead();
            $lead->forceFill($values);
            $lead->save();
            return $lead;
        }
        $lead->forceFill($values);
        if ($lead->isDirty()) $lead->save();
        return $lead;
    }

    public function syncQualifiedClient(User $user, array $contact): void
    {
        app(HubspotClientIdentityGuard::class)->assertForUser($user, $contact);
        $properties = $contact['properties'] ?? [];
        $hubspotId = (string) $contact['id'];
        $remoteUpdatedAt = $this->remoteUpdatedAt($hubspotId, 'contacts');
        $remoteWins = ! $user->updated_at || ($remoteUpdatedAt && $remoteUpdatedAt->gt($user->updated_at));
        $values = [];
        if ($remoteWins) {
            foreach (['firstname' => 'nombres', 'lastname' => 'apellidos', 'phone' => 'phone', 'numero_de_pasaporte' => 'passport'] as $source => $target) {
                if (array_key_exists($source, $properties)) $values[$target] = $properties[$source];
            }
            if ($remoteUpdatedAt) $values['updated_at'] = $remoteUpdatedAt;
        }
        $user->fill(array_diff_key($values, ['updated_at' => true]));
        $technicalValues = ['hs_id' => $hubspotId];
        if ($remoteWins && $remoteUpdatedAt) $technicalValues['updated_at'] = $remoteUpdatedAt;
        $user->forceFill($technicalValues);
        if ($user->isDirty()) $user->save();
        app(LeadConversionService::class)->convertFor($user);
    }

    private function isQualifiedClient(User $user): bool
    {
        return $user->hasRole('Cliente') && Negocio::query()
            ->withoutGlobalScope(BlockedClientRecordScope::class)
            ->where('user_id', $user->id)->exists();
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

    private function review(string $reason, string $hubspotId): void
    {
        Log::warning('CRM HubSpot contact requiere revisión de identidad.', [
            'hubspot_contact_id' => $hubspotId,
            'reason' => $reason,
        ]);
    }
}
