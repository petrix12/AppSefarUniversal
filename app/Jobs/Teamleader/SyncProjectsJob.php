<?php
// app/Jobs/Teamleader/SyncProjectsJob.php

namespace App\Jobs\Teamleader;

use App\Models\TlSyncLog;
use App\Services\TeamleaderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncProjectsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;
    public int $tries   = 3;
    public int $backoff = 30;

    public function __construct(
        public readonly ?int $syncLogId = null,
        public readonly bool $syncDocuments = true,
    ) {}

    public function handle(TeamleaderService $service): void
    {
        Log::info("[TL] Proyectos — recolectando todos los IDs...");

        // ── Paso 1: recoger TODOS los IDs paginando ──────────────────────
        $allIds  = [];
        $page    = 1;
        $perPage = 100;

        // ✅ Usar el total real de la API
        do {
            $response = $service->listProjects($page, $perPage);
            $items    = $response['data'] ?? [];

            foreach ($items as $item) {
                $allIds[] = $item['id'];
            }

            Log::info("[TL] Proyectos — página {$page}: " . count($items) . " IDs");

            $page++;
            usleep(150000);

        } while (count($items) === $perPage); // ← para cuando devuelva menos de 100

        $total = count($allIds);
        Log::info("[TL] Proyectos — {$total} IDs recolectados. Creando chunks...");

        // Actualizar el total real en el log
        TlSyncLog::find($this->syncLogId)?->update(['total' => $total]);

        if ($total === 0) {
            TlSyncLog::find($this->syncLogId)?->update([
                'status'      => 'completed',
                'finished_at' => now(),
            ]);
            return;
        }

        // ── Paso 2: dividir en chunks y despachar en paralelo ─────────────
        $chunks     = array_chunk($allIds, 20);
        $totalChunks = count($chunks);

        Log::info("[TL] Proyectos — {$totalChunks} chunks de 20. Despachando...");

        foreach ($chunks as $index => $chunk) {
            ProcessProjectChunkJob::dispatch(
                $chunk,
                $index + 1,      // chunkNumber (para logs)
                $totalChunks,    // totalChunks (para saber cuándo terminar)
                $this->syncLogId,
                $this->syncDocuments
            )
            ->onQueue('teamleader-sync')
            ->delay(now()->addSeconds($index * 2)); // escalonado 2s entre chunks
        }

        Log::info("[TL] Proyectos — todos los chunks despachados.");
    }

    public function failed(\Throwable $e): void
    {
        Log::error("[TL] SyncProjectsJob falló: " . $e->getMessage());
        TlSyncLog::find($this->syncLogId)?->fail($e->getMessage());
    }
}
