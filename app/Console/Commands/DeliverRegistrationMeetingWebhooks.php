<?php

namespace App\Console\Commands;

use App\Models\RegistrationMeetingWebhook;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class DeliverRegistrationMeetingWebhooks extends Command
{
    protected $signature = 'registration:deliver-meeting-webhooks {--limit=20} {--test : Envía solo un ejemplo ficticio a la URL de pruebas}';
    protected $description = 'Sincroniza el contacto y la referencia del pago de registro con Kalendia';

    public function handle(): int
    {
        if ($this->option('test')) return $this->sendTest();
        if (! config('registration_meetings.enabled')) return self::SUCCESS;
        $native = app(\App\Services\KalendiaApi::class)->configured();
        $notify = (bool) config('registration_meetings.notify_n8n');
        if (! $native && ! $notify) {
            $this->error('Falta configurar Kalendia. Se conservan los eventos pendientes.');
            return self::FAILURE;
        }
        $url = (string) config('registration_meetings.url');
        if ($notify && (parse_url($url, PHP_URL_SCHEME) !== 'https' || ! parse_url($url, PHP_URL_HOST)
            || str_contains((string) parse_url($url, PHP_URL_PATH), '/webhook-test/'))) {
            $this->error('Configura una URL HTTPS de producción para el webhook.');
            return self::FAILURE;
        }
        for ($i = 0; $i < max(1, min(100, (int) $this->option('limit'))); $i++) {
            $event = DB::transaction(function () {
                $event = RegistrationMeetingWebhook::where('status', 'pending')
                    ->where(fn ($q) => $q->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now()))
                    ->where(fn ($q) => $q->whereNull('leased_until')->orWhere('leased_until', '<=', now()))
                    ->orderBy('id')->lockForUpdate()->first();
                if (! $event) return null;
                $event->forceFill(['lease' => (string) Str::uuid(), 'leased_until' => now()->addMinutes(2),
                    'attempts' => $event->attempts + 1])->save();
                return $event;
            });
            if (! $event) break;
            $status = null;
            try {
                $status = $native ? app(\App\Services\KalendiaRegistrationContactSync::class)->sync($event) : 200;
                $sent = true;
                if ($notify) {
                    // This event creates a contact after payment, never an appointment from an old date.
                    $payload = \Illuminate\Support\Arr::except($event->payload, ['_kalendia', 'reunion_fecha_hora', 'reunion_zona_horaria', 'reunion_utc']);
                    $response = Http::acceptJson()->asJson()->connectTimeout(3)->timeout(10)->withoutRedirecting()
                        ->withHeaders(['Idempotency-Key' => $event->event_id])
                        ->post($url, ['event_id' => $event->event_id] + $payload);
                    $status = $response->status();
                    $sent = $response->successful();
                }
            } catch (\App\Services\KalendiaApiException $exception) {
                $status = $exception->httpStatus;
                $sent = false;
            } catch (\Throwable) {
                $sent = false;
            }
            RegistrationMeetingWebhook::whereKey($event->id)->where('lease', $event->lease)->update([
                'status' => $sent ? 'sent' : ($event->attempts >= 8 ? 'failed' : 'pending'),
                'last_http_status' => $status, 'sent_at' => $sent ? now() : null,
                'lease' => null, 'leased_until' => null,
                'next_attempt_at' => $sent ? null : now()->addSeconds(min(3600, 30 * (2 ** $event->attempts))),
                'updated_at' => now(),
            ]);
            $this->line('Evento '.$event->event_id.': '.($sent ? 'enviado' : 'pendiente de revisión/reintento'));
        }

        return self::SUCCESS;
    }

    private function sendTest(): int
    {
        $url = (string) config('registration_meetings.test_url');
        if (parse_url($url, PHP_URL_SCHEME) !== 'https' || ! parse_url($url, PHP_URL_HOST)
            || ! str_contains((string) parse_url($url, PHP_URL_PATH), '/webhook-test/')) {
            $this->error('La URL de prueba debe usar HTTPS y /webhook-test/.');
            return self::FAILURE;
        }
        $eventId = (string) Str::uuid();
        try {
            $response = Http::acceptJson()->asJson()->connectTimeout(3)->timeout(10)->withoutRedirecting()
                ->withHeaders(['Idempotency-Key' => $eventId])->post($url, [
                    'event_id' => $eventId, 'evento' => 'registro.pagado', 'formulario' => '000', 'prueba' => true,
                    'cliente_id' => null, 'compra_id' => null, 'nombre' => 'PRUEBA', 'apellido' => 'FICTICIA',
                    'nombre_completo' => 'PRUEBA FICTICIA', 'pasaporte' => 'PRUEBA-SIN-VALIDEZ',
                    'telefono' => '000000000', 'correo_electronico' => 'prueba@example.invalid',
                    'servicio' => 'Nacionalidad portuguesa por cónyuge', 'servicio_id' => null,
                    'pago_confirmado_en' => now()->toIso8601String(),
                ]);
            $this->line('Prueba ficticia: HTTP '.$response->status().'. No se consultaron ni modificaron pagos.');
            return $response->successful() ? self::SUCCESS : self::FAILURE;
        } catch (\Throwable) {
            $this->error('No se pudo conectar con el webhook de pruebas. No se consultaron ni modificaron pagos.');
            return self::FAILURE;
        }
    }
}
