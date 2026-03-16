<?php
// app/Jobs/Teamleader/SyncCreditNotesJob.php

namespace App\Jobs\Teamleader;

use App\Models\TlSyncLog;
use App\Services\TeamleaderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncCreditNotesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;
    public int $tries   = 3;
    public int $backoff = 30;

    public function __construct(
        public readonly ?int $syncLogId = null,
    ) {}

    public function handle(TeamleaderService $service): void
    {
        Log::info("[TL] CreditNotes — recolectando todos los IDs...");

        $allIds  = [];
        $page    = 1;
        $perPage = 100;

        do {
            $response = $service->listCreditNotes($page, $perPage);
            $items    = $response['data'] ?? [];

            foreach ($items as $item) {
                $allIds[] = $item['id'];
            }

            Log::info("[TL] CreditNotes — página {$page}: " . count($items) . " IDs");

            $page++;
            usleep(150000);

        } while (count($items) === $perPage);

        $total = count($allIds);
        Log::info("[TL] CreditNotes — {$total} IDs recolectados. Creando chunks...");

        TlSyncLog::find($this->syncLogId)?->update(['total' => $total]);

        if ($total === 0) {
            TlSyncLog::find($this->syncLogId)?->update([
                'status'      => 'completed',
                'finished_at' => now(),
            ]);
            return;
        }

        $chunks      = array_chunk($allIds, 20);
        $totalChunks = count($chunks);

        Log::info("[TL] CreditNotes — {$totalChunks} chunks de 20. Despachando...");

        foreach ($chunks as $index => $chunk) {
            ProcessCreditNoteChunkJob::dispatch(
                $chunk,
                $index + 1,
                $totalChunks,
                $this->syncLogId
            )
            ->onQueue('teamleader-sync')
            ->delay(now()->addSeconds($index * 2));
        }

        Log::info("[TL] CreditNotes — {$totalChunks} chunks despachados.");
    }

    public function failed(\Throwable $e): void
    {
        Log::error("[TL] SyncCreditNotesJob falló: " . $e->getMessage());
        TlSyncLog::find($this->syncLogId)?->fail($e->getMessage());
    }
}
