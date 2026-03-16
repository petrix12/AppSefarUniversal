<?php
// app/Jobs/Teamleader/ProcessContactChunkJob.php

namespace App\Jobs\Teamleader;

use App\Models\TlContact;
use App\Models\TlSyncLog;
use App\Services\TeamleaderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessContactChunkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;
    public int $tries   = 3;
    public int $backoff = 30;

    public function __construct(
        public readonly array $contactIds,
        public readonly int   $chunkNumber,
        public readonly int   $totalChunks,
        public readonly ?int  $syncLogId = null,
    ) {}

    public function handle(TeamleaderService $service): void
    {
        Log::info("[TL] Contactos — Chunk {$this->chunkNumber}/{$this->totalChunks} — procesando " . count($this->contactIds) . " contactos");

        foreach ($this->contactIds as $id) {
            try {
                $detail = $service->getContactById($id);
                TlContact::fromTeamleader($detail);
                TlSyncLog::find($this->syncLogId)?->incrementCounter('processed');
            } catch (\Exception $e) {
                Log::error("[TL] Error contacto {$id}: " . $e->getMessage());
                TlSyncLog::find($this->syncLogId)?->incrementCounter('failed');
            }

            usleep(150000);
        }

        Log::info("[TL] Contactos — Chunk {$this->chunkNumber}/{$this->totalChunks} — completado");
        $this->checkIfCompleted();
    }

    private function checkIfCompleted(): void
    {
        $log = TlSyncLog::find($this->syncLogId);
        if (!$log) return;

        if (($log->processed + $log->failed) >= $log->total) {
            Log::info("[TL] Contactos — SYNC COMPLETADO. Total: {$log->total}");
            $log->update(['status' => 'completed', 'finished_at' => now()]);
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error("[TL] ProcessContactChunkJob {$this->chunkNumber}/{$this->totalChunks} falló: " . $e->getMessage());
        TlSyncLog::find($this->syncLogId)?->incrementCounter('failed', count($this->contactIds));
        $this->checkIfCompleted();
    }
}
