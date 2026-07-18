<?php
// app/Jobs/Teamleader/ProcessDealChunkJob.php

namespace App\Jobs\Teamleader;

use App\Exceptions\TeamleaderAuthenticationException;
use App\Exceptions\TeamleaderRateLimitException;
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
        Log::channel('teamleader')->info("[TL] Deals — Chunk {$this->chunkNumber}/{$this->totalChunks} — procesando " . count($this->dealIds) . " deals");

        foreach ($this->dealIds as $offset => $id) {
            try {
                $detail = $service->getDealById($id);

                if (!is_array($detail)) {
                    Log::channel('teamleader')->warning("[TL] Deal {$id}: Teamleader no devolvio detalle.");
                    TlSyncLog::find($this->syncLogId)?->incrementCounter('failed');
                    continue;
                }

                TlDeal::fromTeamleader($detail);

                if ($this->syncDocuments()) {
                    SyncDocumentsJob::dispatch('deal', $id)->onQueue('teamleader-documents');
                }

                TlSyncLog::find($this->syncLogId)?->incrementCounter('processed');
            } catch (TeamleaderRateLimitException $e) {
                $this->releaseRemaining($offset, $e);
                return;
            } catch (TeamleaderAuthenticationException $e) {
                Log::channel('teamleader')->warning("[TL] Deals - autenticacion de Teamleader detenida: {$e->getMessage()}");
                TlSyncLog::find($this->syncLogId)?->fail($e->getMessage());
                return;
            } catch (\Throwable $e) {
                Log::channel('teamleader')->error("[TL] Error deal {$id}: " . $e->getMessage());
                TlSyncLog::find($this->syncLogId)?->incrementCounter('failed');
            }

            usleep(150000);
        }

        Log::channel('teamleader')->info("[TL] Deals — Chunk {$this->chunkNumber}/{$this->totalChunks} — completado");
        $this->checkIfCompleted();
    }

    private function releaseRemaining(int $offset, TeamleaderRateLimitException $e): void
    {
        $remaining = array_slice($this->dealIds, $offset);

        Log::channel('teamleader')->warning("[TL] Deals — rate limit en chunk {$this->chunkNumber}. Reintentando " . count($remaining) . " deals luego.");

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

        if (($log->processed + $log->failed) >= $log->total) {
            Log::channel('teamleader')->info("[TL] Deals — SYNC COMPLETADO. Total: {$log->total}");
            $log->update(['status' => 'completed', 'finished_at' => now()]);
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::channel('teamleader')->error("[TL] ProcessDealChunkJob {$this->chunkNumber}/{$this->totalChunks} falló: " . $e->getMessage());
        TlSyncLog::find($this->syncLogId)?->incrementCounter('failed', count($this->dealIds));
        $this->checkIfCompleted();
    }
}
