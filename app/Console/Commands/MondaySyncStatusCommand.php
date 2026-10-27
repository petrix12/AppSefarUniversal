<?php

namespace App\Console\Commands;

use App\Models\MondayBoard;
use App\Models\MondayItem;
use App\Models\MondayItemValue;
use App\Models\MondayItemMovement;
use App\Models\MondaySyncRun;
use Illuminate\Console\Command;

class MondaySyncStatusCommand extends Command
{
    protected $signature = 'monday:status {--limit=10 : Cantidad de ejecuciones recientes}';

    protected $description = 'Muestra el estado y la cobertura de la réplica local de Monday.';

    public function handle(): int
    {
        $this->table(['Entidad', 'Registros'], [
            ['Tableros disponibles', MondayBoard::available()->count()],
            ['Items activos', MondayItem::query()->count()],
            ['Valores de columnas', MondayItemValue::query()->count()],
            ['Movimientos auditados', MondayItemMovement::query()->count()],
        ]);

        $runs = MondaySyncRun::query()
            ->latest('id')
            ->limit(max(1, (int) $this->option('limit')))
            ->get()
            ->map(fn (MondaySyncRun $run): array => [
                $run->id,
                $run->scope,
                $run->board_id ?: '—',
                $run->status,
                $run->items + $run->subitems,
                optional($run->started_at)->toDateTimeString(),
                optional($run->finished_at)->toDateTimeString(),
                $run->error_message ? mb_strimwidth($run->error_message, 0, 70, '…') : '',
            ]);

        $this->table(
            ['ID', 'Alcance', 'Tablero', 'Estado', 'Items', 'Inicio', 'Fin', 'Error'],
            $runs
        );

        return self::SUCCESS;
    }
}
