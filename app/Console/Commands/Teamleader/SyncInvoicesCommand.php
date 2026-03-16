<?php
// app/Console/Commands/Teamleader/SyncInvoicesCommand.php

namespace App\Console\Commands\Teamleader;

use App\Jobs\Teamleader\SyncInvoicesJob;
use App\Models\TlSyncLog;
use Illuminate\Console\Command;

class SyncInvoicesCommand extends Command
{
    protected $signature   = 'tl:sync-invoices';
    protected $description = 'Sincroniza todas las facturas de Teamleader a la BD local';

    public function handle(): int
    {
        $log = TlSyncLog::create([
            'entity'     => 'invoices',
            'status'     => 'running',
            'started_at' => now(),
        ]);

        SyncInvoicesJob::dispatch($log->id)
            ->onQueue('teamleader-sync');

        $this->info("[TL] SyncInvoicesJob despachado — Log ID: {$log->id}");

        return self::SUCCESS;
    }
}
