<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessCrmWebhookEvent;
use App\Models\CrmWebhookEvent;
use App\Services\CosPortfolioBackfill;
use App\Services\CrmWebhookSignature;
use App\Services\HubspotCosFieldSyncService;
use App\Services\HubspotWebhookEventNormalizer;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class CrmWebhookController extends Controller
{
    public function __invoke(Request $request, string $provider, CrmWebhookSignature $signature)
    {
        $signature->verify($request, $provider);
        abort_if(strlen($request->getContent()) > 1048576, 413);
        $batch = $request->isJson() ? $request->json()->all() : $request->except('token');
        if ($provider === 'teamleader') $batch = [$batch];
        abort_unless(array_is_list($batch) && count($batch) <= 100, 422);
        // Validate the complete batch before acknowledging or persisting any item.
        foreach ($batch as &$event) {
            abort_unless(is_array($event), 422);
            $account = $provider === 'hubspot' ? ($event['portalId'] ?? '') : data_get($event, 'account.id', '');
            $configured = (string) config("crm_sync.$provider.".($provider === 'hubspot' ? 'portal_id' : 'account_id'));
            abort_unless($configured !== '' && $configured === (string) $account, 401);
            if ($provider === 'teamleader') {
                abort_unless(is_string($event['type'] ?? null) && is_string(data_get($event, 'subject.id'))
                    && \Illuminate\Support\Str::isUuid(data_get($event, 'subject.id')), 422);
            } else {
                $event['subscriptionType'] ??= $event['eventType'] ?? null;
                $isGeneric = is_string($event['subscriptionType'] ?? null)
                    && str_starts_with($event['subscriptionType'], 'object.');
                if ($isGeneric) {
                    try {
                        $event = HubspotWebhookEventNormalizer::normalize($event);
                    } catch (\InvalidArgumentException) {
                        abort(422);
                    }
                }
                abort_unless(is_string($event['subscriptionType'] ?? null), 422);
                abort_unless(preg_match('/^[a-zA-Z0-9_-]{2,80}\\.(creation|propertyChange|deletion|archived|restore|associationChange|merge)$/', $event['subscriptionType']) === 1, 422);
                $numericId = fn ($id) => is_scalar($id) && ctype_digit((string) $id) && strlen((string) $id) <= 64;
                if (str_ends_with($event['subscriptionType'], '.associationChange')) {
                    if ($isGeneric) {
                        $subjectType = HubspotWebhookEventNormalizer::objectType($event['objectTypeId'] ?? null);
                        $fromType = HubspotWebhookEventNormalizer::objectType($event['fromObjectTypeId'] ?? null);
                        $toType = HubspotWebhookEventNormalizer::objectType($event['toObjectTypeId'] ?? null);
                        abort_unless($numericId($event['objectId'] ?? null) && $subjectType !== null
                            && $fromType !== null && $toType !== null
                            && in_array($subjectType, [$fromType, $toType], true)
                            && is_scalar($event['associationTypeId'] ?? null), 422);
                    } else {
                        abort_unless($numericId($event['fromObjectId'] ?? null) && $numericId($event['toObjectId'] ?? null), 422);
                    }
                } elseif (str_ends_with($event['subscriptionType'], '.merge')) {
                    abort_unless($numericId($event['primaryObjectId'] ?? null) && is_array($event['mergedObjectIds'] ?? null), 422);
                    foreach ($event['mergedObjectIds'] as $id) abort_unless($numericId($id), 422);
                } else {
                    abort_unless($numericId($event['objectId'] ?? null), 422);
                }
            }
        }
        unset($event);
        if ($provider === 'hubspot' && CosPortfolioBackfill::hubspotFieldsOnly()) {
            $batch = array_filter($batch, HubspotCosFieldSyncService::supportsEvent(...));
        }
        $process = CosPortfolioBackfill::allowsCrmProcessing($provider);
        DB::transaction(function () use ($batch, $provider, $process) {
            foreach ($batch as $event) {
                unset($event['attemptNumber']);
                ksort($event);
                $key = hash('sha256', json_encode($event, JSON_THROW_ON_ERROR));
                $payload = $provider === 'hubspot'
                    ? Arr::only($event, ['subscriptionType', 'genericWebhook', 'objectId', 'objectTypeId', 'fromObjectId', 'toObjectId', 'fromObjectType', 'toObjectType', 'fromObjectTypeId', 'toObjectTypeId', 'associationType', 'associationTypeId', 'associationCategory', 'associationRemoved', 'primaryObjectId', 'mergedObjectIds', 'newObjectId', 'occurredAt', 'label', 'propertyName', 'changeSource', 'sourceId', 'eventId', 'appId'])
                    : Arr::only($event, ['type', 'timestamp', 'event_id', 'id']) + ['subject' => Arr::only($event['subject'], ['id', 'type'])];
                $delivery = CrmWebhookEvent::firstOrCreate(['provider' => $provider, 'event_key' => $key], ['payload' => $payload]);
                // Retrying a failed delivery also repairs a queue insertion failure.
                if ($process && ! in_array($delivery->status, ['completed', 'review'], true)) {
                    ProcessCrmWebhookEvent::dispatch($delivery->id)->afterCommit();
                }
            }
        });
        return response()->json(['accepted' => true], 202);
    }
}
