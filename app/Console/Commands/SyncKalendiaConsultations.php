<?php

namespace App\Console\Commands;

use App\Services\{KalendiaApiException, KalendiaConsultationCatalog};
use Illuminate\Console\Command;

class SyncKalendiaConsultations extends Command
{
    protected $signature = 'kalendia:sync-consultations';
    protected $description = 'Actualiza servicios y precios del catálogo de consultas desde Kalendia';

    public function handle(KalendiaConsultationCatalog $catalog): int
    {
        try { $result = $catalog->sync(true); }
        catch (KalendiaApiException) {
            $this->error('No se pudo consultar el catálogo completo de Kalendia. Se conserva la información anterior.');
            return self::FAILURE;
        }
        $this->info($result['available'].' servicios con precio sincronizados; '.$result['skipped'].' servicios gratuitos, inactivos o con precio o moneda no admitidos.');
        return self::SUCCESS;
    }
}
