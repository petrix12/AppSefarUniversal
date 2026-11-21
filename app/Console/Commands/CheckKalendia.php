<?php

namespace App\Console\Commands;

use App\Services\KalendiaApi;
use App\Services\KalendiaApiException;
use Illuminate\Console\Command;

class CheckKalendia extends Command
{
    protected $signature = 'kalendia:check';
    protected $description = 'Comprueba conexión, permisos y servicios sin crear clientes, citas ni pagos';

    public function handle(KalendiaApi $api): int
    {
        try {
            $business = $api->request('GET', 'business');
            $connection = $api->request('GET', 'connection');
            $this->line('Conexión: correcta. Zona de la agenda: '.($business['timezone'] ?? 'sin indicar'));
            $ok = ($business['timezone'] ?? null) === config('kalendia.timezone');
            foreach (['services.read', 'staff.read', 'availability.read', 'customers.write', 'customers.properties.write', 'customers.notes.write', 'appointments.create', 'appointments.read', 'appointments.update'] as $scope) {
                $present = in_array($scope, $connection['scopes'] ?? [], true);
                $ok = $ok && $present;
                $this->line($scope.': '.($present ? 'disponible' : 'falta'));
            }
            $prepaid = filled(config('kalendia.prepaid_payment_method'));
            foreach (['Nacionalidad portuguesa por cónyuge', 'Nacionalidad portuguesa para familiares',
                'Nacionalidad española por cónyuge', 'Nacionalidad española para familiares'] as $name) {
                $service = $api->serviceFor($name);
                $free = $api->isFreeService($service);
                $ok = $ok && $service !== null && ($free || $prepaid);
                $this->line($name.': '.($service ? $service['id'].($free ? ' · sin cobro en Kalendia' : ($prepaid ? ' · método externo configurado' : ' · falta método para pago externo')) : 'pendiente de habilitar'));
            }
            $this->line('Método confirmado para servicios con precio en Kalendia: '.($prepaid ? 'configurado' : 'sin configurar; esos servicios permanecen protegidos'));
            $this->line('Solo lectura. No se crearon clientes, citas ni pagos.');
            return $ok ? self::SUCCESS : self::FAILURE;
        } catch (KalendiaApiException $exception) {
            $this->error('No se pudo comprobar Kalendia'.($exception->httpStatus ? ' (HTTP '.$exception->httpStatus.')' : '').'. No se realizaron escrituras.');
            return self::FAILURE;
        }
    }
}
