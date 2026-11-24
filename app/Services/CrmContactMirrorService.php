<?php

namespace App\Services;

use App\Models\CrmContactSnapshot;
use App\Models\CrmObjectAssociation;
use App\Models\TlContact;
use App\Models\TlDeal;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CrmContactMirrorService
{
    public function __construct(private CrmMirrorSource $source) {}

    public static function localReadsEnabled(): bool
    {
        return (bool) config('crm_sync.local_reads_enabled')
            && config('crm_sync.processing_enabled')
            && config('crm_sync.hubspot.enabled') && config('crm_sync.teamleader.enabled')
            && config('services.monday.mirror_enabled') && Schema::hasTable('crm_contact_snapshots');
    }

    public function process(string $provider, array $event): void
    {
        $type = (string) ($event['subscriptionType'] ?? $event['type'] ?? '');
        $id = (string) ($event['objectId'] ?? data_get($event, 'subject.id', ''));
        $objectMirrorHandled = $provider === 'hubspot'
            && config('crm_sync.hubspot.object_mirror_enabled')
            && app(CrmObjectMirrorService::class)->processEvent($event);
        if (! $objectMirrorHandled && $provider === 'hubspot' && str_ends_with($type, '.propertyChange')
            && ! CosCrmSyncFields::supportsHubspotProperty($type, (string) ($event['propertyName'] ?? ''))) return;
        if ($provider === 'teamleader' && str_starts_with($type, 'project.')) {
            app(CrmProjectMirrorService::class)->process($event);
            return;
        }
        $contacts = [];
        if ($provider === 'hubspot' && str_ends_with($type, '.merge')) {
            $mergedIds = array_values(array_unique(array_filter(array_merge(
                (array) ($event['mergedObjectIds'] ?? []),
                [(string) ($event['primaryObjectId'] ?? ''), (string) ($event['newObjectId'] ?? '')],
            ))));
            if (str_starts_with($type, 'contact.')) {
                $contacts = $mergedIds;
            } elseif (str_starts_with($type, 'deal.')) {
                $contacts = CrmContactSnapshot::query()->where('provider', 'hubspot')
                    ->where(function ($query) use ($mergedIds) {
                        foreach ($mergedIds as $id) $query->orWhereJsonContains('deal_ids', (string) $id);
                    })->pluck('contact_id')->all();
                foreach ($mergedIds as $id) $contacts = array_merge($contacts, $this->source->dealContacts('hubspot', (string) $id));
            }
        } elseif ($provider === 'hubspot' && str_ends_with($type, '.associationChange')) {
            if (($event['genericWebhook'] ?? false) === true) {
                $subjectType = HubspotWebhookEventNormalizer::objectType($event['objectTypeId'] ?? null);
                $fromType = HubspotWebhookEventNormalizer::objectType($event['fromObjectTypeId'] ?? null);
                $toType = HubspotWebhookEventNormalizer::objectType($event['toObjectTypeId'] ?? null);
                $subjectId = (string) ($event['objectId'] ?? '');
                if (in_array([$fromType, $toType], [['contact', 'deal'], ['deal', 'contact']], true)) {
                    if ($subjectType === 'contact') {
                        $contacts[] = $subjectId;
                    } elseif ($subjectType === 'deal') {
                        $contacts = CrmContactSnapshot::query()->where('provider', 'hubspot')
                            ->whereJsonContains('deal_ids', $subjectId)->pluck('contact_id')->all();
                        $contacts = array_merge($contacts, CrmObjectAssociation::query()->where('provider', 'hubspot')
                            ->where('from_object_type', 'deals')->where('from_external_id', $subjectId)
                            ->where('to_object_type', 'contacts')->where('active', true)
                            ->pluck('to_external_id')->all());
                    }
                }
            } else {
                $association = strtoupper((string) ($event['associationType'] ?? ''));
                if ($association === 'CONTACT_TO_DEAL') $contacts[] = (string) ($event['fromObjectId'] ?? '');
                elseif ($association === 'DEAL_TO_CONTACT') $contacts[] = (string) ($event['toObjectId'] ?? '');
            }
        } elseif (str_starts_with($type, 'contact.') && $id !== '') {
            $contacts[] = $id;
        } elseif (str_starts_with($type, 'deal.') && $id !== '') {
            // Preserve the former customer when a deal moves or is removed.
            $contacts = CrmContactSnapshot::where('provider', $provider)->whereJsonContains('deal_ids', $id)->pluck('contact_id')->all();
            if (! str_ends_with($type, '.deletion') && ! str_ends_with($type, '.deleted')) {
                $contacts = array_merge($contacts, $this->source->dealContacts($provider, $id));
            }
        } elseif ($provider === 'hubspot' && $objectMirrorHandled) {
            // The generic object mirror also stores companies, invoices, quotes,
            // line items, activities and custom CRM objects with no COS projection.
            return;
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
        $contact = $exists ? $this->source->contact($provider, $id) : null;
        $orderedDeals = $deals;
        usort($orderedDeals, fn ($left, $right) => strcmp((string) $left['id'], (string) $right['id']));
        $contentHash = hash('sha256', json_encode($this->canonical([$exists, $contact, $orderedDeals]), JSON_THROW_ON_ERROR));
        $previous = CrmContactSnapshot::where('provider', $provider)->where('contact_id', $id)->first();
        $field = $provider === 'hubspot' ? 'hs_id' : 'tl_id';
        if ($provider === 'hubspot' && $contact !== null) {
            $user = app(HubspotContactLocalSyncService::class)->resolveQualifiedClient($id, $contact, $deals);
        } else {
            $users = User::where($field, $id)->limit(2)->get();
            $user = $users->count() === 1 ? $users->first() : null;
            if ($user && ! $user->hasRole('Cliente')) $user = null;
        }
        $changed = $previous?->content_hash !== $contentHash || $previous?->user_id !== $user?->id;
        $writeSnapshot = fn () => DB::transaction(function () use ($provider, $id, $ids, $contact, $deals, $user, $exists, $contentHash, $changed) {
            if ($provider === 'hubspot') {
                app(CrmObjectMirrorService::class)->storeAssociationSnapshot(
                    'contacts', $id, 'deals', $ids,
                );
            }
            $snapshot = CrmContactSnapshot::firstOrNew(['provider' => $provider, 'contact_id' => $id]);
            $snapshot->fill([
                'user_id' => $user?->id, 'classification' => ! $exists ? 'deleted' : ($user ? 'client' : 'lead'),
                'deal_ids' => $ids, 'contact' => $contact, 'synced_at' => now(),
                'content_hash' => $contentHash,
            ])->save();
            if ($contact === null || ! $changed) return $snapshot;
            if ($provider === 'teamleader') {
                TlContact::fromTeamleader($contact);
                foreach ($deals as $deal) TlDeal::fromTeamleader($deal);
            } elseif ($provider === 'hubspot') {
                $localSync = app(HubspotContactLocalSyncService::class);
                if ($user) {
                    $localSync->syncQualifiedClient($user, $contact);
                    app(HubspotDealLocalSyncService::class)->sync($user, $deals);
                    app(ClientRestrictionService::class)->detectHubspotRetirement($user, $contact, $deals);
                } else {
                    $localSync->syncLead($id, $contact);
                    app(HubspotDealLocalSyncService::class)->syncUnassigned($deals);
                }
            }
            return $snapshot;
        });
        $snapshot = $provider === 'hubspot'
            ? HubspotSyncOrigin::fromHubspot($writeSnapshot)
            : $writeSnapshot();
        if ($user && $changed) {
            // Recalculate locally on the next read; no full external refresh is launched here.
            CrmCosCache::invalidate($user);
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
