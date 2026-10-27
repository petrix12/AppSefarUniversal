<?php

namespace App\Console\Commands;

use App\Jobs\Monday\SyncMondayAccountJob;
use App\Services\MondayMirrorService;
use Illuminate\Console\Command;

class SyncMondayCommand extends Command
{
    protected $signature = 'monday:sync
                            {--board=* : Limitar la sincronización a uno o varios IDs de tablero}
                            {--queue : Despachar el trabajo a la cola monday-sync}';

    protected $description = 'Replica tableros, grupos, columnas, items, subitems y valores de Monday en la base local.';

    public function handle(MondayMirrorService $mirror): int
    {
        $boardIds = array_values(array_unique(array_filter(array_map(
            fn ($id): string => trim((string) $id),
            (array) $this->option('board')
        ))));
        $selection = $boardIds === [] ? null : $boardIds;

        if ((bool) $this->option('queue')) {
            SyncMondayAccountJob::dispatch($selection)->onConnection('monday')->onQueue('monday-sync');
            $this->info('Sincronización de Monday despachada a la cola monday-sync.');
            return self::SUCCESS;
        }

        $this->info('Sincronizando Monday con la base local...');
        $results = $mirror->syncAccount($selection);

        $items = array_sum(array_column($results, 'items'));
        $subitems = array_sum(array_column($results, 'subitems'));
        $values = array_sum(array_column($results, 'values'));
        $this->info(sprintf(
            'Listo: %d tableros, %d items, %d subitems y %d valores.',
            count($results),
            $items,
            $subitems,
            $values
        ));

        return self::SUCCESS;
    }
}
