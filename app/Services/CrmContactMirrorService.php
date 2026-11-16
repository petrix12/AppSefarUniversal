<?php

namespace App\Services;

use App\Models\CrmContactSnapshot;
use App\Models\TlContact;
use App\Models\TlDeal;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CrmContactMirrorService
{
    public function __construct(private CrmMirrorSource $source) {}

    public static function localReadsEnabled(): bool
    {
        return (bool) config('crm_sync.local_reads_enabled')
            && config('crm_sync.hubspot.enabled') && config('crm_sync.teamleader.enabled')
            && config('services.monday.mirror_enabled') && Schema::hasTable('crm_contact_snapshots');
    }

    public function process(string $provider, array $event): void
    {
        $type = (string) ($event['subscriptionType'] ?? $event['type'] ?? '');
        $id = (string) ($event['objectId'] ?? data_get($event, 'subject.id', ''));
        $contacts = [];
        if ($provider === 'hubspot' && $type === 'contact.merge') {
            $contacts = array_merge((array) ($event['mergedObjectIds'] ?? []), [(string) ($event['primaryObjectId'] ?? ''), (string) ($event['newObjectId'] ?? '')]);
        } elseif ($provider === 'hubspot' && str_ends_with($type, '.associationChange')) {
            $association = strtoupper((string) ($event['associationType'] ?? ''));
            if ($association === 'CONTACT_TO_DEAL') $contacts[] = (string) ($event['fromObjectId'] ?? '');
            elseif ($association === 'DEAL_TO_CONTACT') $contacts[] = (string) ($event['toObjectId'] ?? '');
        } elseif (str_starts_with($type, 'contact.') && $id !== '') {
            $contacts[] = $id;
        } elseif (str_starts_with($type, 'deal.') && $id !== '') {
            // Preserve the former customer when a deal moves or is removed.
            $contacts = CrmContactSnapshot::where('provider', $provider)->whereJsonContains('deal_ids', $id)->pluck('contact_id')->all();
            if (! str_ends_with($type, '.deletion') && ! str_ends_with($type, '.deleted')) {
                $contacts = array_merge($contacts, $this->source->dealContacts($provider, $id));
            }
        } else {
            // Unknown subscriptions must be visible for review, never acknowledged as synchronized.
            throw new \UnexpectedValueException('Unsupported CRM event type.');
        }
        foreach (array_unique(array_filter($contacts)) as $contactId) {
            // Even deletion events re-read associations: delivery order is not guaranteed.
            $this->syncContact($provider, (string) $contactId);
        }
    }

    public function syncContact(string $provider, string $id): CrmContactSnapshot
    {
        if (! in_array($provider, ['hubspot', 'teamleader'], true) || $id === '') throw new \InvalidArgumentException('Invalid CRM identity.');
        $exists = $this->source->contactExists($provider, $id);
        $deals = $exists ? $this->source->contactDeals($provider, $id) : [];
        $ids = array_values(array_unique(array_map(fn ($deal) => (string) $deal['id'], $deals)));
        sort($ids);
        // Leads are checked for qualification only. Their profile is never imported.
        $contact = $ids !== [] ? $this->source->contact($provider, $id) : null;
        $orderedDeals = $deals;
        usort($orderedDeals, fn ($left, $right) => strcmp((string) $left['id'], (string) $right['id']));
        $contentHash = hash('sha256', json_encode($this->canonical([$exists, $contact, $orderedDeals]), JSON_THROW_ON_ERROR));
        $previous = CrmContactSnapshot::where('provider', $provider)->where('contact_id', $id)->first();
        $field = $provider === 'hubspot' ? 'hs_id' : 'tl_id';
        $users = User::where($field, $id)->limit(2)->get();
        $user = $users->count() === 1 ? $users->first() : null;
        if ($user && ! $user->hasRole('Cliente')) $user = null;
        $changed = $previous?->content_hash !== $contentHash || $previous?->user_id !== $user?->id;
        $snapshot = DB::transaction(function () use ($provider, $id, $ids, $contact, $deals, $user, $exists, $contentHash, $changed) {
            $snapshot = CrmContactSnapshot::firstOrNew(['provider' => $provider, 'contact_id' => $id]);
            $snapshot->fill([
                'user_id' => $user?->id, 'classification' => ! $exists ? 'deleted' : ($ids !== [] ? 'client' : 'lead'),
                'deal_ids' => $ids, 'contact' => $contact, 'synced_at' => now(),
                'content_hash' => $contentHash,
            ])->save();
            if ($contact === null || ! $changed) return $snapshot;
            if ($provider === 'teamleader') {
                TlContact::fromTeamleader($contact);
                foreach ($deals as $deal) TlDeal::fromTeamleader($deal);
            } elseif ($user) {
                app(HubspotDealLocalSyncService::class)->sync($user, $deals);
                app(ClientRestrictionService::class)->detectHubspotRetirement($user, $contact, $deals);
            }
            // Identity, permissions, payments and contracts never come from arbitrary CRM properties.
            if ($user && $provider === 'hubspot') {
                $values = [];
                foreach (['firstname' => 'nombres', 'lastname' => 'apellidos', 'phone' => 'phone'] as $source => $target) {
                    if (array_key_exists($source, $contact['properties'] ?? [])) $values[$target] = $contact['properties'][$source];
                }
                $user->fill($values);
                if ($user->isDirty()) $user->save();
            }
            return $snapshot;
        });
        if ($user && $changed) {
            Cache::forget('hubspot_data_'.$user->hs_id);
            Cache::forget('teamleader_data_'.$user->id);
            Cache::forget('cos.page_refreshed.'.$user->id);
            Cache::forget('cos.portfolio.v2.all');
            Cache::forget('cos.portfolio.v2.'.$user->owner_id);
            // Recalculate locally on the next read; no full external refresh is launched here.
            $user->forceFill(['arraycos_expire' => now()->subSecond()])->save();
        }
        return $snapshot;
    }

    private function canonical(mixed $value): mixed
    {
        if (! is_array($value)) return $value;
        if (! array_is_list($value)) ksort($value);
        return array_map(fn ($item) => $this->canonical($item), $value);
    }
}
