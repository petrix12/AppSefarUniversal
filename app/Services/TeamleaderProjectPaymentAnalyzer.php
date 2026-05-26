<?php

namespace App\Services;

use App\Models\TlProject;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class TeamleaderProjectPaymentAnalyzer
{
    public const PHASE_FIELDS = [
        1 => [
            'preestab' => [
                'id' => '73173887-a0e8-0f4f-bb55-b61f33d3c6e9',
                'label' => 'Fase 1 Preestab',
            ],
            'paid' => [
                'id' => 'a1b50c58-8175-0d13-9856-f661e783dc08',
                'label' => 'Fase 1 Pagado',
            ],
        ],
        2 => [
            'preestab' => [
                'id' => 'c66a9c15-c965-0812-ad5b-7e48f183c6f9',
                'label' => 'Fase 2 Preestab',
            ],
            'paid' => [
                'id' => 'a5b94ccc-3ea8-06fc-b259-0a487073dc0d',
                'label' => 'Fase 2 Pagado',
            ],
        ],
        3 => [
            'preestab' => [
                'id' => 'e41fdbbb-a25a-005b-af56-9f3ca623c700',
                'label' => 'Fase 3 Preestab',
            ],
            'paid' => [
                'id' => '9a1df9b7-c92f-09e5-b156-96af3f83dc0e',
                'label' => 'Fase 3 Pagado',
            ],
        ],
    ];

    public function analyzeProject(TlProject $project): array
    {
        $phases = collect(self::PHASE_FIELDS)
            ->mapWithKeys(function (array $fields, int $phase) use ($project) {
                $preestabRaw = $this->customFieldValue($project, $fields['preestab']['id'], $fields['preestab']['label']);
                $paidRaw = $this->customFieldValue($project, $fields['paid']['id'], $fields['paid']['label']);

                $preestab = $this->parseMoneyText($preestabRaw);
                $paid = $this->parseMoneyText($paidRaw);

                $exonerated = $preestab['exonerated'] || $paid['exonerated'];
                $included = $preestab['included'] || $paid['included'];
                $effectivePreestab = ($exonerated || $included) ? 0.0 : $preestab['total'];
                $effectivePaid = ($exonerated || $included) ? 0.0 : $paid['total'];
                $balance = max($effectivePreestab - $effectivePaid, 0.0);
                $overpaid = max($effectivePaid - $effectivePreestab, 0.0);

                return [
                    $phase => [
                        'phase' => $phase,
                        'preestab_raw' => $preestabRaw,
                        'paid_raw' => $paidRaw,
                        'preestab_amount' => round($preestab['total'], 2),
                        'paid_amount' => round($paid['total'], 2),
                        'effective_preestab_amount' => round($effectivePreestab, 2),
                        'effective_paid_amount' => round($effectivePaid, 2),
                        'balance_amount' => round($balance, 2),
                        'overpaid_amount' => round($overpaid, 2),
                        'status' => $this->phaseStatus($effectivePreestab, $effectivePaid, $balance, $exonerated, $included),
                        'preestab_parse' => $preestab,
                        'paid_parse' => $paid,
                    ],
                ];
            });

        return [
            'project_id' => $project->id,
            'project_title' => $project->title,
            'customer_id' => $project->customer_id,
            'customer_type' => $project->customer_type,
            'phases' => $phases->all(),
            'totals' => [
                'preestab_amount' => round($phases->sum('effective_preestab_amount'), 2),
                'paid_amount' => round($phases->sum('effective_paid_amount'), 2),
                'balance_amount' => round($phases->sum('balance_amount'), 2),
                'overpaid_amount' => round($phases->sum('overpaid_amount'), 2),
            ],
        ];
    }

    public function analyzeProjects(Collection $projects): array
    {
        $items = $projects->map(fn (TlProject $project) => $this->analyzeProject($project));

        return [
            'projects' => $items->values()->all(),
            'totals' => [
                'projects' => $items->count(),
                'preestab_amount' => round($items->sum('totals.preestab_amount'), 2),
                'paid_amount' => round($items->sum('totals.paid_amount'), 2),
                'balance_amount' => round($items->sum('totals.balance_amount'), 2),
                'overpaid_amount' => round($items->sum('totals.overpaid_amount'), 2),
            ],
        ];
    }

    public function parseMoneyText(?string $value): array
    {
        $raw = trim((string) $value);
        $normalized = $this->normalizeText($raw);
        $withoutDates = $this->removeDates($normalized);

        $exonerated = (bool) preg_match('/\bEXONERAD[OA]|\bEXONERACION\b/u', $withoutDates);
        $included = (bool) preg_match('/\bINCLUID[OA]\s+EN\s+FASE\b/u', $withoutDates);

        $amounts = $this->extractAmounts($withoutDates);

        return [
            'raw' => $raw,
            'normalized' => $withoutDates,
            'amounts' => $amounts,
            'total' => round(array_sum($amounts), 2),
            'exonerated' => $exonerated,
            'included' => $included,
            'has_amount' => count($amounts) > 0,
        ];
    }

    private function customFieldValue(TlProject $project, string $fieldId, string $label): ?string
    {
        $normalizedLabel = $this->normalizeFieldLabel($label);

        foreach ($project->custom_fields ?? [] as $field) {
            $currentId = $field['definition']['id'] ?? $field['id'] ?? null;
            $currentLabel = $field['definition']['label'] ?? $field['label'] ?? null;

            if ($currentId === $fieldId || ($currentLabel && $this->normalizeFieldLabel($currentLabel) === $normalizedLabel)) {
                $value = $field['value'] ?? null;

                return is_scalar($value) ? trim((string) $value) : null;
            }
        }

        return null;
    }

    private function extractAmounts(string $text): array
    {
        if ($text === '' || preg_match('/^\s*(EXONERAD[OA]|INCLUID[OA]\s+EN\s+FASE)\b/u', $text)) {
            return [];
        }

        preg_match_all('/(?<![\w])\d+(?:[.,]\d+)*(?![\w])/u', $text, $matches, PREG_OFFSET_CAPTURE);

        $amounts = [];
        $numericTokens = $matches[0] ?? [];
        $tokenCount = count($numericTokens);

        foreach ($numericTokens as [$token, $position]) {
            $amount = $this->parseDecimal($token);

            if ($amount === null) {
                continue;
            }

            $context = $this->amountContext($text, $position, strlen($token));
            $hasMoneyContext = (bool) preg_match('/(€|EUR|EURO|EUROS|ABONO|ABONADO|PAGO|PAGADO|MONTO|CUOTA|TRANSFER|TRANSFERENCIA|PREESTAB)/u', $context);
            $mostlyNumericValue = $this->isMostlyNumericPaymentValue($text);
            $isLikelyPhaseNumber = $amount <= 3 && preg_match('/FASE\s*' . preg_quote((string) (int) $amount, '/') . '/u', $context);

            if ($isLikelyPhaseNumber) {
                continue;
            }

            if ($hasMoneyContext || $amount >= 100 || ($tokenCount === 1 && $mostlyNumericValue)) {
                $amounts[] = round($amount, 2);
            }
        }

        return $amounts;
    }

    private function parseDecimal(string $token): ?float
    {
        $token = trim($token);

        if ($token === '') {
            return null;
        }

        $lastComma = strrpos($token, ',');
        $lastDot = strrpos($token, '.');

        if ($lastComma !== false && $lastDot !== false) {
            $decimalSeparator = $lastComma > $lastDot ? ',' : '.';
            $thousandsSeparator = $decimalSeparator === ',' ? '.' : ',';
            $token = str_replace($thousandsSeparator, '', $token);
            $token = str_replace($decimalSeparator, '.', $token);
        } elseif ($lastComma !== false) {
            $token = $this->normalizeSingleSeparatorNumber($token, ',');
        } elseif ($lastDot !== false) {
            $token = $this->normalizeSingleSeparatorNumber($token, '.');
        }

        return is_numeric($token) ? (float) $token : null;
    }

    private function normalizeSingleSeparatorNumber(string $token, string $separator): string
    {
        $parts = explode($separator, $token);
        $last = end($parts);

        if (count($parts) > 2) {
            return strlen($last) === 2
                ? str_replace($separator, '', implode($separator, array_slice($parts, 0, -1))) . '.' . $last
                : str_replace($separator, '', $token);
        }

        if (strlen($last) === 1 || strlen($last) === 2) {
            return str_replace($separator, '.', $token);
        }

        return str_replace($separator, '', $token);
    }

    private function normalizeText(string $value): string
    {
        $value = Str::ascii($value);
        $value = mb_strtoupper($value);
        $value = preg_replace('/(EUR|EUROS?)/u', ' $1 ', $value);
        $value = str_replace(["\u{00A0}", "\r", "\n", "\t"], ' ', $value);

        return trim(preg_replace('/\s+/', ' ', $value));
    }

    private function normalizeFieldLabel(string $value): string
    {
        return preg_replace('/[^A-Z0-9]+/', '', $this->normalizeText($value));
    }

    private function removeDates(string $value): string
    {
        $value = preg_replace('/\b\d{1,2}[\/-]\d{1,2}[\/-]\d{2,4}\b/u', ' ', $value);
        $value = preg_replace('/\b\d{4}[\/-]\d{1,2}[\/-]\d{1,2}\b/u', ' ', $value);

        return trim(preg_replace('/\s+/', ' ', $value));
    }

    private function amountContext(string $text, int $position, int $length): string
    {
        $start = max(0, $position - 28);
        $contextLength = $length + 56;

        return substr($text, $start, $contextLength);
    }

    private function isMostlyNumericPaymentValue(string $text): bool
    {
        $clean = preg_replace('/[0-9.,€+\-\s]/u', '', $text);

        return trim((string) $clean) === '';
    }

    private function phaseStatus(float $preestab, float $paid, float $balance, bool $exonerated, bool $included): string
    {
        if ($exonerated) {
            return 'exonerated';
        }

        if ($included) {
            return 'included';
        }

        if ($preestab <= 0 && $paid <= 0) {
            return 'empty';
        }

        if ($preestab > 0 && $balance <= 0.01) {
            return 'paid';
        }

        if ($paid > 0) {
            return 'partial';
        }

        return 'pending';
    }
}
