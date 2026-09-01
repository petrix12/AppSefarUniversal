<?php

namespace App\Jobs;

use App\Services\ExternalPlatformFieldCatalogService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Traverses Monday board metadata in bounded chunks. No Monday item values
 * are fetched, and the job only updates the unification catalogue cache.
 */
class RefreshMondayFieldCatalogue implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 55;

    public function handle(ExternalPlatformFieldCatalogService $catalogues): void
    {
        $catalogues->refreshMondayChunk();
    }

    public function failed(Throwable $exception): void
    {
        app(ExternalPlatformFieldCatalogService::class)->markMondayRefreshFailed($exception);
    }
}
