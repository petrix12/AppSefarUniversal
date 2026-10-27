<?php

namespace App\Jobs\Monday;

use App\Models\MondayWebhookEvent;
use App\Services\MondayMirrorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessMondayWebhookEventJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    public int $tries = 5;

    public int $backoff = 60;

    public function __construct(public readonly int $eventId) {}

    public function handle(MondayMirrorService $mirror): void
    {
        $delivery = MondayWebhookEvent::find($this->eventId);
        if ($delivery === null || $delivery->status === 'completed') {
            return;
        }

        $delivery->update(['status' => 'processing', 'error_message' => null]);

        try {
            $event = (array) data_get($delivery->payload, 'event', []);
            $parentItemId = $event['newParentItemId']
                ?? $event['new_parent_item_id']
                ?? $event['parentItemId']
                ?? $event['parent_item_id']
                ?? $event['parentPulseId']
                ?? $event['parent_pulse_id']
                ?? null;

            if ($delivery->item_id) {
                // Querying the current item state also resolves cross-board
                // moves safely when source and destination events arrive out
                // of order.
                $mirror->syncItem(
                    $delivery->item_id,
                    $delivery->board_id,
                    $parentItemId !== null ? (string) $parentItemId : null
                );
            } elseif ($delivery->board_id) {
                $mirror->syncBoard($delivery->board_id);
            }

            $delivery->update([
                'status' => 'completed',
                'processed_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $delivery->update([
                'status' => 'failed',
                'error_message' => mb_substr($e->getMessage(), 0, 65000),
            ]);
            throw $e;
        }
    }

}
