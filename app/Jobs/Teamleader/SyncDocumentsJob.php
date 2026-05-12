<?php

namespace App\Jobs\Teamleader;

use App\Models\TlContact;
use App\Models\TlDocument;
use App\Models\TlDocumentUserLink;
use App\Models\TlDeal;
use App\Models\TlProject;
use App\Models\TlSyncLog;
use App\Models\User;
use App\Services\TeamleaderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class SyncDocumentsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 600;
    public int $tries = 3;
    public int $backoff = 60;

    private bool $resolvedUserChecked = false;
    private ?User $resolvedUser = null;
    private ?TlContact $resolvedContact = null;
    private ?string $resolvedMatchedBy = null;

    public function __construct(
        public readonly string $entityType,
        public readonly string $entityId,
        public readonly int $page = 1,
        public readonly ?int $syncLogId = null,
        public readonly int $pageSize = 100,
        public readonly bool $linkToUsers = true,
        public readonly bool $runInline = false,
    ) {}

    public function handle(TeamleaderService $service): void
    {
        Log::info("[TL Docs] {$this->entityType}/{$this->entityId} page {$this->page}");

        $response = $service->listFiles(
            $this->entityType,
            $this->entityId,
            $this->page,
            $this->pageSize
        );

        $files = $response['data'] ?? [];
        $received = count($files);

        foreach ($files as $fileData) {
            try {
                $this->processFile($fileData, $service);
            } catch (\Throwable $e) {
                Log::error('[TL Docs] Error migrando archivo', [
                    'entity_type' => $this->entityType,
                    'entity_id' => $this->entityId,
                    'file_id' => $fileData['id'] ?? null,
                    'error' => $e->getMessage(),
                ]);
            }

            usleep(300000);
        }

        if ($received === $this->pageSize) {
            if ($this->runInline) {
                (new self(
                    $this->entityType,
                    $this->entityId,
                    $this->page + 1,
                    $this->syncLogId,
                    $this->pageSize,
                    $this->linkToUsers,
                    true
                ))->handle($service);

                return;
            }

            self::dispatch(
                $this->entityType,
                $this->entityId,
                $this->page + 1,
                $this->syncLogId,
                $this->pageSize,
                $this->linkToUsers,
                $this->runInline
            )
                ->onQueue('teamleader-documents')
                ->delay(now()->addSeconds(3));

            return;
        }

        $this->markEntityCompleted();
        Log::info("[TL Docs] {$this->entityType}/{$this->entityId} completado.");
    }

    private function processFile(array $fileData, TeamleaderService $service): void
    {
        $fileId = $fileData['id'] ?? null;

        if (!$fileId) {
            return;
        }

        $existingDocument = TlDocument::find($fileId);
        $document = TlDocument::fromTeamleader($fileData, $this->entityType, $this->entityId);

        if (!$this->documentNeedsDownload($existingDocument, $fileData)) {
            $this->linkDocumentToAppUser($document);
            Log::info("[TL Docs] {$fileId} ya estaba en S3.");
            return;
        }

        $content = $service->downloadFile($fileId);
        $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $fileData['name'] ?? $fileId) ?: $fileId;
        $fileName = $this->safeFileName($fileId, $safeName);
        $s3Path = "teamleader/{$this->entityType}/{$this->entityId}/{$fileName}";

        Storage::disk('s3')->put($s3Path, $content, 'private');

        $document->update([
            's3_path' => $s3Path,
            's3_disk' => 's3',
            'downloaded' => true,
            'downloaded_at' => now(),
        ]);

        $this->linkDocumentToAppUser($document);

        Log::info("[TL Docs] {$fileName} -> s3://{$s3Path}");
    }

    private function documentNeedsDownload(?TlDocument $existingDocument, array $fileData): bool
    {
        if (!$existingDocument || !$existingDocument->downloaded || blank($existingDocument->s3_path)) {
            return true;
        }

        $teamleaderUpdatedAt = $fileData['updated_at'] ?? null;

        if (!$teamleaderUpdatedAt || !$existingDocument->tl_updated_at) {
            return false;
        }

        try {
            return Carbon::parse($teamleaderUpdatedAt)->gt($existingDocument->tl_updated_at);
        } catch (\Throwable) {
            return false;
        }
    }

    private function linkDocumentToAppUser(TlDocument $document): void
    {
        if (!$this->linkToUsers) {
            return;
        }

        $user = $this->resolveAppUser();

        if (!$user) {
            return;
        }

        $link = TlDocumentUserLink::firstOrNew([
            'tl_document_id' => $document->id,
            'user_id' => $user->id,
        ]);

        $link->fill([
            'tl_contact_id' => $this->resolvedContact?->id,
            'entity_type' => $document->entity_type,
            'entity_id' => $document->entity_id,
            'matched_by' => $this->resolvedMatchedBy,
        ]);

        if (!$link->exists) {
            $link->status = 'suggested';
        }

        $link->save();
    }

    private function resolveAppUser(): ?User
    {
        if ($this->resolvedUserChecked) {
            return $this->resolvedUser;
        }

        $this->resolvedUserChecked = true;

        if ($this->entityType === 'contact') {
            $this->resolvedContact = TlContact::find($this->entityId);
            $this->resolvedUser = $this->resolveUserFromContact($this->resolvedContact);
            return $this->resolvedUser;
        }

        if ($this->entityType === 'project') {
            $project = TlProject::find($this->entityId);

            if ($project && $project->customer_type === 'contact') {
                $this->resolvedContact = TlContact::find($project->customer_id);
                $this->resolvedUser = $this->resolveUserFromContact($this->resolvedContact);
            }
        }

        if ($this->entityType === 'deal') {
            $deal = TlDeal::find($this->entityId);

            if ($deal && $deal->customer_type === 'contact') {
                $this->resolvedContact = TlContact::find($deal->customer_id);
                $this->resolvedUser = $this->resolveUserFromContact($this->resolvedContact);
            }
        }

        return $this->resolvedUser;
    }

    private function resolveUserFromContact(?TlContact $contact): ?User
    {
        if (!$contact) {
            return null;
        }

        if (Schema::hasColumn('users', 'tl_id') && filled($contact->id)) {
            $user = User::where('tl_id', $contact->id)->first();

            if ($user) {
                $this->resolvedMatchedBy = 'tl_id';
                return $user;
            }
        }

        if (filled($contact->email)) {
            $user = User::where('email', $contact->email)->first();

            if ($user) {
                $this->resolvedMatchedBy = 'email';
                return $user;
            }
        }

        if (filled($contact->passport)) {
            $user = User::where('passport', $contact->passport)->first();

            if ($user) {
                $this->resolvedMatchedBy = 'passport';
                return $user;
            }
        }

        return null;
    }

    private function safeFileName(string $fileId, string $name): string
    {
        $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $name) ?: $fileId;

        return str_starts_with($safeName, "{$fileId}_")
            ? $safeName
            : "{$fileId}_{$safeName}";
    }

    private function markEntityCompleted(): void
    {
        $log = TlSyncLog::find($this->syncLogId);

        if (!$log) {
            return;
        }

        $log->incrementCounter('processed');

        if (($log->processed + $log->failed) >= $log->total) {
            $log->update([
                'status' => 'completed',
                'finished_at' => now(),
            ]);
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error("[TL Docs] Job fallo para {$this->entityType}/{$this->entityId}: " . $e->getMessage());
        TlSyncLog::find($this->syncLogId)?->incrementCounter('failed');
    }
}
