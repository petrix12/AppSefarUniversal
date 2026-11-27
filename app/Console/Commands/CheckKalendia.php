<?php

namespace App\Console\Commands;

use App\Services\KalendiaApi;
use App\Services\KalendiaApiException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\{DB, Schema};

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
            foreach (['Nacionalidad portuguesa por cónyuge', 'Nacionalidad portuguesa para familiares',
                'Nacionalidad española por cónyuge', 'Nacionalidad española para familiares'] as $name) {
                $service = $api->serviceFor($name);
                $ready = $api->canReservePaidService($service);
                $ok = $ok && $ready;
                $this->line($name.': '.($ready ? $service['id'].' · reserva con pago confirmado en App Sefar' : 'servicio o precio pendiente de confirmar'));
            }
            $this->line('Las reservas pagadas en App Sefar omiten payment_method y conservan el comprobante en las notas.');
            $this->line('Webhook de Kalendia: '.(filled(config('kalendia.webhook_secret')) ? 'secreto configurado' : 'pendiente de configurar KALENDIA_WEBHOOK_SECRET'));
            $this->line('Receptor de citas: '.rtrim(config('app.url'), '/').'/api/webhooks/kalendia');
            $this->line('Restricción adicional de negocio: '.(config('kalendia.webhook_business_id') ?: 'firma propia del webhook y API aislada al negocio'));
            $this->line('Webhook de Stripe para consultas: '.(filled(config('kalendia.stripe_webhook_secret')) ? 'secreto propio configurado' : 'falta STRIPE_KALENDIA_WEBHOOK_SECRET'));
            if (Schema::hasTable('kalendia_webhook_events')) {
                $states = DB::table('kalendia_webhook_events')->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status');
                $this->line('Eventos de Kalendia: '.(($states['pending'] ?? 0) + ($states['processing'] ?? 0) + ($states['retry'] ?? 0)).' pendientes; '
                    .($states['synced'] ?? 0).' sincronizados; '.($states['review'] ?? 0).' requieren revisión.');
            } else $this->line('Eventos de Kalendia: falta aplicar la migración del receptor.');
            $this->line('Solo lectura. No se crearon clientes, citas ni pagos.');
            return $ok ? self::SUCCESS : self::FAILURE;
        } catch (KalendiaApiException $exception) {
            $this->error('No se pudo comprobar Kalendia'.($exception->httpStatus ? ' (HTTP '.$exception->httpStatus.')' : '').'. No se realizaron escrituras.');
            return self::FAILURE;
        }
    }
}
