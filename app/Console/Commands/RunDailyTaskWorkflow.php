<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class RunDailyTaskWorkflow extends Command
{
    protected $signature = 'tasks:daily-workflow
        {--date= : Fecha base opcional YYYY-MM-DD}
        {--per=10 : Tareas base por asesor}
        {--dry-run : Solo muestra cambios, no actualiza nada}';

    protected $description = 'Ejecuta secuencialmente el cierre/reasignacion de tareas vencidas y la generacion de tareas diarias.';

    public function handle(): int
    {
        $date = $this->option('date');
        $per = (int) ($this->option('per') ?? 10);
        $dryRun = (bool) $this->option('dry-run');

        $this->info('Iniciando flujo diario de tareas.');

        $notifyOptions = [];
        if ($date) {
            $notifyOptions['--date'] = $date;
        }
        if ($dryRun) {
            $notifyOptions['--dry-run'] = true;
        }

        $this->line('');
        $this->info('Paso 1/2: revisar tareas vencidas y reasignar clientes.');
        $notifyExitCode = $this->call('tasks:notify-unclosed', $notifyOptions);

        if ($notifyExitCode !== self::SUCCESS) {
            $this->error('El flujo se detuvo porque tasks:notify-unclosed fallo.');
            return $notifyExitCode;
        }

        $generateOptions = [
            '--per' => $per,
        ];
        if ($date) {
            $generateOptions['--date'] = $date;
        }
        if ($dryRun) {
            $generateOptions['--dry-run'] = true;
        }

        $this->line('');
        $this->info('Paso 2/2: generar tareas diarias.');
        $generateExitCode = $this->call('tasks:generate-daily', $generateOptions);

        if ($generateExitCode !== self::SUCCESS) {
            $this->error('El flujo termino con error en tasks:generate-daily.');
            return $generateExitCode;
        }

        $this->line('');
        $this->info('Flujo diario de tareas completado.');

        return self::SUCCESS;
    }
}
