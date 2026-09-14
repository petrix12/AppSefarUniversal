<?php

namespace App\Services;

/**
 * Chooses one financial source for a client COS without ever mixing a current
 * HubSpot deal with an unrelated Teamleader historical project.
 */
class ClientPaymentSourceResolver
{
    /**
     * @return array{source: string, reason: string, analysis: array}
     */
    public function resolve(array $hubspot, array $teamleader): array
    {
        $hasHubspot = (bool) ($hubspot['has_hubspot_deals'] ?? ! empty($hubspot['projects']));
        $hasTeamleader = ! empty($teamleader['projects']);
        $hasAssociation = (bool) ($hubspot['has_linked_deals'] ?? false);

        if ($hasHubspot && $hasTeamleader) {
            if ($hasAssociation) {
                return $this->decision('hubspot', 'tratos_asociados', $hubspot);
            }

            return $this->decision('teamleader', 'historico_sin_correlacion', $teamleader);
        }

        if ($hasHubspot) {
            return $this->decision('hubspot', 'solo_hubspot', $hubspot);
        }

        if ($hasTeamleader) {
            return $this->decision('teamleader', 'solo_teamleader', $teamleader);
        }

        return $this->decision('none', 'sin_datos_financieros', [
            'projects' => [],
            'totals' => [
                'projects' => 0,
                'preestab_amount' => 0.0,
                'paid_amount' => 0.0,
                'balance_amount' => 0.0,
                'overpaid_amount' => 0.0,
                'difference_amount' => 0.0,
                'projects_to_review' => 0,
            ],
        ]);
    }

    private function decision(string $source, string $reason, array $analysis): array
    {
        $analysis = $this->scopeCartaNaturalezaPayments($analysis);

        return compact('source', 'reason', 'analysis');
    }

    /**
     * Carta de Naturaleza is an independent service. Historical projects can
     * still carry values in Fase 1–3 from an earlier configuration, but once
     * an actual Carta payment has been recorded those phases must neither be
     * presented nor made collectible in the client portal.
     */
    private function scopeCartaNaturalezaPayments(array $analysis): array
    {
        $projects = collect($analysis['projects'] ?? [])
            ->map(function (array $project): array {
                $phases = collect($project['phases'] ?? []);
                $carta = $phases->first(
                    fn (array $phase, int|string $key): bool => (int) ($phase['phase'] ?? $key) === 98
                );

                if (! $this->hasRecordedCartaPayment($carta)) {
                    return $project;
                }

                $phases = $phases
                    ->filter(fn (array $phase, int|string $key): bool => (int) ($phase['phase'] ?? $key) === 98)
                    ->all();

                $project['phases'] = $phases;
                $project['payment_scope'] = 'carta_naturaleza';
                $project['needs_review'] = collect($phases)->contains('needs_review', true);
                $project['review_count'] = collect($phases)->where('needs_review', true)->count();
                $project['totals'] = $this->totalsFor($phases);

                return $project;
            })
            ->values();

        $analysis['projects'] = $projects->all();
        $analysis['totals'] = [
            'projects' => $projects->count(),
            'preestab_amount' => round($projects->sum('totals.preestab_amount'), 2),
            'paid_amount' => round($projects->sum('totals.paid_amount'), 2),
            'balance_amount' => round($projects->sum('totals.balance_amount'), 2),
            'overpaid_amount' => round($projects->sum('totals.overpaid_amount'), 2),
            'difference_amount' => round($projects->sum('totals.difference_amount'), 2),
            'projects_to_review' => $projects->where('needs_review', true)->count(),
        ];

        return $analysis;
    }

    private function hasRecordedCartaPayment(?array $carta): bool
    {
        if (! $carta) {
            return false;
        }

        // A raw abono without a Carta amount to reconcile against is a data
        // issue, not evidence that Carta was actually paid. Keep the normal
        // phase scope until Finance establishes the service amount.
        return (float) ($carta['effective_preestab_amount'] ?? 0) > 0.01
            && in_array($carta['status'] ?? null, ['partial', 'paid', 'review'], true)
            && (
                (float) ($carta['effective_paid_amount'] ?? 0) > 0
                || trim((string) ($carta['paid_raw'] ?? '')) !== ''
            );
    }

    /** @param array<int|string, array<string, mixed>> $phases */
    private function totalsFor(array $phases): array
    {
        $phases = collect($phases);

        return [
            'preestab_amount' => round($phases->sum('effective_preestab_amount'), 2),
            'paid_amount' => round($phases->sum('effective_paid_amount'), 2),
            'balance_amount' => round($phases->sum('balance_amount'), 2),
            'overpaid_amount' => round($phases->sum('overpaid_amount'), 2),
            'difference_amount' => round($phases->sum('difference_amount'), 2),
        ];
    }
}
