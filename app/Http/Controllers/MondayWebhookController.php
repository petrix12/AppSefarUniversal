<?php

namespace App\Http\Controllers;

use App\Jobs\Monday\ProcessMondayWebhookEventJob;
use App\Models\MondayWebhookEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MondayWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $configured = trim((string) config('services.monday.webhook_secret'));
        $received = trim((string) $request->query('token'));
        abort_unless($configured !== '' && $received !== '' && hash_equals($configured, $received), 401);

        if ($request->filled('challenge')) {
            return response()->json(['challenge' => $request->input('challenge')]);
        }

        $payload = $request->all();
        $event = (array) ($payload['event'] ?? []);
        $eventKey = (string) (
            $event['triggerUuid']
            ?? $event['trigger_uuid']
            ?? hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR))
        );
        $boardId = $event['parentItemBoardId']
            ?? $event['parent_item_board_id']
            ?? $event['parentBoardId']
            ?? $event['parent_board_id']
            ?? $event['boardId']
            ?? $event['board_id']
            ?? null;
        $itemId = $event['subitemId']
            ?? $event['subitem_id']
            ?? $event['pulseId']
            ?? $event['pulse_id']
            ?? $event['itemId']
            ?? $event['item_id']
            ?? null;

        $delivery = MondayWebhookEvent::query()->firstOrCreate(['event_key' => $eventKey], [
            'event_type' => $event['type'] ?? null,
            'board_id' => $boardId !== null ? (string) $boardId : null,
            'item_id' => $itemId !== null ? (string) $itemId : null,
            'payload' => $payload,
            'status' => 'pending',
        ]);

        if ($delivery->wasRecentlyCreated) {
            ProcessMondayWebhookEventJob::dispatch($delivery->id)->onConnection('monday')->onQueue('monday-sync');
        }

        return response()->json(['accepted' => true]);
    }
}
