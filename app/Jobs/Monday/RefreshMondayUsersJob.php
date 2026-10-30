<?php

namespace App\Jobs\Monday;

use App\Services\MondayApiClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

/** Refresh the optional staff selector independently of the COS calculation. */
class RefreshMondayUsersJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 180;
    public int $tries = 3;
    public int $backoff = 60;
    public int $uniqueFor = 3600;

    public function __construct()
    {
        $this->onConnection('monday')->onQueue('monday-sync');
    }

    public function handle(MondayApiClient $client): void
    {
        $data = $client->query('query { users { id name email enabled } }');
        if (! is_array($data['users'] ?? null)) {
            throw new RuntimeException('Monday no devolvió el directorio de usuarios.');
        }
        $users = collect($data['users'])->filter(fn ($user) => $user['enabled'] ?? false)
            ->map(fn ($user) => array_intersect_key($user, array_flip(['id', 'name', 'email'])))
            ->values()->all();
        // Keep the last usable directory available when a later synchronization fails.
        Cache::forever('cos.monday_users', $users);
    }
}
