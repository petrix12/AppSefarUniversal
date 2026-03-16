<?php
// app/Console/Commands/Teamleader/SyncContactsCommand.php

namespace App\Console\Commands\Teamleader;

use App\Jobs\Teamleader\SyncContactsJob;
use App\Models\TlSyncLog;
use Illuminate\Console\Command;

class SyncContactsCommand extends Command
{
    protected $signature   = 'tl:sync-contacts';
    protected $description = 'Sincroniza todos los contactos de Teamleader a la BD local';

    public function handle(): int
    {
        $log = TlSyncLog::create([
            'entity'     => 'contacts',
            'status'     => 'running',
            'started_at' => now(),
        ]);

        SyncContactsJob::dispatch($log->id)
            ->onQueue('teamleader-sync');

        $this->info("[TL] SyncContactsJob despachado — Log ID: {$log->id}");

        return self::SUCCESS;
    }
}
