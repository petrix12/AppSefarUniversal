<?php

namespace App\Services;

use InvalidArgumentException;

/**
 * Performs the deliberately full project update required by Teamleader when a
 * custom payment field changes. Sending custom_fields alone may clear other
 * project data in this account.
 */
class TeamleaderProjectPaymentWriter
{
    private const PAID_FIELD_IDS = [
        1 => 'a1b50c58-8175-0d13-9856-f661e783dc08',
        2 => 'a5b94ccc-3ea8-06fc-b259-0a487073dc0d',
        3 => '9a1df9b7-c92f-09e5-b156-96af3f83dc0e',
        98 => '4339375f-ed77-02d9-a157-7da9f9e4bfac',
        99 => 'f23fbe3b-5d13-0a41-a857-e9ab1c63dc42',
    ];

    public function __construct(private readonly TeamleaderService $teamleader)
    {
    }

    public function appendPhasePayment(
        string $projectId,
        int $phase,
        float $amount,
        string $date,
        ?string $reference = null,
        string $currency = 'EUR'
    ): void
    {
        $fieldId = self::PAID_FIELD_IDS[$phase] ?? null;

        if (! $fieldId || $amount <= 0) {
            throw new InvalidArgumentException('La fase o el monto de Teamleader no es válido.');
        }

        $project = $this->teamleader->getProjectDetails($projectId);
        if (! is_array($project) || empty($project['id'])) {
            throw new \RuntimeException('Teamleader no devolvió el proyecto que se iba a actualizar.');
        }

        $customFields = $this->customFieldsForUpdate($project['custom_fields'] ?? []);
        $currency = strtoupper(trim($currency)) ?: 'EUR';
        $reference = trim((string) $reference) ?: null;
        $newPaymentText = 'Abono ' . format_money($amount, 2, '.', '') . " {$currency} {$date}"
            . ($reference ? " [COS:{$reference}]" : '');
        $fieldFound = false;

        foreach ($customFields as &$field) {
            if (($field['id'] ?? null) !== $fieldId) {
                continue;
            }

            $previous = trim((string) ($field['value'] ?? ''));
            if ($reference && str_contains($previous, "[COS:{$reference}]")) {
                return;
            }

            $field['value'] = $previous === '' ? $newPaymentText : $previous . ' + ' . $newPaymentText;
            $fieldFound = true;
            break;
        }
        unset($field);

        if (! $fieldFound) {
            $customFields[] = ['id' => $fieldId, 'value' => $newPaymentText];
        }

        $this->teamleader->updateProject(
            (string) $project['id'],
            $this->projectUpdatePayload($project, $customFields)
        );
    }

    /**
     * Exposed for regression tests and for legacy phase payments that already
     * have an up-to-date project response.
     */
    public function projectUpdatePayload(array $project, array $customFields): array
    {
        $payload = [];

        // projects.update accepts these project attributes. Re-sending the
        // current values makes an update of custom_fields safe in this
        // Teamleader account, where collections are replaced as a whole.
        foreach (['title', 'description', 'status', 'starts_on', 'purchase_order_number'] as $field) {
            if (array_key_exists($field, $project) && $project[$field] !== null) {
                $payload[$field] = $project[$field];
            }
        }

        if (! empty($project['customer']['id']) && ! empty($project['customer']['type'])) {
            $payload['customer'] = [
                'id' => $project['customer']['id'],
                'type' => $project['customer']['type'],
            ];
        }

        if (isset($project['budget']) && is_array($project['budget'])) {
            $payload['budget'] = array_filter([
                'amount' => $project['budget']['amount'] ?? null,
                'currency' => $project['budget']['currency'] ?? null,
            ], static fn ($value) => $value !== null);
        }

        $payload['custom_fields'] = $customFields;

        return $payload;
    }

    private function customFieldsForUpdate(array $fields): array
    {
        return collect($fields)
            ->map(function (array $field): ?array {
                $id = $field['id'] ?? data_get($field, 'definition.id');

                if (! $id) {
                    return null;
                }

                return [
                    'id' => $id,
                    'value' => $field['value'] ?? null,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }
}
