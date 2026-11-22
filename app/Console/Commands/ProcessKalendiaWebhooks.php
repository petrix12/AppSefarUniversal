<?php

namespace App\Console\Commands;

use App\Services\KalendiaWebhookProcessor;
use App\Services\KalendiaApi;
use Illuminate\Console\Command;

class ProcessKalendiaWebhooks extends Command
{
    protected $signature = 'kalendia:process-webhooks {--limit=4}';
    protected $description = 'Actualiza citas notificadas por Kalendia mediante lecturas por ID, sin crear citas ni pagos';

    public function handle(KalendiaWebhookProcessor $processor, KalendiaApi $api): int
    {
        if (!$api->configured()) { $this->error('Falta configurar KALENDIA_TOKEN. Se conservan los eventos pendientes.'); return self::FAILURE; }
        $counts = $processor->process(max(1, min(20, (int) $this->option('limit'))));
        $this->info('Citas actualizadas: '.$counts['synced'].'. Reintentos pendientes: '.$counts['retry'].'. Revisión: '.$counts['review'].'.');
        return self::SUCCESS;
    }
}
