<?php
// app/Jobs/Teamleader/ProcessProjectChunkJob.php

namespace App\Jobs\Teamleader;

use App\Exceptions\TeamleaderRateLimitException;
use App\Models\TlProject;
use App\Models\TlSyncLog;
use App\Services\TeamleaderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessProjectChunkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;
    public int $tries   = 3;
    public int $backoff = 30;

    public function __construct(
        public readonly array $projectIds,
        public readonly int   $chunkNumber,
        public readonly int   $totalChunks,
        public readonly ?int  $syncLogId = null,
        public readonly bool  $syncDocuments = true,
    ) {}

    public function handle(TeamleaderService $service): void
    {
        Log::channel('teamleader')->info("[TL] Chunk {$this->chunkNumber}/{$this->totalChunks} — procesando " . count($this->projectIds) . " proyectos");

        foreach ($this->projectIds as $offset => $id) {
            try {
                $detail  = $service->getProjectDetails($id);

                if (!is_array($detail)) {
                    Log::channel('teamleader')->warning("[TL] Proyecto {$id}: Teamleader no devolvio detalle.");
                    TlSyncLog::find($this->syncLogId)?->incrementCounter('failed');
                    continue;
                }

                TlProject::fromTeamleader($detail);

                if ($this->syncDocuments()) {
                    SyncDocumentsJob::dispatch('project', $id)->onQueue('teamleader-documents');
                }

                TlSyncLog::find($this->syncLogId)?->incrementCounter('processed');

            } catch (TeamleaderRateLimitException $e) {
                $this->releaseRemaining($offset, $e);
                return;
            } catch (\Throwable $e) {
                Log::channel('teamleader')->error("[TL] Error proyecto {$id}: " . $e->getMessage());
                TlSyncLog::find($this->syncLogId)?->incrementCounter('failed');
            }

            usleep(150000); // 150ms entre llamadas a la API
        }

        Log::channel('teamleader')->info("[TL] Chunk {$this->chunkNumber}/{$this->totalChunks} — completado");

        // ── ¿Es el último chunk? → marcar sync como completado ───────────
        $this->checkIfCompleted();
    }

    private function releaseRemaining(int $offset, TeamleaderRateLimitException $e): void
    {
        $remaining = array_slice($this->projectIds, $offset);

        Log::channel('teamleader')->warning("[TL] Proyectos — rate limit en chunk {$this->chunkNumber}. Reintentando " . count($remaining) . " proyectos luego.");

        self::dispatch(
            $remaining,
            $this->chunkNumber,
            $this->totalChunks,
            $this->syncLogId,
            $this->syncDocuments()
        )
            ->onQueue('teamleader-sync')
            ->delay(now()->addSeconds($e->retryAfterSeconds()));
    }

    private function syncDocuments(): bool
    {
        return isset($this->syncDocuments) ? $this->syncDocuments : true;
    }

    private function checkIfCompleted(): void
    {
        $log = TlSyncLog::find($this->syncLogId);
        if (!$log) return;

        $processed = $log->processed + $log->failed;

        if ($processed >= $log->total) {
            Log::channel('teamleader')->info("[TL] Proyectos — SYNC COMPLETADO. Total: {$log->total}");
            $log->update([
                'status'      => 'completed',
                'finished_at' => now(),
            ]);
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::channel('teamleader')->error("[TL] ProcessProjectChunkJob {$this->chunkNumber}/{$this->totalChunks} falló: " . $e->getMessage());

        // Marcar los proyectos del chunk como fallidos
        $count = count($this->projectIds);
        TlSyncLog::find($this->syncLogId)?->incrementCounter('failed', $count);

        $this->checkIfCompleted();
    }
}
