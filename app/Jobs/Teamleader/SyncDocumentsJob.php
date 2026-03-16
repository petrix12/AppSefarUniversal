<?php
// app/Jobs/Teamleader/SyncDocumentsJob.php

namespace App\Jobs\Teamleader;

use App\Models\TlDocument;
use App\Models\TlSyncLog;
use App\Services\TeamleaderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SyncDocumentsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;
    public int $tries   = 3;
    public int $backoff = 60;

    public function __construct(
        public readonly string $entityType,
        public readonly string $entityId,
        public readonly int    $page      = 1,
        public readonly ?int   $syncLogId = null,
        public readonly int    $pageSize  = 100,
    ) {}

    public function handle(TeamleaderService $service): void
    {
        Log::info("[TL Docs] {$this->entityType}/{$this->entityId} — página {$this->page}");

        $response = $service->listFiles(
            $this->entityType,
            $this->entityId,
            $this->page,
            $this->pageSize
        );

        $files    = $response['data'] ?? [];
        $received = count($files);

        Log::info("[TL Docs] {$this->entityType}/{$this->entityId} página {$this->page} — recibidos: {$received}");

        foreach ($files as $fileData) {
            try {
                $this->processFile($fileData, $service);
            } catch (\Exception $e) {
                Log::error("[TL Docs] Error en archivo {$fileData['id']}: " . $e->getMessage());
            }

            usleep(300000); // 300ms — archivos son pesados
        }

        // ✅ Sin meta → paginar mientras recibamos página llena
        if ($received === $this->pageSize) {
            Log::info("[TL Docs] {$this->entityType}/{$this->entityId} — hay más, despachando página " . ($this->page + 1));

            self::dispatch(
                $this->entityType,
                $this->entityId,
                $this->page + 1,
                $this->syncLogId,
                $this->pageSize,
            )
                ->onQueue('teamleader-documents')
                ->delay(now()->addSeconds(3));
        } else {
            Log::info("[TL Docs] {$this->entityType}/{$this->entityId} — COMPLETADO en página {$this->page}. Archivos: ~" . (($this->page - 1) * $this->pageSize + $received));
        }
    }

    private function processFile(array $fileData, TeamleaderService $service): void
    {
        $fileId = $fileData['id'];

        // Si ya fue descargado → skip
        $existing = TlDocument::find($fileId);
        if ($existing?->downloaded) {
            Log::info("[TL Docs] Archivo {$fileId} ya en S3, skip.");
            return;
        }

        // 1. Guardar metadata primero
        $document = TlDocument::fromTeamleader(
            $fileData,
            $this->entityType,
            $this->entityId
        );

        // 2. Descargar contenido
        $content = $service->downloadFile($fileId);

        // 3. Construir ruta en S3
        $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $fileData['name'] ?? $fileId);
        $s3Path   = "teamleader/{$this->entityType}/{$this->entityId}/{$fileId}_{$safeName}";

        // 4. Subir a S3
        Storage::disk('s3')->put($s3Path, $content, 'private');

        // 5. Marcar como descargado
        $document->update([
            's3_path'       => $s3Path,
            's3_disk'       => 's3',
            'downloaded'    => true,
            'downloaded_at' => now(),
        ]);

        Log::info("[TL Docs] ✅ {$safeName} → s3://{$s3Path}");
    }

    public function failed(\Throwable $e): void
    {
        Log::error("[TL Docs] Job falló para {$this->entityType}/{$this->entityId}: " . $e->getMessage());
    }
}
