<?php

namespace App\Jobs\Monday;

use App\Services\MondayMirrorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncMondayAccountJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public int $tries = 3;

    public int $backoff = 60;

    public int $uniqueFor = 900;

    public function __construct(public readonly ?array $boardIds = null) {}

    public function handle(MondayMirrorService $mirror): void
    {
        foreach ($mirror->discoverBoards($this->boardIds) as $boardId) {
            if (filled(config('services.monday.webhook_secret'))) {
                EnsureMondayBoardWebhooksJob::dispatch($boardId)->onConnection('monday')->onQueue('monday-sync');
            }
            SyncMondayBoardJob::dispatch($boardId)->onConnection('monday')->onQueue('monday-sync');
        }
    }

    public function uniqueId(): string
    {
        return $this->boardIds === null ? 'all' : hash('sha256', implode(',', $this->boardIds));
    }
}
