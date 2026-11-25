<?php

namespace App\Services;

use Illuminate\Support\Arr;

class StripePaymentAudit
{
    private const CATEGORIES = [
        'failures' => [
            'payment_intent.payment_failed', 'charge.failed', 'invoice.payment_failed', 'refund.failed',
        ],
        'payments' => [
            'payment_intent.succeeded', 'payment_intent.canceled', 'charge.succeeded', 'invoice.paid',
        ],
        'refunds' => [
            'charge.refunded', 'refund.created', 'refund.updated', 'refund.failed',
        ],
        'disputes' => [
            'charge.dispute.created', 'charge.dispute.updated', 'charge.dispute.closed',
        ],
    ];

    private const LABELS = [
        'payment_intent.payment_failed' => 'Intento de pago fallido',
        'charge.failed' => 'Pago rechazado',
        'invoice.payment_failed' => 'Factura impaga',
        'refund.failed' => 'Reembolso fallido',
        'payment_intent.succeeded' => 'Pago confirmado',
        'payment_intent.canceled' => 'Intento cancelado',
        'charge.succeeded' => 'Pago confirmado',
        'invoice.paid' => 'Factura pagada',
        'charge.refunded' => 'Pago reembolsado',
        'refund.created' => 'Reembolso iniciado',
        'refund.updated' => 'Reembolso actualizado',
        'charge.dispute.created' => 'Contracargo abierto',
        'charge.dispute.updated' => 'Contracargo actualizado',
        'charge.dispute.closed' => 'Contracargo cerrado',
    ];

    public function __construct(private StripeAdminApi $stripe) {}

    public function list(string $account, string $category = 'all', ?string $startingAfter = null): array
    {
        $types = $category === 'all'
            ? array_values(array_unique(array_merge(...array_values(self::CATEGORIES))))
            : (self::CATEGORIES[$category] ?? throw new PlatformApiException('invalid_operation', 422));

        $query = ['types' => $types, 'limit' => 100];
        if ($startingAfter !== null) $query['starting_after'] = $startingAfter;

        $response = $this->stripe->request($account, 'GET', 'events', $query);
        $items = $response['data'] ?? null;
        if (!is_array($items) || !array_is_list($items) || !is_bool($response['has_more'] ?? null)) {
            throw new PlatformApiException('invalid_page');
        }

        return [
            'events' => array_map(fn ($event) => $this->present($event), array_filter($items, 'is_array')),
            'has_more' => $response['has_more'],
        ];
    }

    private function present(array $event): array
    {
        $object = data_get($event, 'data.object', []);
        $object = is_array($object) ? $object : [];
        $error = $object['last_payment_error'] ?? $object['last_finalization_error'] ?? [];
        $error = is_array($error) ? $error : [];
        $outcome = is_array($object['outcome'] ?? null) ? $object['outcome'] : [];
        $type = is_string($event['type'] ?? null) ? $event['type'] : 'unknown';
        $currency = is_string($object['currency'] ?? null) ? strtolower($object['currency']) : null;
        $amount = $this->amount($object, $type);
        $reason = $error['message'] ?? $object['failure_message'] ?? $object['failure_reason']
            ?? $object['cancellation_reason'] ?? $outcome['seller_message'] ?? $outcome['reason'] ?? $object['reason'] ?? null;

        if (!is_string($reason) || trim($reason) === '') {
            $reason = str_contains($type, 'failed')
                ? 'Stripe registró el fallo, pero este evento no incluye un mensaje de diagnóstico.'
                : (is_string($object['reason'] ?? null) ? $object['reason'] : 'Sin detalle adicional en el evento.');
        }

        return [
            'event_id' => is_string($event['id'] ?? null) ? $event['id'] : '',
            'type' => $type,
            'label' => self::LABELS[$type] ?? $type,
            'created' => is_numeric($event['created'] ?? null) ? (int) $event['created'] : null,
            'resource_id' => is_string($object['id'] ?? null) ? $object['id'] : '',
            'resource' => is_string($object['object'] ?? null) ? $object['object'] : '',
            'status' => is_string($object['status'] ?? null) ? $object['status'] : (str_contains($type, '.failed') ? 'failed' : ''),
            'customer_id' => is_string($object['customer'] ?? null) ? $object['customer'] : '',
            'amount' => $amount !== null && $currency ? StripeMoney::display($amount, $currency) : '—',
            'currency' => $currency,
            'error_code' => Arr::first(array_filter([
                $error['code'] ?? null, $error['decline_code'] ?? null,
                $object['failure_code'] ?? null, $outcome['reason'] ?? null,
            ], 'is_string')),
            'reason' => mb_substr(trim($reason), 0, 500),
        ];
    }

    private function amount(array $object, string $type): ?int
    {
        $fields = str_starts_with($type, 'invoice.')
            ? ['amount_due', 'amount_paid', 'amount']
            : ['amount', 'amount_received', 'amount_paid', 'amount_refunded', 'amount_due'];
        foreach ($fields as $field) {
            if (is_int($object[$field] ?? null)) return $object[$field];
        }

        return null;
    }
}
