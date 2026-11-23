<?php

namespace App\Console\Commands;

use App\Services\KalendiaApi;
use App\Services\KalendiaBookingQueue;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class ProcessKalendiaBookings extends Command
{
    protected $signature = 'kalendia:process-bookings';
    protected $description = 'Procesa una solicitud de cita pendiente de Kalendia';

    public function handle(KalendiaBookingQueue $queue, KalendiaApi $api): int
    {
        if (! $api->configured() || ! Schema::hasTable('kalendia_booking_requests') || ! Schema::hasTable('kalendia_bookings')) {
            $this->error('La cola de citas de Kalendia todavía no está disponible.');

            return self::FAILURE;
        }

        $result = $queue->processNext();
        $this->info($result === null ? 'No hay solicitudes pendientes para procesar.' : 'Estado de la solicitud: '.$result);

        return self::SUCCESS;
    }
}
