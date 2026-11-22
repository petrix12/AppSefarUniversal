<?php
namespace App\Http\Controllers;
use App\Models\KalendiaConsultationOrder;
use App\Services\KalendiaConsultationCheckout;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
class KalendiaStripeWebhookController extends Controller
{
    public function __invoke(Request $request, KalendiaConsultationCheckout $checkout)
    {
        $secret = (string) config('kalendia.stripe_webhook_secret');
        if ($secret === '') return response()->json(['received' => false], 503);
        try { $event = \Stripe\Webhook::constructEvent($request->getContent(), (string) $request->header('Stripe-Signature'), $secret); }
        catch (\Throwable) { return response()->json(['received' => false], 400); }
        if ($event->type !== 'payment_intent.succeeded') return response()->json(['received' => true, 'ignored' => 'event_type']);
        // This endpoint belongs to our own account, not a Stripe Connect platform.
        if (filled($event->account ?? null)) return response()->json(['received' => true, 'ignored' => 'connected_account']);
        $key = (string) config('services.stripe.secret');
        $live = str_starts_with($key, 'sk_live_') || str_starts_with($key, 'rk_live_');
        $test = str_starts_with($key, 'sk_test_') || str_starts_with($key, 'rk_test_');
        if (($live || $test) && ($event->livemode ?? null) !== $live) return response()->json(['received' => true, 'ignored' => 'mode']);
        $intent = $event->data->object->toArray();
        $reference = data_get($intent, 'metadata.kalendia_order');
        if (($intent['object'] ?? null) !== 'payment_intent' || !is_string($reference) || !Str::isUuid($reference)) {
            return response()->json(['received' => true, 'ignored' => 'unrelated_payment']);
        }
        $flow = data_get($intent, 'metadata.sefar_flow');
        if ($flow !== null && $flow !== KalendiaConsultationOrder::SOURCE) return response()->json(['received' => true, 'ignored' => 'other_flow']);
        $order = KalendiaConsultationOrder::where('uuid', $reference)->first();
        if (! $order) return response()->json(['received' => true, 'ignored' => 'order_not_found']);
        if (($order->payment_intent_id && ($intent['id'] ?? null) !== $order->payment_intent_id)
            || (string) data_get($intent, 'metadata.user_id') !== (string) $order->user_id) {
            return response()->json(['received' => true, 'ignored' => 'payment_reference']);
        }
        $checkout->confirm($order, $intent);
        return response()->json(['received' => true]);
    }
}
