<?php
namespace App\Console\Commands;

use App\Services\PlatformImportPlanner;
use Illuminate\Console\Command;

class QueuePlatformImports extends Command
{
    protected $signature = 'platforms:import {--provider=} {--account=} {--queue}';
    protected $description = 'Planifica o solicita importaciones locales de Stripe y Kalendia.';

    public function handle(PlatformImportPlanner $planner): int
    {
        try {
            $provider = $this->option('provider') ?: null;
            $account = $this->option('account') ?: null;
            if ($this->option('queue')) {
                $result = $planner->queueAll($provider, $account);
                $this->info("Solicitadas: {$result['requested']}; en curso: {$result['already_running']}; omitidas: {$result['skipped']}.");
                if (isset($result['reasons']['migration_missing'])) $this->warn('Falta la migración de plataformas; algunas solicitudes no se pudieron crear.');
                elseif ($result['skipped']) $this->warn('Algunas importaciones se omitieron por configuración o por ser API bajo demanda.');
                if (isset($result['reasons']['migration_missing'])) return self::FAILURE;
                if ($provider !== 'paypal' && $result['configured'] === 0 && $result['already_running'] === 0) return self::FAILURE;
                return self::SUCCESS;
            }

            $plan = $planner->plan($provider, $account);
            foreach ($plan['connections'] as $connection) {
                $resources = $connection['resources'] ? implode(', ', $connection['resources']) : 'sin recursos en cola';
                $status = $connection['configured'] ? 'credencial configurada' : 'credencial no configurada';
                $this->line("{$connection['provider']}/{$connection['account']} ({$connection['mode']}): {$status}; {$resources}");
            }
            $this->comment('Solo planificación; use --queue para crear solicitudes locales.');
            return self::SUCCESS;
        } catch (\InvalidArgumentException) {
            $this->error('Proveedor o cuenta no admitidos.');
            return self::FAILURE;
        }
    }
}
