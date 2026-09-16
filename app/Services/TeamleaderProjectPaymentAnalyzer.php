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
        98 => [
            'key' => 'carta_naturaleza',
            'label' => 'Carta de Naturaleza',
            'preestab' => [
                'id' => 'a42ed217-b570-0973-9052-fab97214c229',
                'label' => 'Carta Nat Preestablecido',
            ],
            'paid' => [
                'id' => '4339375f-ed77-02d9-a157-7da9f9e4bfac',
                'label' => 'Carta Nat Pagado',
            ],
        ],
        99 => [
            'key' => 'cil_fcje',
            'label' => 'CIL / FCJE',
            'preestab' => [
                'id' => 'aa1ce4b9-a410-00f2-a953-5f8c2713dc35',
                'label' => 'CIL FCJE Preestablecido',
            ],
            'paid' => [
                'id' => 'f23fbe3b-5d13-0a41-a857-e9ab1c63dc42',
                'label' => 'CIL FCJE Pagado',
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
                $difference = $effectivePreestab - $effectivePaid;
                $reviewReasons = $this->reviewReasons(
                    $preestab,
                    $paid,
                    $effectivePreestab,
                    $effectivePaid,
                    $overpaid
                );

                $status = $this->phaseStatus(
                    $effectivePreestab,
                    $effectivePaid,
                    $balance,
                    $overpaid,
                    $exonerated,
                    $included
                );

                if (in_array('currency_conversion_required', $reviewReasons, true)) {
                    $status = 'review';
                }

                return [
                    $phase => [
                        'phase' => $phase,
                        'payment_key' => $fields['key'] ?? "fase_{$phase}",
                        'payment_label' => $fields['label'] ?? "Fase {$phase}",
                        'preestab_raw' => $preestabRaw,
                        'paid_raw' => $paidRaw,
                        'preestab_amount' => round($preestab['total'], 2),
                        'paid_amount' => round($paid['total'], 2),
                        'effective_preestab_amount' => round($effectivePreestab, 2),
                        'effective_paid_amount' => round($effectivePaid, 2),
                        'balance_amount' => round($balance, 2),
                        'overpaid_amount' => round($overpaid, 2),
                        'difference_amount' => round($difference, 2),
                        'status' => $status,
                        'needs_review' => $reviewReasons !== [],
                        'review_reasons' => $reviewReasons,
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
            'needs_review' => $phases->contains('needs_review', true),
            'review_count' => $phases->where('needs_review', true)->count(),
            'totals' => [
                'preestab_amount' => round($phases->sum('effective_preestab_amount'), 2),
                'paid_amount' => round($phases->sum('effective_paid_amount'), 2),
                'balance_amount' => round($phases->sum('balance_amount'), 2),
                'overpaid_amount' => round($phases->sum('overpaid_amount'), 2),
                'difference_amount' => round($phases->sum('difference_amount'), 2),
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
                'difference_amount' => round($items->sum('totals.difference_amount'), 2),
                'projects_to_review' => $items->where('needs_review', true)->count(),
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

        $entries = $this->extractAmounts($this->withoutInstallmentCount($withoutDates));
        $amounts = collect($entries)
            ->where('currency', 'EUR')
            ->pluck('amount')
            ->values()
            ->all();
        $foreignEntries = collect($entries)
            ->where('currency', '!=', 'EUR')
            ->values()
            ->all();
        $foreignCurrencies = collect($foreignEntries)
            ->pluck('currency')
            ->unique()
            ->values()
            ->all();

        $paymentDates = $this->extractPaymentDates($raw);
        $paymentSegments = preg_split('/\s*\+\s*/u', $raw) ?: [];
        $datedEntries = collect($entries)
            ->values()
            ->map(function (array $entry, int $index) use ($paymentDates, $paymentSegments): array {
                $entry['date'] = $this->normalizedPaymentDate($paymentDates[$index] ?? null);
                $entry['cos_reference'] = $this->cosReference($paymentSegments[$index] ?? '');

                return $entry;
            })
            ->all();

        return [
            'raw' => $raw,
            'normalized' => $withoutDates,
            // Amounts in another currency are kept separate until the phase
            // service converts them with their payment-date ECB reference rate.
            'amounts' => $amounts,
            'entries' => $entries,
            'dated_entries' => $datedEntries,
            'foreign_amounts' => $foreignEntries,
            'foreign_currencies' => $foreignCurrencies,
            'requires_currency_conversion' => $foreignCurrencies !== [],
            'payment_dates' => $paymentDates,
            'total' => round(array_sum($amounts), 2),
            'exonerated' => $exonerated,
            'included' => $included,
            'has_amount' => count($entries) > 0,
        ];
    }

    /**
     * In Teamleader, values such as "2500€/2" mean a 2-installment plan
     * for a EUR 2500 phase. The suffix is not a second monetary amount and
     * must never turn the phase into EUR 2502 or divide its agreed total.
     */
    private function withoutInstallmentCount(string $value): string
    {
        return preg_replace('/\s*(€|EUR|EUROS?|USD|US\$|DOLARES?)\s*\/\s*\d+\b/u', ' $1', $value) ?? $value;
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
            $hasMoneyContext = (bool) preg_match('/(€|EUR|EURO|EUROS|USD|US\$|DOLAR|DOLARES|ABONO|ABONADO|PAGO|PAGADO|MONTO|CUOTA|TRANSFER|TRANSFERENCIA|PREESTAB)/u', $context);
            $mostlyNumericValue = $this->isMostlyNumericPaymentValue($text);
            $isLikelyPhaseNumber = $amount <= 3 && preg_match('/FASE\s*' . preg_quote((string) (int) $amount, '/') . '/u', $context);

            if ($isLikelyPhaseNumber) {
                continue;
            }

            if ($hasMoneyContext || $amount >= 100 || ($tokenCount === 1 && $mostlyNumericValue)) {
                $currency = $this->amountCurrency($text, $position, strlen($token));
                $amounts[] = ['amount' => round($amount, 2), 'currency' => $currency];
            }
        }

        return $amounts;
    }

    private function amountCurrency(string $text, int $position, int $length): string
    {
        // Currency belongs to the amount immediately before or after it. Do
        // not inspect the next abono: "1232$ + 1175€" must keep 1175 in EUR.
        $before = substr($text, max(0, $position - 12), min(12, $position));
        $after = substr($text, $position + $length, 12);

        if (preg_match('/(?:USD|US\$|DOLARES?|\$)\s*$/u', $before)
            || preg_match('/^\s*(?:USD|US\$|DOLARES?|\$)/u', $after)) {
            return 'USD';
        }

        return 'EUR';
    }

    private function extractPaymentDates(string $value): array
    {
        preg_match_all('/\b(?:\d{4}[\/-]\d{1,2}[\/-]\d{1,2}|\d{1,2}[\/-]\d{1,2}[\/-]\d{2,4})\b/u', $value, $matches);

        return collect($matches[0] ?? [])
            ->map(static fn (string $date) => str_replace('/', '-', $date))
            ->unique()
            ->values()
            ->all();
    }

    private function normalizedPaymentDate(?string $date): ?string
    {
        $date = trim((string) $date);
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $date, $matches)) {
            return checkdate((int) $matches[2], (int) $matches[3], (int) $matches[1])
                ? sprintf('%04d-%02d-%02d', $matches[1], $matches[2], $matches[3])
                : null;
        }

        if (preg_match('/^(\d{1,2})-(\d{1,2})-(\d{4})$/', $date, $matches)) {
            return checkdate((int) $matches[2], (int) $matches[1], (int) $matches[3])
                ? sprintf('%04d-%02d-%02d', $matches[3], $matches[2], $matches[1])
                : null;
        }

        return null;
    }

    private function cosReference(string $value): ?string
    {
        if (preg_match('/\[COS:([^\]]+)\]/u', $value, $matches)) {
            return trim($matches[1]) ?: null;
        }

        return null;
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
        $value = preg_replace('/(EUR|EUROS?|USD|US\$|DOLARES?)/u', ' $1 ', $value);
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

    private function reviewReasons(
        array $preestab,
        array $paid,
        float $effectivePreestab,
        float $effectivePaid,
        float $overpaid
    ): array {
        $reasons = [];

        if ($overpaid > 0.01) {
            $reasons[] = $effectivePreestab <= 0 && $effectivePaid > 0
                ? 'payment_without_preestablished_amount'
                : 'paid_exceeds_preestablished_amount';
        }

        if ($preestab['raw'] !== '' && ! $preestab['has_amount'] && ! $preestab['exonerated'] && ! $preestab['included']) {
            $reasons[] = 'unreadable_preestablished_amount';
        }

        if ($paid['raw'] !== '' && ! $paid['has_amount'] && ! $paid['exonerated'] && ! $paid['included']) {
            $reasons[] = 'unreadable_paid_amount';
        }

        if ($preestab['requires_currency_conversion'] || $paid['requires_currency_conversion']) {
            $reasons[] = 'currency_conversion_required';
        }

        return $reasons;
    }

    private function phaseStatus(float $preestab, float $paid, float $balance, float $overpaid, bool $exonerated, bool $included): string
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

        if ($overpaid > 0.01) {
            return 'review';
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
