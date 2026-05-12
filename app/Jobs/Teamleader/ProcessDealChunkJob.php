<?php
// app/Jobs/Teamleader/ProcessDealChunkJob.php

namespace App\Jobs\Teamleader;

use App\Models\TlDeal;
use App\Models\TlSyncLog;
use App\Services\TeamleaderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessDealChunkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;
    public int $tries   = 3;
    public int $backoff = 30;

    public function __construct(
        public readonly array $dealIds,
        public readonly int   $chunkNumber,
        public readonly int   $totalChunks,
        public readonly ?int  $syncLogId = null,
        public readonly bool  $syncDocuments = true,
    ) {}

    public function handle(TeamleaderService $service): void
    {
        Log::info("[TL] Deals — Chunk {$this->chunkNumber}/{$this->totalChunks} — procesando " . count($this->dealIds) . " deals");

        foreach ($this->dealIds as $id) {
            try {
                $detail = $service->getDealById($id);
                TlDeal::fromTeamleader($detail);

                if ($this->syncDocuments) {
                    SyncDocumentsJob::dispatch('deal', $id)->onQueue('teamleader-documents');
                }

                TlSyncLog::find($this->syncLogId)?->incrementCounter('processed');
            } catch (\Throwable $e) {
                Log::error("[TL] Error deal {$id}: " . $e->getMessage());
                TlSyncLog::find($this->syncLogId)?->incrementCounter('failed');
            }

            usleep(150000);
        }

        Log::info("[TL] Deals — Chunk {$this->chunkNumber}/{$this->totalChunks} — completado");
        $this->checkIfCompleted();
    }

    private function checkIfCompleted(): void
    {
        $log = TlSyncLog::find($this->syncLogId);
        if (!$log) return;

        if (($log->processed + $log->failed) >= $log->total) {
            Log::info("[TL] Deals — SYNC COMPLETADO. Total: {$log->total}");
            $log->update(['status' => 'completed', 'finished_at' => now()]);
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error("[TL] ProcessDealChunkJob {$this->chunkNumber}/{$this->totalChunks} falló: " . $e->getMessage());
        TlSyncLog::find($this->syncLogId)?->incrementCounter('failed', count($this->dealIds));
        $this->checkIfCompleted();
    }
}
