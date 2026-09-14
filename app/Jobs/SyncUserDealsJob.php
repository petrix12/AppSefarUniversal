<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\HubspotDealLocalSyncService;
use App\Services\HubspotDealTeamleaderProjectLinkService;
use App\Services\HubspotService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Refreshes the local projection of HubSpot deals. Teamleader is intentionally
 * read only: existing projects may be linked, but are never created or edited.
 */
class SyncUserDealsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 300;
    public array $backoff = [30, 60, 120];

    public function __construct(public User $user)
    {
    }

    public function handle(HubspotService $hubspot): void
    {
        $user = $this->user->fresh();
        if (! $user || blank($user->hs_id)) {
            Log::info('Sincronización de tratos omitida: el usuario no tiene contacto HubSpot.', [
                'user_id' => $this->user->id,
            ]);

            return;
        }

        try {
            $deals = $hubspot->getDealsByContactId((string) $user->hs_id);
            $local = app(HubspotDealLocalSyncService::class)->sync($user, $deals);
            $links = app(HubspotDealTeamleaderProjectLinkService::class)->detectAndLink($user);

            Log::info('Tratos de HubSpot actualizados y contrastados con histórico Teamleader.', [
                'user_id' => $user->id,
                'hubspot_deals' => count($deals),
                'local' => $local,
                'links' => $links,
            ]);
        } catch (\Throwable $exception) {
            Log::error('Error en la sincronización de tratos de HubSpot.', [
                'user_id' => $user->id,
                'attempt' => $this->attempts(),
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}
