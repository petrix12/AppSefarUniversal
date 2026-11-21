<?php
namespace App\Http\Controllers;
use App\Models\KalendiaConsultationOrder;
use App\Services\KalendiaConsultationCheckout;
use Illuminate\Http\Request;
class KalendiaStripeWebhookController extends Controller
{
    public function __invoke(Request $request, KalendiaConsultationCheckout $checkout)
    {
        $secret = (string) config('kalendia.stripe_webhook_secret');
        if ($secret === '') return response()->json(['received' => false], 503);
        try { $event = \Stripe\Webhook::constructEvent($request->getContent(), (string) $request->header('Stripe-Signature'), $secret); }
        catch (\Throwable) { return response()->json(['received' => false], 400); }
        if ($event->type !== 'payment_intent.succeeded') return response()->json(['received' => true]);
        $intent = $event->data->object->toArray();
        $order = KalendiaConsultationOrder::where('uuid', data_get($intent, 'metadata.kalendia_order'))->first();
        if ($order) $checkout->confirm($order, $intent);
        return response()->json(['received' => true]);
    }
}
