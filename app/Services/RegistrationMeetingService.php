<?php

namespace App\Services;

use App\Models\Compras;
use App\Models\Factura;
use App\Models\RegistrationMeetingWebhook;
use App\Models\Servicio;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class RegistrationMeetingService
{
    public static function hasBookedInitialMeeting(User $user): bool
    {
        if (! Schema::hasTable('kalendia_bookings')) return false;
        return \App\Models\KalendiaBooking::where('user_id', $user->id)->whereNotNull('remote_id')
            ->whereHas('purchase', fn ($query) => $query->where(fn ($q) => $q->whereNull('source')
                ->orWhere('source', '!=', \App\Models\KalendiaConsultationOrder::SOURCE)))->exists();
    }
    public static function supports(?string $service): bool
    {
        $value = self::normalize($service);

        return in_array($value, [
            'nacionalidad portuguesa por conyuge', 'nacionalidad portuguesa para familiares',
            'nacionalidad espanola por conyuge', 'nacionalidad espanola para familiares',
        ], true);
    }

    public static function normalize(?string $service): string
    {
        return preg_replace('/\\s+/', ' ', Str::lower(Str::ascii(trim((string) $service))));
    }

    public function contextForService(Servicio $service): ?array
    {
        if (! self::supports($service->id_hubspot) && ! self::supports($service->nombre)) return null;
        return ['service_code' => $service->id_hubspot, 'service_id' => $service->id,
            'service' => self::supports($service->id_hubspot) ? $service->id_hubspot : $service->nombre];
    }

    public static function supportsUser(User $user): bool
    {
        if (self::supports($user->servicio)) return true;
        if (! filled($user->servicio) || ! Schema::hasTable('servicios')) return false;
        return self::supports(Servicio::where('id_hubspot', $user->servicio)->value('nombre'));
    }

    /** Called inside the confirmed receipt transaction; the scheduler sends after commit. */
    public function queueConfirmed(Compras $receipt, Factura $invoice): void
    {
        if ($receipt->source === \App\Models\KalendiaConsultationOrder::SOURCE) return;
        $meeting = data_get($receipt->metadata, 'registration_contact') ?? data_get($receipt->metadata, 'registration_meeting');
        if (! is_array($meeting) && self::supports($receipt->servicio_hs_id)) {
            $meeting = ['service' => $receipt->servicio_hs_id, 'service_code' => $receipt->servicio_hs_id, 'service_id' => $receipt->servicio_id];
        }
        if (! is_array($meeting) || ! self::supports($meeting['service'] ?? null)) return;
        if (! $receipt->pagado || $receipt->hash_factura !== $invoice->hash_factura
            || (int) $receipt->id_user !== (int) $invoice->id_cliente
            || (string) $receipt->servicio_hs_id !== (string) ($meeting['service_code'] ?? '')
            || ! in_array($invoice->met, ['stripe', 'paypal', 'cupon'], true)) return;
        $user = $receipt->user;
        RegistrationMeetingWebhook::firstOrCreate(['compra_id' => $receipt->id], [
            'event_id' => (string) Str::uuid(),
            'payload' => [
                'evento' => 'registro.pagado', 'formulario' => '000', 'cliente_id' => $user->id,
                'compra_id' => $receipt->id, 'nombre' => $user->nombres, 'apellido' => $user->apellidos,
                'nombre_completo' => $user->name, 'pasaporte' => $user->passport,
                'telefono' => $user->phone, 'correo_electronico' => $user->email,
                'servicio' => $meeting['service'], 'servicio_id' => $meeting['service_id'],
                'factura_id' => $invoice->id, 'monto_pagado' => number_format((float) $receipt->monto, 2, '.', ''),
                'moneda' => data_get($receipt->metadata, 'display_currency', 'EUR'),
                'referencia_pago' => data_get($receipt->metadata, 'gateway_reference'),
                'pago_confirmado_en' => ($receipt->paid_at ?: now())->toIso8601String(),
            ],
        ]);
    }
}
