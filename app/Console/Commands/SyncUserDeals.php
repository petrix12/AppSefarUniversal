<?php

namespace App\Console\Commands;

use App\Jobs\SyncUserDealsJob;
use App\Models\User;
use Illuminate\Console\Command;

class SyncUserDeals extends Command
{
    protected $signature = 'deals:sync {user_id} {--sync : Ejecutar en el proceso actual (sin cola)}';
    protected $description = 'Sincroniza la proyección local de tratos desde HubSpot y la contrasta con el histórico local de Teamleader.';

    public function handle(): int
    {
        $user = User::findOrFail((int) $this->argument('user_id'));

        if ($this->option('sync')) {
            // Ejecuta inmediatamente (sin cola)
            SyncUserDealsJob::dispatchSync($user);
            $this->info("✅ Sincronización ejecutada (sync) para user_id={$user->id}");
            return self::SUCCESS;
        }

        // Encola normal
        SyncUserDealsJob::dispatch($user)->onQueue('sync');
        $this->info("📨 Job encolado para user_id={$user->id} (queue=sync)");

        return self::SUCCESS;
    }
}
