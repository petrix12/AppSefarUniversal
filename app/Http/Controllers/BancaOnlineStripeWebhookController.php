<?php

namespace App\Http\Controllers;

use App\Models\Compras;
use App\Services\BancaOnlineCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BancaOnlineStripeWebhookController extends Controller
{
    public function __construct(private BancaOnlineCatalog $catalog)
    {
    }

    public function __invoke(Request $request)
    {
        $payload = $request->getContent();
        $signature = (string) $request->header('Stripe-Signature');
        $secret = (string) config('services.stripe.banca_online_webhook_secret');

        if ($secret !== '') {
            try {
                $event = \Stripe\Webhook::constructEvent($payload, $signature, $secret);
            } catch (\Throwable $e) {
                Log::warning('Banca Online Stripe webhook signature rejected.', [
                    'error' => $e->getMessage(),
                ]);

                return response()->json(['received' => false], 400);
            }
        } else {
            $decoded = json_decode($payload, true);

            if (! is_array($decoded)) {
                return response()->json(['received' => false], 400);
            }

            $event = \Stripe\Event::constructFrom($decoded);
        }

        $object = $event->data->object ?? null;
        $checkoutToken = $this->checkoutTokenFromStripeObject($object);

        if (! $checkoutToken) {
            return response()->json(['received' => true, 'ignored' => 'missing_checkout_token']);
        }

        $purchases = $this->purchasesForCheckoutToken($checkoutToken);

        if ($purchases->isEmpty()) {
            return response()->json(['received' => true, 'ignored' => 'checkout_not_found']);
        }

        $status = match ($event->type) {
            'invoice.payment_succeeded' => 'installment_paid',
            'invoice.payment_failed' => 'installment_failed',
            'invoice.upcoming' => 'installment_upcoming',
            'customer.subscription.deleted' => 'installment_subscription_deleted',
            default => null,
        };

        if (! $status) {
            return response()->json(['received' => true, 'ignored' => 'event_type']);
        }

        $purchases->each(function (Compras $purchase) use ($event, $object, $status) {
            $metadata = $purchase->metadata ?? [];
            $events = collect($metadata['stripe_webhook_events'] ?? [])
                ->push([
                    'id' => $event->id,
                    'type' => $event->type,
                    'status' => $status,
                    'amount_due' => isset($object->amount_due) ? ((float) $object->amount_due / 100) : null,
                    'amount_paid' => isset($object->amount_paid) ? ((float) $object->amount_paid / 100) : null,
                    'currency' => isset($object->currency) ? strtoupper((string) $object->currency) : null,
                    'invoice_id' => $object->id ?? null,
                    'created_at' => now()->toIso8601String(),
                ])
                ->take(-20)
                ->values()
                ->all();

            $metadata['stripe_webhook_events'] = $events;
            $metadata['installment_status'] = $status;
            $metadata['installment_status_at'] = now()->toIso8601String();

            $purchase->forceFill(['metadata' => $metadata])->save();
        });

        return response()->json(['received' => true]);
    }

    private function purchasesForCheckoutToken(string $checkoutToken)
    {
        $query = Compras::where('source', $this->catalog->source());

        try {
            return (clone $query)->where('metadata->checkout_token', $checkoutToken)->get();
        } catch (\Throwable $e) {
            return $query
                ->where('created_at', '>=', now()->subYear())
                ->get()
                ->filter(fn (Compras $purchase) => ($purchase->metadata['checkout_token'] ?? null) === $checkoutToken)
                ->values();
        }
    }

    private function checkoutTokenFromStripeObject($object): ?string
    {
        if (! $object) {
            return null;
        }

        $payload = method_exists($object, 'toArray') ? $object->toArray() : (array) $object;
        $sources = [
            $payload['metadata'] ?? [],
            $payload['subscription_details']['metadata'] ?? [],
        ];

        foreach (($payload['lines']['data'] ?? []) as $line) {
            $sources[] = $line['metadata'] ?? [];
            $sources[] = $line['price']['metadata'] ?? [];
        }

        foreach ($sources as $metadata) {
            if (! empty($metadata['checkout_token'])) {
                return (string) $metadata['checkout_token'];
            }
        }

        return null;
    }
}
