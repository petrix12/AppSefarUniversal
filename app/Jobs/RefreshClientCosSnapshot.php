<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\ClientCosSnapshotService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RefreshClientCosSnapshot implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 300;
    public int $uniqueFor = 1800;
    public int $backoff = 60;

    public function __construct(public int $userId, public bool $forceRefresh = false)
    {
        // Explicit connection: the application may still use QUEUE_CONNECTION=sync.
        $this->onConnection('cos');
        $this->onQueue('cos-refresh');
    }

    public function uniqueId(): string
    {
        return (string) $this->userId;
    }

    public function handle(ClientCosSnapshotService $snapshots): void
    {
        $user = User::find($this->userId);
        if ($user) {
            $snapshots->get($user, $this->forceRefresh);
            \Illuminate\Support\Facades\Cache::put('cos.page_refreshed.' . $user->id, true, 300);
            \Illuminate\Support\Facades\Cache::remember('cos.monday_users', 3600, function () {
                $result = \Monday::customQuery('users { id name email enabled }');

                return collect($result['users'] ?? [])
                    ->filter(fn ($user) => $user['enabled'] ?? false)
                    ->map(fn ($user) => array_intersect_key($user, array_flip(['id', 'name', 'email'])))
                    ->values()->all();
            });
        }
    }
}
