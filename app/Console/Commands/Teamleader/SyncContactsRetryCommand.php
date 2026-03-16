<?php
// app/Console/Commands/Teamleader/SyncContactsRetryCommand.php

namespace App\Console\Commands\Teamleader;

use App\Jobs\Teamleader\ProcessContactChunkJob;
use App\Models\TlSyncLog;
use Illuminate\Console\Command;

class SyncContactsRetryCommand extends Command
{
    protected $signature = 'tl:sync-contacts-retry {ids* : Uno o varios IDs de contacto separados por espacio}';
    protected $description = 'Reintenta sincronizar contactos específicos por ID';

    public function handle(): int
    {
        $ids = $this->argument('ids');

        $this->info('[TL] Reintentando ' . count($ids) . ' contactos...');

        $log = TlSyncLog::create([
            'entity'     => 'contacts_retry',
            'status'     => 'running',
            'total'      => count($ids),
            'started_at' => now(),
        ]);

        ProcessContactChunkJob::dispatch(
            $ids,
            1,
            1,
            $log->id
        )->onQueue('teamleader-sync');

        $this->info("[TL] Despachado — Log ID: {$log->id}");

        return self::SUCCESS;
    }
}
