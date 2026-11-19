<?php

namespace App\Services;

use App\Models\Compras;
use App\Models\Factura;
use App\Models\RegistrationMeetingWebhook;
use App\Models\Servicio;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RegistrationMeetingService
{
    public static function supports(?string $service): bool
    {
        $value = Str::lower(Str::ascii(trim((string) $service)));
        $value = preg_replace('/\s+/', ' ', $value);

        return in_array($value, [
            'nacionalidad portuguesa por conyuge', 'nacionalidad portuguesa para familiares',
            'nacionalidad espanola por conyuge', 'nacionalidad espanola para familiares',
        ], true);
    }

    public function validateMeeting(array $input, Servicio $service): ?array
    {
        if (! self::supports($service->id_hubspot) && ! self::supports($service->nombre)) {
            return null;
        }
        $data = Validator::make($input, [
            'meeting_at' => ['required', 'date_format:Y-m-d\TH:i'],
            'meeting_timezone' => ['required', 'timezone:all'],
            'phone' => ['required', 'string', 'max:255'],
        ], [
            'meeting_at.required' => 'Selecciona la fecha y hora de la reunión.',
            'meeting_at.date_format' => 'La fecha y hora de reunión no son válidas.',
            'meeting_timezone.required' => 'Selecciona la zona horaria de la reunión.',
        ])->validate();
        $date = CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $data['meeting_at'], $data['meeting_timezone']);
        if ($date->format('Y-m-d\TH:i') !== $data['meeting_at'] || $date->isPast()) {
            throw ValidationException::withMessages(['meeting_at' => 'La reunión debe tener una fecha y hora futuras válidas.']);
        }

        return ['requested_at' => $date->utc()->toIso8601String(), 'timezone' => $data['meeting_timezone'],
            'local_at' => $date->toIso8601String(), 'service_code' => $service->id_hubspot,
            'service' => self::supports($service->id_hubspot) ? $service->id_hubspot : $service->nombre, 'service_id' => $service->id];
    }

    /** Called inside the confirmed receipt transaction; the scheduler sends after commit. */
    public function queueConfirmed(Compras $receipt, Factura $invoice): void
    {
        $meeting = data_get($receipt->metadata, 'registration_meeting');
        if (! is_array($meeting) || ! self::supports($meeting['service'] ?? null)) return;
        if (! $receipt->pagado || $receipt->hash_factura !== $invoice->hash_factura
            || (int) $receipt->id_user !== (int) $invoice->id_cliente
            || (string) $receipt->servicio_hs_id !== (string) ($meeting['service_code'] ?? '')
            || ! in_array($invoice->met, ['stripe', 'paypal', 'cupon'], true)) return;
        $user = $receipt->user;
        $date = CarbonImmutable::parse($meeting['requested_at'])->setTimezone($meeting['timezone']);
        RegistrationMeetingWebhook::firstOrCreate(['compra_id' => $receipt->id], [
            'event_id' => (string) Str::uuid(),
            'payload' => [
                'evento' => 'registro.pagado', 'formulario' => '000', 'cliente_id' => $user->id,
                'compra_id' => $receipt->id, 'nombre' => $user->nombres, 'apellido' => $user->apellidos,
                'nombre_completo' => $user->name, 'pasaporte' => $user->passport,
                'telefono' => $user->phone, 'correo_electronico' => $user->email,
                'servicio' => $meeting['service'], 'servicio_id' => $meeting['service_id'],
                'reunion_fecha_hora' => $date->toIso8601String(), 'reunion_zona_horaria' => $meeting['timezone'],
                'reunion_utc' => $date->utc()->toIso8601String(),
                'pago_confirmado_en' => ($receipt->paid_at ?: now())->toIso8601String(),
            ],
        ]);
    }
}
