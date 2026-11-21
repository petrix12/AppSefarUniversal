<?php

namespace App\Services;

use App\Models\Compras;
use App\Models\RegistrationMeetingWebhook;

class KalendiaRegistrationContactSync
{
    public function __construct(private KalendiaApi $api) {}

    private function persist(RegistrationMeetingWebhook $event, array $payload): void
    {
        $changed = RegistrationMeetingWebhook::whereKey($event->id)->where('lease', $event->lease)
            ->update(['payload' => json_encode($payload, JSON_THROW_ON_ERROR), 'updated_at' => now()]);
        if (! $changed) throw new KalendiaApiException();
        $event->payload = $payload;
    }

    public function sync(RegistrationMeetingWebhook $event): int
    {
        $receipt = Compras::with(['factura', 'user'])->find($event->compra_id);
        if (! $receipt || ! $receipt->pagado || ! $receipt->factura
            || (int) $receipt->id_user !== (int) data_get($event->payload, 'cliente_id')
            || (int) $receipt->factura->id_cliente !== (int) $receipt->id_user
            || ! in_array($receipt->factura->met, ['stripe', 'paypal', 'cupon'], true)
            || $receipt->user->hasIndividualClientBlock()) throw new KalendiaApiException();
        $payload = $event->payload;
        $state = $payload['_kalendia'] ?? [];
        if (! isset($state['customer_payload'])) {
            $state['customer_payload'] = array_filter(['name' => $payload['nombre_completo'],
                'email' => $payload['correo_electronico'], 'phone' => $payload['telefono'] ?? null,
                'timezone' => config('kalendia.timezone'), 'properties' => ['cf_nopasaporte' => $payload['pasaporte'] ?? null]],
                fn ($value) => $value !== null && $value !== '');
            $state['payment_note'] = 'Pago de registro confirmado en la App. Servicio: '.$payload['servicio']
                .'. Factura #'.$receipt->factura->id.'. Compra #'.$receipt->id.'. Importe recibido: '
                .number_format((float) $receipt->monto, 2, '.', '').' '.data_get($receipt->metadata, 'display_currency', 'EUR')
                .'. Comprobante: '.url('/viewcomprobante/'.$receipt->factura->id);
            $payload['_kalendia'] = $state;
            $this->persist($event, $payload);
        }
        if (empty($state['customer_id'])) {
            $result = $this->api->request('POST', 'customers', $state['customer_payload'], 'app-registration-customer-'.$event->event_id);
            $id = $result['id'] ?? data_get($result, 'customer.id');
            if (! is_string($id) || ! preg_match('/^cli_[A-Za-z0-9_-]{1,200}$/D', $id)) throw new KalendiaApiException();
            $state['customer_id'] = $id;
            $state['last_http_status'] = $result['_http_status'];
            $payload['_kalendia'] = $state;
            $this->persist($event, $payload);
        }
        if (empty($state['payment_note_id'])) {
            $result = $this->api->request('POST', 'customers/'.$state['customer_id'].'/notes',
                ['text' => $state['payment_note'], 'is_pinned' => false], 'app-registration-payment-'.$event->event_id);
            $id = $result['id'] ?? data_get($result, 'note.id');
            if (! is_string($id) || ! preg_match('/^nt_[A-Za-z0-9_-]{1,200}$/D', $id)) throw new KalendiaApiException();
            $state['payment_note_id'] = $id;
            $state['last_http_status'] = $result['_http_status'];
            $payload['_kalendia'] = $state;
            $this->persist($event, $payload);
        }
        return (int) ($state['last_http_status'] ?? 200);
    }
}
