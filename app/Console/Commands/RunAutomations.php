<?php

namespace App\Console\Commands;

use App\Services\AutomationEngine;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class RunAutomations extends Command
{
    protected $signature = 'automations:run {--limit=100 : Máximo de acciones vencidas a procesar}';

    protected $description = 'Evalúa reglas programadas y ejecuta las acciones de automatización pendientes.';

    public function handle(AutomationEngine $engine): int
    {
        if (! Schema::hasTable('automation_rules')) {
            $this->warn('El motor de automatizaciones aún no está migrado.');

            return self::SUCCESS;
        }

        $limit = max(1, min((int) $this->option('limit'), 500));
        $result = $engine->runScheduler($limit);

        $this->table(['Elemento', 'Cantidad'], [
            ['Acciones cron encoladas', $result['cron_queued']],
            ['Acciones por fecha encoladas', $result['date_queued']],
            ['Completadas', $result['processed']['completed']],
            ['Omitidas', $result['processed']['skipped']],
            ['Fallidas', $result['processed']['failed']],
        ]);

        return $result['processed']['failed'] === 0 ? self::SUCCESS : self::FAILURE;
    }
}
