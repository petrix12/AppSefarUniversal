<?php
// app/Jobs/Teamleader/ProcessCreditNoteChunkJob.php

namespace App\Jobs\Teamleader;

use App\Exceptions\TeamleaderRateLimitException;
use App\Models\TlCreditNote;
use App\Models\TlSyncLog;
use App\Services\TeamleaderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessCreditNoteChunkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;
    public int $tries   = 3;
    public int $backoff = 30;

    public function __construct(
        public readonly array $creditNoteIds,
        public readonly int   $chunkNumber,
        public readonly int   $totalChunks,
        public readonly ?int  $syncLogId = null,
    ) {}

    public function handle(TeamleaderService $service): void
    {
        Log::channel('teamleader')->info("[TL] CreditNotes — Chunk {$this->chunkNumber}/{$this->totalChunks} — procesando " . count($this->creditNoteIds) . " notas de crédito");

        foreach ($this->creditNoteIds as $offset => $id) {
            try {
                $detail = $service->getCreditNoteById($id);

                if (!is_array($detail)) {
                    Log::channel('teamleader')->warning("[TL] Nota de credito {$id}: Teamleader no devolvio detalle.");
                    TlSyncLog::find($this->syncLogId)?->incrementCounter('failed');
                    continue;
                }

                TlCreditNote::fromTeamleader($detail);
                TlSyncLog::find($this->syncLogId)?->incrementCounter('processed');
            } catch (TeamleaderRateLimitException $e) {
                $this->releaseRemaining($offset, $e);
                return;
            } catch (\Throwable $e) {
                Log::channel('teamleader')->error("[TL] Error creditNote {$id}: " . $e->getMessage());
                TlSyncLog::find($this->syncLogId)?->incrementCounter('failed');
            }

            usleep(150000);
        }

        Log::channel('teamleader')->info("[TL] CreditNotes — Chunk {$this->chunkNumber}/{$this->totalChunks} — completado");
        $this->checkIfCompleted();
    }

    private function releaseRemaining(int $offset, TeamleaderRateLimitException $e): void
    {
        $remaining = array_slice($this->creditNoteIds, $offset);

        Log::channel('teamleader')->warning("[TL] CreditNotes — rate limit en chunk {$this->chunkNumber}. Reintentando " . count($remaining) . " notas luego.");

        self::dispatch(
            $remaining,
            $this->chunkNumber,
            $this->totalChunks,
            $this->syncLogId
        )
            ->onQueue('teamleader-sync')
            ->delay(now()->addSeconds($e->retryAfterSeconds()));
    }

    private function checkIfCompleted(): void
    {
        $log = TlSyncLog::find($this->syncLogId);
        if (!$log) return;

        if (($log->processed + $log->failed) >= $log->total) {
            Log::channel('teamleader')->info("[TL] CreditNotes — SYNC COMPLETADO. Total: {$log->total}");
            $log->update(['status' => 'completed', 'finished_at' => now()]);
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::channel('teamleader')->error("[TL] ProcessCreditNoteChunkJob {$this->chunkNumber}/{$this->totalChunks} falló: " . $e->getMessage());
        TlSyncLog::find($this->syncLogId)?->incrementCounter('failed', count($this->creditNoteIds));
        $this->checkIfCompleted();
    }
}
