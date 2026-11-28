<?php
namespace App\Services;

use Illuminate\Support\Arr;

class PlatformCatalog
{
    public const STRIPE_EVENTS = ['charge.succeeded','charge.failed','charge.refunded','charge.captured','charge.updated',
        'payment_intent.succeeded','payment_intent.payment_failed','payment_intent.canceled','payment_intent.processing','payment_intent.requires_action','payment_intent.amount_capturable_updated',
        'customer.created','customer.updated','customer.deleted','customer.subscription.created','customer.subscription.updated','customer.subscription.deleted',
        'invoice.created','invoice.updated','invoice.paid','invoice.payment_failed','invoice.voided','invoice.deleted',
        'refund.created','refund.updated','refund.failed','charge.dispute.created','charge.dispute.updated','charge.dispute.closed',
        'product.created','product.updated','product.deleted','price.created','price.updated','price.deleted'];
    public const KALENDIA_EVENTS = ['appointment.created','appointment.confirmed','appointment.rescheduled','appointment.cancelled','appointment.payment_updated',
        'customer.created','customer.updated','customer.note_created','customer.note_updated','form.submitted','form.submission_updated','form.schema_updated',
        'payment.confirmed','payment.failed','payment.refunded','payment.updated','order.created','order.payment_updated','order.fulfillment_updated','product.updated'];
    public const STRIPE = [
        'charges'=>['Pagos','ch','charges'], 'payment_intents'=>['Intentos de pago','pi','payment_intents'],
        'customers'=>['Representados','cus','customers'], 'invoices'=>['Facturas','in','invoices'],
        'refunds'=>['Reembolsos','re','refunds'], 'subscriptions'=>['Suscripciones','sub','subscriptions'],
        'disputes'=>['Disputas','dp','disputes'], 'products'=>['Productos','prod','products'], 'prices'=>['Precios','price','prices'],
    ];
    public const KALENDIA = [
        'customers'=>['Representados','cli','customers'], 'appointments'=>['Citas','apt','appointments'],
        'services'=>['Servicios','svc','services'], 'staff'=>['Profesionales','stf','staff'], 'resources'=>['Recursos','res','resources'],
        'payments'=>['Pagos','pay','payments'], 'orders'=>['Pedidos','ord','orders'], 'products'=>['Productos','prd','products'],
        'categories'=>['Categorías',null,'categories'], 'inventory'=>['Inventario',null,'inventory'], 'coupons'=>['Cupones',null,'coupons'],
        'forms'=>['Formularios','frm','forms/schemas'], 'submissions'=>['Respuestas de formularios','sub','forms/submissions'],
    ];
    public function resources(string $provider): array
    {
        abort_unless(in_array($provider, ['stripe','kalendia'], true), 404);
        return $provider === 'stripe' ? self::STRIPE : self::KALENDIA;
    }
    public function definition(string $provider, string $resource): array
    {
        $definitions = $this->resources($provider); abort_unless(isset($definitions[$resource]), 404); return $definitions[$resource];
    }
    public function validId(string $provider, string $resource, mixed $id): bool
    {
        $prefix = $this->definition($provider, $resource)[1];
        return is_string($id) && strlen($id) <= 190 && (bool) preg_match($prefix
            ? '/^'.preg_quote($prefix, '/').'_[A-Za-z0-9_-]+$/D' : '/^[A-Za-z0-9_-]+$/D', $id);
    }
    // Explicit projection: never persist card data, tokens, client_secret or webhook signing secrets.
    public function project(string $provider, string $resource, array $data): array
    {
        $common = ['id','object','name','email','phone','description','status','created','updated_at','deleted','active','currency','amount',
            'customer','customer_name','customer_email','customer_phone','client_id','customer_id','service_id','service_name','staff_id','staff_name',
            'starts_at','ends_at','date','time','timezone','virtual_meeting_url','virtual_provider','duration_minutes','price','quantity','stock',
            'total','subtotal','payment_id','payment_status','order_id','appointment_id','reference','number','form_id','product_id','title','code'];
        $extra = $provider === 'stripe'
            ? ['paid','refunded','amount_refunded','amount_captured','captured','amount_due','amount_paid','amount_remaining','amount_received',
                'amount_capturable','payment_intent','charge','latest_charge','customer_balance','unit_amount','recurring','cancel_at_period_end',
                'canceled_at','current_period_end','due_date','reason','livemode','receipt_number']
            : ['address','properties','fields','values','text','is_pinned','available','modality','category_id','discount_type','discount_value',
                'max_discount','minimum_purchase','global_limit','buyer_limit','scope_type','scope','rules','schema_version','type','unit','location_type'];
        $result = Arr::only($data, array_merge($common, $extra));
        // Customer expansions are reduced to a stable ID; never retain arbitrary nested provider objects.
        if (is_array($result['customer'] ?? null)) $result['customer'] = $result['customer']['id'] ?? null;
        if ($provider === 'stripe') {
            $metadata = Arr::only(is_array($data['metadata'] ?? null) ? $data['metadata'] : [], ['sefar_flow','kalendia_order','user_id','checkout_token','receipt_id','app_operation']);
            if ($metadata) $result['metadata'] = $metadata;
        }
        return $result;
    }
    public function stripeEvent(string $type): ?string
    {
        if (str_starts_with($type, 'customer.subscription.')) return 'subscriptions';
        if (str_starts_with($type, 'charge.dispute.')) return 'disputes';
        if (str_starts_with($type, 'charge.refund.')) return 'refunds';
        $prefix = explode('.', $type)[0];
        return ['charge'=>'charges','payment_intent'=>'payment_intents','customer'=>'customers','invoice'=>'invoices',
            'refund'=>'refunds','product'=>'products','price'=>'prices'][$prefix] ?? null;
    }
    public function kalendiaEvent(string $type): ?string
    {
        return match ($type) {
            'customer.created','customer.updated','customer.note_created','customer.note_updated'=>'customers',
            'appointment.created','appointment.confirmed','appointment.rescheduled','appointment.cancelled','appointment.payment_updated'=>'appointments',
            'payment.confirmed','payment.failed','payment.refunded','payment.updated'=>'payments',
            'order.created','order.payment_updated','order.fulfillment_updated'=>'orders',
            'product.updated'=>'products', 'form.submitted','form.submission_updated'=>'submissions', 'form.schema_updated'=>'forms',
            default=>null,
        };
    }
}
