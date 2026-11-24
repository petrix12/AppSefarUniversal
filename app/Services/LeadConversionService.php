<?php

namespace App\Services;

use App\Models\CrmIdentityReview;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class LeadConversionService
{
    /**
     * Move one uniquely identified lead into the registered client account.
     * Ambiguous matches stay in the lead queue for Systems review.
     */
    public function convertFor(User $user): int
    {
        if (! Schema::hasTable('leads') || ! $user->hasRole('Cliente')) {
            return 0;
        }

        $result = DB::transaction(function () use ($user): array {
            $client = User::query()->lockForUpdate()->find($user->id);
            if (! $client || ! $client->hasRole('Cliente')) {
                return ['converted' => 0, 'blocked' => false];
            }

            $leads = $this->candidateLeads($client)->lockForUpdate()->limit(3)->get();
            if ($leads->count() > 1) {
                $reason = 'registration_matches_multiple_leads';
                $this->recordRegistrationReview($client, $leads, $reason);
                return ['converted' => 0, 'blocked' => true, 'reason' => $reason];
            }
            if ($leads->isEmpty()) {
                return ['converted' => 0, 'blocked' => false];
            }

            $lead = $leads->first();
            if ($lead->converted_at && (int) $lead->converted_user_id !== (int) $client->id) {
                $reason = 'lead_already_converted_to_another_user';
                $this->recordRegistrationReview($client, $leads, $reason);
                return ['converted' => 0, 'blocked' => true, 'reason' => $reason];
            }

            $hubspotId = trim((string) $lead->hubspot_id);
            if ($hubspotId !== '' && ! ctype_digit($hubspotId)) {
                $reason = 'lead_has_invalid_hubspot_id';
                $this->recordRegistrationReview($client, $leads, $reason);
                return ['converted' => 0, 'blocked' => true, 'reason' => $reason];
            }
            if ($hubspotId !== '' && filled($client->hs_id) && (string) $client->hs_id !== $hubspotId) {
                $reason = 'registration_hubspot_id_conflict';
                $this->recordRegistrationReview($client, $leads, $reason);
                return ['converted' => 0, 'blocked' => true, 'reason' => $reason];
            }
            if ($hubspotId !== '' && $this->hubspotIdHasOtherLocalIdentity($hubspotId, $client, $lead)) {
                $reason = 'registration_hubspot_id_collision';
                $this->recordRegistrationReview($client, $leads, $reason);
                return ['converted' => 0, 'blocked' => true, 'reason' => $reason];
            }
            if ($this->leadHasForeignClientAssociations($lead, $client)) {
                $reason = 'lead_has_conflicting_crm_associations';
                $this->recordRegistrationReview($client, $leads, $reason);
                return ['converted' => 0, 'blocked' => true, 'reason' => $reason];
            }

            $this->copyLeadProfileToClient($client, $lead);
            $this->moveLeadAssociations($lead, $client);
            $lead->delete();

            return ['converted' => 1, 'blocked' => false];
        }, 3);

        if ($result['blocked']) {
            Log::warning('CRM: el registro quedó pendiente por una coincidencia ambigua de lead', [
                'user_id' => $user->id,
                'reason' => $result['reason'] ?? 'identity_review',
            ]);
        }

        if (! $result['blocked'] && $user->hasRole('Cliente')) {
            $fresh = $user->fresh();
            if ($fresh) app(HubspotOutboxService::class)->queue($fresh, array_keys($fresh->getAttributes()));
        }

        return (int) $result['converted'];
    }

    private function candidateLeads(User $client): \Illuminate\Database\Eloquent\Builder
    {
        $query = Lead::query();

        if (filled($client->hs_id)) {
            return $query->where('hubspot_id', (string) $client->hs_id);
        }

        $email = mb_strtolower(trim((string) $client->email));
        $passport = mb_strtolower(trim((string) $client->passport));

        return $query->where(function ($candidates) use ($client, $email, $passport): void {
            $candidates->where('converted_user_id', $client->id);

            if ($email === '' && $passport === '') {
                return;
            }

            $candidates->orWhere(function ($active) use ($email, $passport): void {
                if (Schema::hasColumn('leads', 'converted_at')) {
                    $active->whereNull('converted_at');
                }
                $active->where(function ($match) use ($email, $passport): void {
                    if ($email !== '') {
                        $match->whereRaw('LOWER(TRIM(email)) = ?', [$email]);
                    }
                    if ($passport !== '') {
                        $method = $email !== '' ? 'orWhereRaw' : 'whereRaw';
                        $match->{$method}('LOWER(TRIM(passport)) = ?', [$passport]);
                    }
                });
            });
        });
    }

    private function hubspotIdHasOtherLocalIdentity(string $hubspotId, User $client, Lead $lead): bool
    {
        return User::query()->where('hs_id', $hubspotId)->where('id', '!=', $client->id)->exists()
            || Lead::query()->where('hubspot_id', $hubspotId)->where('id', '!=', $lead->id)->exists();
    }

    private function leadHasForeignClientAssociations(Lead $lead, User $client): bool
    {
        foreach (['crm_activities', 'crm_quotes'] as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'lead_id') || ! Schema::hasColumn($table, 'client_id')) {
                continue;
            }
            if (DB::table($table)->where('lead_id', $lead->id)
                ->whereNotNull('client_id')->where('client_id', '!=', $client->id)->exists()) {
                return true;
            }
        }

        return false;
    }

    private function copyLeadProfileToClient(User $client, Lead $lead): void
    {
        $values = [];
        if (blank($client->hs_id) && filled($lead->hubspot_id)) {
            $values['hs_id'] = (string) $lead->hubspot_id;
        }
        if (blank($client->phone) && filled($lead->telefono) && Schema::hasColumn('users', 'phone')) {
            $values['phone'] = $lead->telefono;
        }
        if (blank($client->passport) && filled($lead->passport) && Schema::hasColumn('users', 'passport')) {
            $values['passport'] = $lead->passport;
        }
        if ((blank($client->nombres) || blank($client->apellidos)) && filled($lead->nombre)) {
            $parts = preg_split('/\s+/', trim((string) $lead->nombre), 2) ?: [];
            if (blank($client->nombres) && filled($parts[0] ?? null)) $values['nombres'] = $parts[0];
            if (blank($client->apellidos) && filled($parts[1] ?? null)) $values['apellidos'] = $parts[1];
        }
        if (blank($client->owner_id) && filled($lead->user_id)
            && User::internalUsers()->whereKey($lead->user_id)->exists()) {
            $values['owner_id'] = $lead->user_id;
        }

        if ($values !== []) {
            HubspotSyncOrigin::fromHubspot(function () use ($client, $values): void {
                $client->forceFill($values)->save();
            });
        }
    }

    private function moveLeadAssociations(Lead $lead, User $client): void
    {
        foreach (['crm_activities', 'crm_quotes'] as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'lead_id') || ! Schema::hasColumn($table, 'client_id')) {
                continue;
            }
            DB::table($table)->where('lead_id', $lead->id)->update([
                'client_id' => $client->id,
                'lead_id' => null,
                'updated_at' => now(),
            ]);
        }
    }

    private function recordRegistrationReview(User $client, \Illuminate\Support\Collection $leads, string $reason): void
    {
        $hubspotIds = $leads->pluck('hubspot_id')->filter(fn ($id) => is_scalar($id) && ctype_digit((string) $id))
            ->map(fn ($id) => (string) $id)->unique()->values();

        if (! Schema::hasTable('crm_identity_reviews') || $hubspotIds->isEmpty()) {
            return;
        }

        foreach ($hubspotIds as $hubspotId) {
            CrmIdentityReview::query()->updateOrCreate([
                'provider' => 'hubspot',
                'object_type' => 'contacts',
                'external_id' => $hubspotId,
            ], [
                'reason_code' => $reason,
                'candidate_user_ids' => [$client->id],
                'candidate_lead_ids' => $leads->where('hubspot_id', $hubspotId)->modelKeys(),
                'matched_fields' => ['registration_email_or_passport'],
                'status' => 'pending',
                'resolution' => null,
                'local_entity_id' => null,
                'reviewed_by_user_id' => null,
                'review_note' => null,
                'reviewed_at' => null,
            ]);
        }
    }
}
