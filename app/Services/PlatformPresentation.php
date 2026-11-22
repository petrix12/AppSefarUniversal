<?php
namespace App\Services;

class PlatformPresentation
{
    public static function status(?string $status): string
    {
        return ['pending'=>'Pendiente','processing'=>'En proceso','succeeded'=>'Confirmado','synced'=>'Sincronizado','retry'=>'Reintento pendiente',
            'review'=>'Requiere revisión','uncertain'=>'Sin confirmar','rejected'=>'Rechazado','paid'=>'Pagado','refunded'=>'Reembolsado','partially_refunded'=>'Reembolso parcial','failed'=>'Fallido',
            'canceled'=>'Cancelado','cancelled'=>'Cancelado','confirmed'=>'Confirmado','active'=>'Activo','inactive'=>'Inactivo','deleted'=>'Eliminado',
            'requires_payment_method'=>'Pendiente de método de pago','requires_confirmation'=>'Pendiente de confirmación','requires_action'=>'Pendiente del cliente',
            'requires_capture'=>'Pendiente de captura','draft'=>'Borrador','open'=>'Abierta','void'=>'Anulada','past_due'=>'Vencida',
            'completed'=>'Completado','no_show'=>'No asistió'][$status??'']??($status?:'—');
    }
    public static function amount(string $provider,array $data): string
    {
        $amount=$data['amount']??$data['amount_due']??$data['unit_amount']??$data['total']??$data['price']??null;
        if (!is_numeric($amount)) return '—';
        $currency=$data['currency']??null;
        if (!$currency) return (string)$amount;
        return $provider==='stripe' ? StripeMoney::display((int)$amount,(string)$currency) : number_format((float)$amount,2,',','.').' '.strtoupper((string)$currency);
    }
    public static function label(string $key): string
    {
        return ['id'=>'Identificador','name'=>'Nombre','email'=>'Correo','phone'=>'Teléfono','description'=>'Descripción','status'=>'Estado','currency'=>'Moneda',
            'captured'=>'Capturado','amount_captured'=>'Importe capturado','amount_capturable'=>'Importe pendiente de captura','unit_amount'=>'Precio unitario','service_id'=>'Servicio','staff_id'=>'Profesional','payment_id'=>'Pago de Kalendia','customer_id'=>'Cliente de Kalendia','appointment_id'=>'Cita','form_id'=>'Formulario','cancel_at_period_end'=>'Cancelación al final del periodo','amount'=>'Importe','amount_refunded'=>'Importe reembolsado','amount_due'=>'Importe facturado','amount_paid'=>'Importe pagado',
            'amount_remaining'=>'Saldo pendiente','amount_received'=>'Importe recibido','number'=>'Número de factura','paid'=>'Pagado','refunded'=>'Reembolsado',
            'customer'=>'Cliente','customer_name'=>'Nombre del cliente','customer_email'=>'Correo del cliente','client_id'=>'Cliente de Kalendia',
            'service_name'=>'Servicio','staff_name'=>'Profesional','timezone'=>'Zona horaria','starts_at'=>'Inicio','ends_at'=>'Fin','address'=>'Dirección',
            'price'=>'Precio','duration_minutes'=>'Duración en minutos','properties'=>'Propiedades','values'=>'Respuestas','fields'=>'Campos',
            'created'=>'Fecha de creación','updated_at'=>'Fecha de actualización','payment_intent'=>'Intento de pago','charge'=>'Pago original',
            'reason'=>'Motivo','quantity'=>'Cantidad','stock'=>'Existencias','active'=>'Activo','recurring'=>'Periodicidad','metadata'=>'Referencias de la App'][$key]??str_replace('_',' ',$key);
    }
    public static function value(string $provider,string $key,mixed $value,array $data): string
    {
        if (is_array($value)) return json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        if (is_bool($value)) return $value ? 'Sí' : 'No';
        if (in_array($key,['status','payment_status'],true)) return self::status($value);
        if ($provider==='stripe' && is_numeric($value) && isset($data['currency'])
            && (str_starts_with($key,'amount') || in_array($key,['unit_amount','customer_balance'],true))) return StripeMoney::display((int)$value,$data['currency']);
        if (in_array($key,['created','updated_at','starts_at','ends_at','due_date','current_period_end','canceled_at'],true)) {
            try { return (is_numeric($value) ? \Carbon\Carbon::createFromTimestampUTC((int)$value) : \Carbon\Carbon::parse($value))
                ->timezone(KalendiaTimezone::preferred())->format('d/m/Y H:i').' · '.KalendiaTimezone::preferred(); } catch (\Throwable) {}
        }
        return (string)$value;
    }
    public static function instant(string $key,mixed $value): ?string
    {
        if (!in_array($key,['created','updated_at','starts_at','ends_at','due_date','current_period_end','canceled_at'],true) || $value===null) return null;
        try { return (is_numeric($value) ? \Carbon\Carbon::createFromTimestampUTC((int)$value) : \Carbon\Carbon::parse($value))->toIso8601String(); }
        catch (\Throwable) { return null; }
    }
}
