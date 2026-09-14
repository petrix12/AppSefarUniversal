<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\ClientFileReviewService;
use App\Services\CosDocumentImporter;
use App\Services\HubspotService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RefreshClientHubspotFilesJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 300;
    public int $uniqueFor = 900;
    public int $backoff = 60;

    public function __construct(public int $userId, public string $trigger)
    {
        $this->onConnection('cos');
        $this->onQueue('cos-refresh');
    }

    public function uniqueId(): string
    {
        return 'client-hubspot-files:' . $this->userId;
    }

    public function handle(HubspotService $hubspot, CosDocumentImporter $importer, ClientFileReviewService $reviews): void
    {
        $user = User::find($this->userId);
        if (! $user || ! $reviews->canReview($user)) {
            return;
        }

        try {
            $engagements = collect($hubspot->getEngagementsByContactId($user->hs_id))
                ->map(fn (string $url) => [
                    'url' => $url,
                    'hubspot_property' => 'engagement',
                    'document_kind' => null,
                ]);
            $files = $engagements
                ->merge($hubspot->getContactFileFieldRecords($user->hs_id))
                ->sortByDesc(fn (array $file) => filled($file['document_kind'] ?? null))
                ->unique('url')
                ->values()
                ->all();

            $importer->import($files, $user, $hubspot);
            $user->forceFill(['revision_archivos' => now()])->save();
        } catch (\Throwable $exception) {
            Log::warning('No se pudieron revisar los archivos HubSpot del cliente.', [
                'user_id' => $this->userId,
                'trigger' => $this->trigger,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    public function failed(\Throwable $exception): void
    {
        // Let a later login/page visit retry instead of leaving a failed review
        // marked as recent.
        User::whereKey($this->userId)->update(['revision_archivos' => null]);
    }
}
