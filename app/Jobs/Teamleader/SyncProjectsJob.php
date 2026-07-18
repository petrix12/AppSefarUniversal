<?php

namespace App\Jobs\Teamleader;

use App\Exceptions\TeamleaderAuthenticationException;
use App\Exceptions\TeamleaderRateLimitException;
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

    public int $timeout = 300;
    public int $tries = 3;
    public int $backoff = 90;

    public function __construct(
        public readonly ?int $syncLogId = null,
        public readonly bool $syncDocuments = true,
        public readonly int $page = 1,
    ) {}

    public function handle(TeamleaderService $service): void
    {
        $perPage = 100;
        $pagesPerJob = max(1, (int) config('services.teamleader.sync_pages_per_job', 2));
        $page = isset($this->page) ? $this->page : 1;
        $allIds = [];
        $lastCount = 0;

        Log::channel('teamleader')->info("[TL] Proyectos - recolectando IDs desde pagina {$page}...");

        for ($i = 0; $i < $pagesPerJob; $i++) {
            try {
                $response = $service->listProjects($page, $perPage);
            } catch (TeamleaderRateLimitException $e) {
                Log::channel('teamleader')->warning("[TL] Proyectos - rate limit en pagina {$page}. Reintentando luego.");
                $this->release($e->retryAfterSeconds());
                return;
            } catch (TeamleaderAuthenticationException $e) {
                Log::channel('teamleader')->warning("[TL] Proyectos - autenticacion de Teamleader detenida: {$e->getMessage()}");
                TlSyncLog::find($this->syncLogId)?->fail($e->getMessage());
                return;
            }

            $items = $response['data'] ?? [];
            $lastCount = count($items);

            foreach ($items as $item) {
                if (!empty($item['id'])) {
                    $allIds[] = $item['id'];
                }
            }

            Log::channel('teamleader')->info("[TL] Proyectos - pagina {$page}: {$lastCount} IDs");

            $page++;

            if ($lastCount < $perPage) {
                break;
            }
        }

        $this->dispatchNextPageIfNeeded($lastCount === $perPage, $page);
        $this->dispatchChunks($allIds);

        if (!$allIds && $lastCount < $perPage) {
            TlSyncLog::find($this->syncLogId)?->update([
                'status' => 'completed',
                'finished_at' => now(),
            ]);
        }
    }

    private function dispatchNextPageIfNeeded(bool $hasMore, int $nextPage): void
    {
        if (!$hasMore) {
            return;
        }

        self::dispatch($this->syncLogId, $this->syncDocuments(), $nextPage)
            ->onQueue('teamleader-sync')
            ->delay(now()->addSeconds(1));
    }

    private function dispatchChunks(array $ids): void
    {
        $total = count($ids);

        if ($total === 0) {
            return;
        }

        TlSyncLog::find($this->syncLogId)?->incrementCounter('total', $total);

        $chunkSize = max(1, (int) config('services.teamleader.sync_chunk_size', 5));
        $chunkDelay = max(1, (int) config('services.teamleader.sync_chunk_delay_seconds', 12));
        $chunks = array_chunk($ids, $chunkSize);
        $totalChunks = count($chunks);

        Log::channel('teamleader')->info("[TL] Proyectos - {$totalChunks} chunks de {$chunkSize}. Despachando...");

        foreach ($chunks as $index => $chunk) {
            ProcessProjectChunkJob::dispatch(
                $chunk,
                $index + 1,
                $totalChunks,
                $this->syncLogId,
                $this->syncDocuments()
            )
                ->onQueue('teamleader-sync')
                ->delay(now()->addSeconds($index * $chunkDelay));
        }
    }

    private function syncDocuments(): bool
    {
        return isset($this->syncDocuments) ? $this->syncDocuments : true;
    }

    public function failed(\Throwable $e): void
    {
        Log::channel('teamleader')->error("[TL] SyncProjectsJob fallo: " . $e->getMessage());
        TlSyncLog::find($this->syncLogId)?->fail($e->getMessage());
    }
}
