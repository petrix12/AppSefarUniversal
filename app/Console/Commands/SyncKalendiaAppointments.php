<?php
namespace App\Console\Commands;
use App\Services\{KalendiaAppointmentService, KalendiaApiException};
use Illuminate\Console\Command;
class SyncKalendiaAppointments extends Command
{
    protected $signature = 'kalendia:sync-appointments';
    protected $description = 'Actualiza una página incremental de citas y sus estados locales';
    public function handle(KalendiaAppointmentService $service): int
    {
        try { $this->info('Citas consultadas: '.$service->syncPage()); return self::SUCCESS; }
        catch (KalendiaApiException) { $this->error('Kalendia no confirmó la consulta. Se conserva el punto de sincronización.'); return self::FAILURE; }
    }
}
