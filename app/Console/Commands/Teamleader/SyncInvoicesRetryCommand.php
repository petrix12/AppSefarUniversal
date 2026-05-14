<?php
// app/Console/Commands/Teamleader/SyncInvoicesRetryCommand.php

namespace App\Console\Commands\Teamleader;

use App\Jobs\Teamleader\ProcessInvoiceChunkJob;
use App\Models\TlSyncLog;
use Illuminate\Console\Command;

class SyncInvoicesRetryCommand extends Command
{
    protected $signature   = 'tl:sync-invoices-retry {ids* : Uno o varios IDs de factura}';
    protected $description = 'Reintenta sincronizar facturas específicas por ID';

    public function handle(): int
    {
        $ids = $this->argument('ids');

        $this->info('[TL] Reintentando ' . count($ids) . ' facturas...');

        $log = TlSyncLog::create([
            'entity'     => 'invoices_retry',
            'status'     => 'running',
            'total'      => count($ids),
            'started_at' => now(),
        ]);

        ProcessInvoiceChunkJob::dispatch($ids, 1, 1, $log->id, false)
            ->onQueue('teamleader-sync');

        $this->info("[TL] Despachado — Log ID: {$log->id}");

        return self::SUCCESS;
    }
}
