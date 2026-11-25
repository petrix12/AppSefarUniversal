<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class StripeAdminWorkspace
{
    private const COLLECTIONS = [
        'charges' => 'Pagos',
        'payment_intents' => 'Intentos de pago',
        'invoices' => 'Facturas',
        'refunds' => 'Reembolsos',
        'disputes' => 'Contracargos',
    ];

    private const ID_COLLECTIONS = [
        'ch_' => 'charges', 'pi_' => 'payment_intents', 'in_' => 'invoices',
        're_' => 'refunds', 'du_' => 'disputes', 'dp_' => 'disputes', 'evt_' => 'events', 'cus_' => 'customers',
    ];

    private const STATUS_OPTIONS = [
        'charges' => ['succeeded'=>'Confirmado', 'failed'=>'Fallido', 'pending'=>'Pendiente', 'refunded'=>'Reembolsado', 'partially_refunded'=>'Reembolso parcial'],
        'payment_intents' => ['succeeded'=>'Confirmado', 'canceled'=>'Cancelado', 'processing'=>'En proceso', 'requires_payment_method'=>'Pendiente de método de pago', 'requires_action'=>'Pendiente de autenticación', 'requires_capture'=>'Pendiente de captura', 'requires_confirmation'=>'Pendiente de confirmación'],
        'invoices' => ['draft'=>'Borrador', 'open'=>'Abierta', 'paid'=>'Pagada', 'uncollectible'=>'Incobrable', 'void'=>'Anulada'],
        'refunds' => ['pending'=>'Pendiente', 'requires_action'=>'Requiere acción', 'succeeded'=>'Confirmado', 'failed'=>'Fallido', 'canceled'=>'Cancelado'],
        'disputes' => ['warning_needs_response'=>'Aviso pendiente de respuesta', 'warning_under_review'=>'Aviso en revisión', 'warning_closed'=>'Aviso cerrado', 'needs_response'=>'Pendiente de respuesta', 'under_review'=>'En revisión', 'won'=>'Ganado', 'lost'=>'Perdido'],
    ];

    public function __construct(private StripeAdminApi $stripe, private StripePaymentAudit $audit) {}

    public function label(string $dataset): string
    {
        return self::COLLECTIONS[$dataset] ?? 'Actividad reciente';
    }

    public function statuses(string $dataset): array
    {
        return self::STATUS_OPTIONS[$dataset] ?? [];
    }

    public function datasetFor(string $dataset, ?string $search): string
    {
        $kind = $this->idCollection($search ?? '');
        if ($kind === null || $kind === 'customers'
            || (in_array($dataset, ['charges', 'refunds', 'disputes'], true) && $kind === 'payment_intents')
            || (in_array($dataset, ['refunds', 'disputes'], true) && $kind === 'charges')) {
            return $dataset;
        }

        return $kind;
    }

    public function retrieve(string $account, string $id): array
    {
        $dataset = $this->idCollection($id);
        if ($dataset === 'events') {
            return $this->audit->retrieve($account, $id);
        }

        if (!isset(self::COLLECTIONS[$dataset])) {
            throw new PlatformApiException('invalid_operation', 422);
        }

        $object = $this->stripe->request($account, 'GET', $dataset.'/'.$id);
        if (($object['id'] ?? null) !== $id) {
            throw new PlatformApiException('resource_mismatch');
        }

        return $this->present($dataset, $object);
    }

    public function query(string $account, array $filters): array
    {
        $dataset = $filters['dataset'];
        $cursor = $filters['starting_after'] ?? null;
        $created = $this->createdFilter($filters['date_from'] ?? null, $filters['date_to'] ?? null);
        $search = (string) ($filters['q'] ?? '');
        $idCollection = $this->idCollection($search);
        if ($cursor !== null && $this->idCollection($cursor) !== $dataset) {
            $cursor = null;
        }

        if ($search !== '' && $idCollection === $dataset) {
            return ['rows' => [$this->retrieve($account, $search)], 'next_cursor' => null];
        }

        if ($dataset === 'events') {
            if ($search !== '') {
                throw ValidationException::withMessages(['q' => 'Para buscar por ID de titular, selecciona Pagos, Intentos de pago o Facturas.']);
            }

            $result = $this->audit->list($account, $filters['category'] ?? 'failures', $cursor, $created);
            $last = end($result['events']);
            return ['rows' => $result['events'], 'next_cursor' => $result['has_more'] ? ($last['event_id'] ?? null) : null];
        }

        $params = ['limit' => 100];
        if ($cursor) $params['starting_after'] = $cursor;
        if ($created) $params['created'] = $created;

        if ($search !== '') {
            if ($idCollection === 'customers' && in_array($dataset, ['charges', 'payment_intents', 'invoices'], true)) {
                $params['customer'] = $search;
            } elseif ($dataset === 'charges' && $idCollection === 'payment_intents') {
                $params['payment_intent'] = $search;
            } elseif (in_array($dataset, ['refunds', 'disputes'], true) && in_array($idCollection, ['charges', 'payment_intents'], true)) {
                $params[$idCollection === 'charges' ? 'charge' : 'payment_intent'] = $search;
            } else {
                $this->invalidSearch();
            }
        }

        $status = $filters['status'] ?? '';
        if ($status !== '' && !isset($this->statuses($dataset)[$status])) {
            throw ValidationException::withMessages(['status' => 'Selecciona un estado válido para este listado.']);
        }
        if ($dataset === 'invoices' && $status !== '') {
            $params['status'] = $status;
        }

        $response = $this->stripe->request($account, 'GET', $dataset, $params);
        $objects = $response['data'] ?? null;
        if (!is_array($objects) || !array_is_list($objects) || !is_bool($response['has_more'] ?? null)) {
            throw new PlatformApiException('invalid_page');
        }

        $rows = [];
        foreach ($objects as $object) {
            if (!is_array($object)) throw new PlatformApiException('invalid_page');
            $row = $this->present($dataset, $object);
            if ($status !== '' && ($row['status'] ?? '') !== $status) continue;
            $rows[] = $row;
        }
        $last = $objects ? end($objects) : null;

        return [
            'rows' => $rows,
            'next_cursor' => $response['has_more'] && is_array($last) && is_string($last['id'] ?? null) ? $last['id'] : null,
        ];
    }

    private function present(string $dataset, array $data): array
    {
        $currency = is_string($data['currency'] ?? null) ? strtolower($data['currency']) : null;
        $amountField = $dataset === 'invoices'
            ? (($data['amount_due'] ?? null) > 0 ? 'amount_due' : 'amount_paid')
            : 'amount';
        $amount = is_int($data[$amountField] ?? null) && $currency
            ? StripeMoney::display($data[$amountField], $currency) : '—';
        $error = is_array($data['last_payment_error'] ?? null) ? $data['last_payment_error'] : [];
        $finalizationError = is_array($data['last_finalization_error'] ?? null) ? $data['last_finalization_error'] : [];
        $outcome = is_array($data['outcome'] ?? null) ? $data['outcome'] : [];
        $reason = $error['message'] ?? $data['failure_message'] ?? $data['failure_reason']
            ?? $finalizationError['message'] ?? $outcome['seller_message'] ?? $outcome['reason']
            ?? $data['reason'] ?? $data['cancellation_reason'] ?? null;
        $status = is_string($data['status'] ?? null) ? $data['status'] : '';
        if ($dataset === 'charges' && $status === '') {
            $status = !empty($data['paid']) ? 'succeeded' : (!empty($data['failure_code']) || !empty($data['failure_message']) ? 'failed' : 'pending');
        }
        if ($dataset === 'charges' && ($data['amount_refunded'] ?? 0) > 0) {
            $status = !empty($data['refunded']) ? 'refunded' : 'partially_refunded';
        }

        return [
            'event_id' => '',
            'type' => $dataset,
            'label' => self::COLLECTIONS[$dataset][0],
            'created' => is_numeric($data['created'] ?? null) ? (int) $data['created'] : null,
            'resource_id' => is_string($data['id'] ?? null) ? $data['id'] : '',
            'lookup_resource' => $dataset,
            'resource' => is_string($data['object'] ?? null) ? $data['object'] : $dataset,
            'status' => $status,
            'customer_id' => is_string($data['customer'] ?? null) ? $data['customer'] : '',
            'amount' => $amount,
            'error_code' => $error['code'] ?? $data['failure_code'] ?? $finalizationError['code'] ?? '',
            'decline_code' => $error['decline_code'] ?? $outcome['reason'] ?? '',
            'reason' => is_string($reason) && trim($reason) !== ''
                ? mb_substr(trim($reason), 0, 500)
                : ($status === 'failed' ? 'Stripe marcó el intento como fallido; no incluyó un mensaje de diagnóstico.' : '—'),
            'description' => is_string($data['description'] ?? null) ? mb_substr($data['description'], 0, 500) : null,
            'charge_id' => $data['charge'] ?? $data['latest_charge'] ?? $error['charge'] ?? null,
            'payment_intent_id' => $data['payment_intent'] ?? null,
            'network_status' => $outcome['network_status'] ?? null,
            'outcome_type' => $outcome['type'] ?? null,
            'operation' => match (true) {
                $dataset === 'charges' && ($data['paid'] ?? false) === true && ($data['captured'] ?? false) === true
                    && is_int($data['amount_refunded'] ?? null) && ($data['amount'] ?? 0) > $data['amount_refunded'] => 'refund',
                $dataset === 'payment_intents' && in_array($status, ['requires_payment_method','requires_confirmation','requires_action','requires_capture'], true) => 'cancel',
                default => null,
            },
        ];
    }

    private function createdFilter(?string $from, ?string $to): array
    {
        $created = [];
        if ($from) $created['gte'] = CarbonImmutable::parse($from, 'UTC')->startOfDay()->timestamp;
        if ($to) $created['lte'] = CarbonImmutable::parse($to, 'UTC')->endOfDay()->timestamp;
        return $created;
    }

    private function idCollection(string $id): ?string
    {
        foreach (self::ID_COLLECTIONS as $prefix => $dataset) {
            if (str_starts_with($id, $prefix) && preg_match('/^'.preg_quote($prefix, '/').'[A-Za-z0-9_-]{1,180}$/D', $id)) return $dataset;
        }
        return null;
    }

    private function invalidSearch(): never
    {
        throw ValidationException::withMessages([
            'q' => 'Este listado no admite ese tipo de ID. Para buscar por titular (cus_…), selecciona Pagos, Intentos de pago o Facturas.',
        ]);
    }
}
