<?php

namespace App\Jobs\Monday;

use App\Services\MondayMirrorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncMondayBoardJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;

    public int $tries = 3;

    public int $backoff = 90;

    public int $uniqueFor = 1800;

    public function __construct(public readonly string $boardId) {}

    public function handle(MondayMirrorService $mirror): void
    {
        $mirror->syncBoard($this->boardId);
    }

    public function uniqueId(): string
    {
        return $this->boardId;
    }
}
