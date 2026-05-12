<?php
// app/Jobs/Teamleader/SyncInvoicesJob.php

namespace App\Jobs\Teamleader;

use App\Models\TlSyncLog;
use App\Services\TeamleaderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncInvoicesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;
    public int $tries   = 3;
    public int $backoff = 30;

    public function __construct(
        public readonly ?int $syncLogId = null,
        public readonly bool $downloadPdfs = true,
    ) {}

    public function handle(TeamleaderService $service): void
    {
        Log::info("[TL] Facturas — recolectando todos los IDs...");

        $allIds  = [];
        $page    = 1;
        $perPage = 100;

        do {
            $response = $service->listInvoices($page, $perPage);
            $items    = $response['data'] ?? [];

            foreach ($items as $item) {
                $allIds[] = $item['id'];
            }

            Log::info("[TL] Facturas — página {$page}: " . count($items) . " IDs");

            $page++;
            usleep(150000);

        } while (count($items) === $perPage);

        $total = count($allIds);
        Log::info("[TL] Facturas — {$total} IDs recolectados. Creando chunks...");

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

        Log::info("[TL] Facturas — {$totalChunks} chunks de 20. Despachando...");

        foreach ($chunks as $index => $chunk) {
            ProcessInvoiceChunkJob::dispatch(
                $chunk,
                $index + 1,
                $totalChunks,
                $this->syncLogId,
                $this->downloadPdfs
            )
            ->onQueue('teamleader-sync')
            ->delay(now()->addSeconds($index * 2));
        }

        Log::info("[TL] Facturas — {$totalChunks} chunks despachados.");
    }

    public function failed(\Throwable $e): void
    {
        Log::error("[TL] SyncInvoicesJob falló: " . $e->getMessage());
        TlSyncLog::find($this->syncLogId)?->fail($e->getMessage());
    }
}
