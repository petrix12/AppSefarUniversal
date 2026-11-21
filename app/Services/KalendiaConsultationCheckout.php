<?php
namespace App\Services;

use App\Models\{Compras, KalendiaConsultationOffer, KalendiaConsultationOrder, User};
use App\Services\Payments\{ConfirmedPaymentInvoiceService, RegistrationPaymentReceiptService};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class KalendiaConsultationCheckout
{
    public function __construct(private KalendiaApi $api, private KalendiaStripeGateway $stripe) {}
    public function assertClient(User $user): void
    {
        abort_unless($user->hasRole('Cliente') && (int) $user->pay === 2 && (int) $user->contrato === 1, 403,
            'Completa primero tu registro y contrato.');
    }
    public function configured(): bool
    {
        return $this->api->configured() && filled(config('services.stripe.secret')) && filled(config('services.stripe.key'));
    }
    public function canReserve(string $remoteId): bool
    {
        $service = $this->api->serviceById($remoteId);
        return $service && ($this->api->isFreeService($service) || filled(config('kalendia.prepaid_payment_method')));
    }
    public function order(User $user, KalendiaConsultationOffer $offer, string $key): KalendiaConsultationOrder
    {
        $this->assertClient($user);
        if (! $this->configured() || ! $offer->hasPrice() || ! $this->canReserve($offer->remote_service_id)) {
            throw ValidationException::withMessages(['consultation' => 'Esta consulta todavía no está habilitada para contratar.']);
        }
        return DB::transaction(function () use ($user, $offer, $key) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $offer = KalendiaConsultationOffer::whereKey($offer->id)->lockForUpdate()->firstOrFail();
            abort_unless($offer->hasPrice(), 422);
            $existing = KalendiaConsultationOrder::where('checkout_key', $key)->first();
            if ($existing) {
                abort_unless((int) $existing->user_id === (int) $user->id && (int) $existing->offer_id === (int) $offer->id, 409);
                return $existing;
            }
            return KalendiaConsultationOrder::create(['uuid' => (string) Str::uuid(), 'checkout_key' => $key,
                'user_id' => $user->id, 'offer_id' => $offer->id, 'name' => $offer->name, 'type' => $offer->type,
                'remote_service_id' => $offer->remote_service_id, 'amount' => $offer->price, 'currency' => 'EUR']);
        });
    }
    public function begin(KalendiaConsultationOrder $order): array
    {
        if ($order->status === 'paid') return ['paid' => true];
        if (! $this->configured()) throw ValidationException::withMessages(['payment' => 'El pago todavía no está disponible.']);
        if ($order->payment_intent_id) {
            try { $intent = $this->stripe->retrieve($order->payment_intent_id); }
            catch (\Throwable) { throw ValidationException::withMessages(['payment' => 'No podemos comprobar el pago en este momento.']); }
            $this->confirm($order, $intent);
            return ['paid' => $order->fresh()->status === 'paid', 'client_secret' => $intent['client_secret'] ?? null];
        }
        if (! $this->canReserve($order->remote_service_id)) throw ValidationException::withMessages(['payment' => 'La agenda de esta consulta todavía no está habilitada para contratar.']);
        $order = DB::transaction(function () use ($order) {
            $row = KalendiaConsultationOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($row->payment_intent_id) return $row;
            if ($row->leased_until?->isFuture()) throw ValidationException::withMessages(['payment' => 'Estamos preparando el pago. Espera unos segundos.']);
            if ($row->payment_started_at && $row->payment_started_at->lt(now()->subHours(23))) {
                throw ValidationException::withMessages(['payment' => 'Este intento requiere revisión antes de volver a cobrar. Contacta con nuestro equipo.']);
            }
            $row->forceFill(['status' => 'creating', 'lease' => (string) Str::uuid(), 'leased_until' => now()->addMinute(),
                'payment_started_at' => $row->payment_started_at ?: now()])->save();
            return $row;
        });
        if ($order->payment_intent_id) return $this->begin($order);
        try {
            $intent = $this->stripe->create(['amount' => (int) round((float) $order->amount * 100), 'currency' => 'eur',
                'automatic_payment_methods' => ['enabled' => true], 'description' => 'Consulta Sefar: '.$order->name,
                'metadata' => ['kalendia_order' => $order->uuid, 'user_id' => (string) $order->user_id]], 'kalendia-order-'.$order->uuid);
            if (! is_string($intent['id'] ?? null) || ! preg_match('/^pi_[A-Za-z0-9]+$/D', $intent['id'])) throw new \RuntimeException();
            if (! KalendiaConsultationOrder::whereKey($order->id)->where('lease', $order->lease)->where('status', '!=', 'paid')->update([
                'payment_intent_id' => $intent['id'], 'status' => 'pending', 'lease' => null, 'leased_until' => null])) {
                if ($order->fresh()->status === 'paid') return ['paid' => true];
                throw new \RuntimeException();
            }
        } catch (\Throwable) {
            if ($order->fresh()->status === 'paid') return ['paid' => true];
            KalendiaConsultationOrder::whereKey($order->id)->where('lease', $order->lease)->update(['status' => 'uncertain', 'lease' => null, 'leased_until' => null]);
            throw ValidationException::withMessages(['payment' => 'No tenemos confirmación de la pasarela. Reintenta esta misma compra; no inicies otro pago.']);
        }
        $this->confirm($order->fresh(), $intent);
        return ['paid' => $order->fresh()->status === 'paid', 'client_secret' => $intent['client_secret'] ?? null];
    }
    public function verify(KalendiaConsultationOrder $order): void
    {
        if ($order->status === 'paid' || ! $order->payment_intent_id) return;
        $this->confirm($order, $this->stripe->retrieve($order->payment_intent_id));
    }
    public function confirm(KalendiaConsultationOrder $order, array $intent): void
    {
        $paid = ($intent['status'] ?? null) === 'succeeded';
        if (! preg_match('/^pi_[A-Za-z0-9]+$/D', (string) ($intent['id'] ?? ''))
            || ($order->payment_intent_id && $intent['id'] !== $order->payment_intent_id)
            || (! $order->payment_intent_id && ! $order->payment_started_at) || ($intent['currency'] ?? null) !== 'eur'
            || ($paid && (int) ($intent['amount_received'] ?? -1) !== (int) round((float) $order->amount * 100))
            || (int) ($intent['amount'] ?? -1) !== (int) round((float) $order->amount * 100)
            || data_get($intent, 'metadata.kalendia_order') !== $order->uuid
            || (string) data_get($intent, 'metadata.user_id') !== (string) $order->user_id) {
            throw ValidationException::withMessages(['payment' => 'El pago necesita revisión; sus referencias no coinciden con la compra.']);
        }
        if (! $paid) return;
        DB::transaction(function () use ($order, $intent) {
            User::whereKey($order->user_id)->lockForUpdate()->firstOrFail();
            $row = KalendiaConsultationOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($row->payment_intent_id && $row->payment_intent_id !== $intent['id']) abort(409);
            if ($row->compra_id) return;
            $invoice = app(ConfirmedPaymentInvoiceService::class)->getOrCreate($row->user_id, 'stripe', $intent['id'], 'kalendia-'.$row->uuid);
            $purchase = Compras::create(['id_user' => $row->user_id, 'source' => KalendiaConsultationOrder::SOURCE,
                'servicio_hs_id' => $row->name, 'descripcion' => 'Consulta: '.$row->name, 'pagado' => 1,
                'monto' => $row->amount, 'hash_factura' => $invoice->hash_factura, 'paid_at' => now(),
                'metadata' => ['display_currency' => 'EUR', 'kalendia_consultation' => ['offer_id' => $row->offer_id,
                    'order_id' => $row->id, 'service_name' => $row->name, 'type' => $row->type, 'remote_service_id' => $row->remote_service_id]]]);
            app(RegistrationPaymentReceiptService::class)->record($invoice, [$purchase], $intent['id']);
            $row->forceFill(['payment_intent_id' => $intent['id'], 'compra_id' => $purchase->id, 'status' => 'paid', 'paid_at' => now(),
                'lease' => null, 'leased_until' => null])->save();
        });
    }
}
