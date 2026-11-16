<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessCrmWebhookEvent;
use App\Models\CrmWebhookEvent;
use App\Services\CrmWebhookSignature;
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
                abort_unless(is_string($event['subscriptionType'] ?? null), 422);
                $numericId = fn ($id) => is_scalar($id) && ctype_digit((string) $id) && strlen((string) $id) <= 64;
                if (str_ends_with($event['subscriptionType'], '.associationChange')) {
                    abort_unless($numericId($event['fromObjectId'] ?? null) && $numericId($event['toObjectId'] ?? null), 422);
                } elseif ($event['subscriptionType'] === 'contact.merge') {
                    abort_unless($numericId($event['primaryObjectId'] ?? null) && is_array($event['mergedObjectIds'] ?? null), 422);
                    foreach ($event['mergedObjectIds'] as $id) abort_unless($numericId($id), 422);
                } else {
                    abort_unless($numericId($event['objectId'] ?? null), 422);
                }
            }
        }
        unset($event);
        DB::transaction(function () use ($batch, $provider) {
            foreach ($batch as $event) {
                unset($event['attemptNumber']);
                ksort($event);
                $key = hash('sha256', json_encode($event, JSON_THROW_ON_ERROR));
                $payload = $provider === 'hubspot'
                    ? Arr::only($event, ['subscriptionType', 'objectId', 'objectTypeId', 'fromObjectId', 'toObjectId', 'associationType', 'associationRemoved', 'primaryObjectId', 'mergedObjectIds', 'newObjectId', 'occurredAt', 'propertyName', 'changeSource', 'sourceId', 'eventId', 'appId'])
                    : Arr::only($event, ['type', 'timestamp', 'event_id', 'id']) + ['subject' => Arr::only($event['subject'], ['id', 'type'])];
                $delivery = CrmWebhookEvent::firstOrCreate(['provider' => $provider, 'event_key' => $key], ['payload' => $payload]);
                // Retrying a failed delivery also repairs a queue insertion failure.
                if ($delivery->status !== 'completed' && config('crm_sync.processing_enabled')) {
                    ProcessCrmWebhookEvent::dispatch($delivery->id)->afterCommit();
                }
            }
        });
        return response()->json(['accepted' => true], 202);
    }
}
