<?php

namespace App\Services;

use App\Models\HubspotDealTeamleaderProjectLink;
use App\Models\Negocio;
use App\Models\TlProject;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Builds phase balances from the local projection of HubSpot deals.
 *
 * HubSpot values are deliberately kept independent from Teamleader's
 * historical projection. Source selection happens one level above this
 * analyzer, so an associated HubSpot deal can never silently inherit an old
 * Teamleader balance.
 */
class HubspotDealPaymentAnalyzer
{
    private const PHASE_FIELDS = [
        1 => [
            'key' => 'fase_1',
            'label' => 'Fase 1',
            'preestablished' => ['fase_1_preestab'],
            'paid' => ['monto_fase_1_pagado', 'fase_1_pagado', 'fase_1_pagado__teamleader_'],
        ],
        2 => [
            'key' => 'fase_2',
            'label' => 'Fase 2',
            'preestablished' => ['fase_2_preestab'],
            'paid' => ['monto_fase_2_pagado', 'fase_2_pagado', 'fase_2_pagado__teamleader_'],
        ],
        3 => [
            'key' => 'fase_3',
            'label' => 'Fase 3',
            'preestablished' => ['fase_3_preestab'],
            'paid' => ['monto_fase_3_pagado', 'fase_3_pagado', 'fase_3_pagado__teamleader_'],
        ],
        98 => [
            'key' => 'carta_naturaleza',
            'label' => 'Carta de Naturaleza',
            'preestablished' => ['carta_nat_preestab'],
            'paid' => ['carta_nat_montopagado', 'carta_nat_pagado'],
        ],
        99 => [
            'key' => 'cil_fcje',
            'label' => 'CIL / FCJE',
            'preestablished' => ['cil___fcje_preestab'],
            'paid' => ['cilfcje_montopagado', 'cil___fcje_pagado'],
        ],
    ];

    public function __construct(private readonly TeamleaderProjectPaymentAnalyzer $rules)
    {
    }

    public function analyzeFor(User $user): array
    {
        $deals = Negocio::query()
            ->where('user_id', $user->id)
            ->whereNotNull('hubspot_id')
            ->where('hubspot_id', '!=', '')
            ->orderBy('dealname')
            ->get();

        $links = Schema::hasTable('hubspot_deal_teamleader_project_links')
            ? HubspotDealTeamleaderProjectLink::query()
                ->whereIn('negocio_id', $deals->pluck('id'))
                ->with('project')
                ->get()
                ->keyBy('negocio_id')
            : collect();

        return array_merge($this->analyzeDeals($deals, $links), [
            'has_hubspot_deals' => $deals->isNotEmpty(),
            'has_linked_deals' => $links->isNotEmpty(),
        ]);
    }

    /** @param Collection<int, Negocio> $deals */
    public function analyzeDeals(Collection $deals, ?Collection $links = null): array
    {
        $links ??= collect();
        $projects = $deals
            ->map(function (Negocio $deal) use ($links): array {
                $link = $links->get($deal->id);
                $legacyProject = $link?->project instanceof TlProject ? $link->project : null;

                return $this->analyzeDeal($deal, $legacyProject);
            })
            ->values();

        return [
            'projects' => $projects->all(),
            'totals' => [
                'projects' => $projects->count(),
                'preestab_amount' => round($projects->sum('totals.preestab_amount'), 2),
                'paid_amount' => round($projects->sum('totals.paid_amount'), 2),
                'balance_amount' => round($projects->sum('totals.balance_amount'), 2),
                'overpaid_amount' => round($projects->sum('totals.overpaid_amount'), 2),
                'difference_amount' => round($projects->sum('totals.difference_amount'), 2),
                'projects_to_review' => $projects->where('needs_review', true)->count(),
            ],
        ];
    }

    /**
     * The optional historical argument is retained for callers that used this
     * method before source selection was centralised. It is intentionally not
     * used to fill a HubSpot phase.
     */
    public function analyzeDeal(Negocio $deal, ?TlProject $legacyProject = null): array
    {
        $phases = collect(self::PHASE_FIELDS)
            ->mapWithKeys(function (array $fields, int $phase) use ($deal): array {
                $preestablished = $this->firstValue($deal, $fields['preestablished']);
                $paid = $this->bestPaidValue($deal, $fields['paid']);

                return [$phase => array_merge($this->rules->analyzePaymentValues(
                    $phase,
                    $preestablished,
                    $paid,
                    $fields['key'],
                    $fields['label'],
                ), [
                    'payment_origin' => 'hubspot',
                ])];
            });

        return [
            // A stable local key keeps each HubSpot deal/phase payment
            // independent even when the historical project is later relinked.
            'project_id' => 'hubspot:' . $deal->id,
            'project_title' => $deal->dealname ?: ($deal->servicio_solicitado2 ?: 'Trato HubSpot'),
            'payment_source' => 'hubspot',
            'hubspot_deal_id' => (string) $deal->hubspot_id,
            'negocio_id' => (int) $deal->id,
            'legacy_teamleader_project_id' => (string) ($legacyProject?->id ?? ''),
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

    private function firstValue(Negocio $deal, array $fields): ?string
    {
        foreach ($fields as $field) {
            $value = $deal->getAttribute($field);
            if ($this->hasValue($value)) {
                return trim((string) $value);
            }
        }

        return null;
    }

    private function bestPaidValue(Negocio $deal, array $fields): ?string
    {
        $values = collect($fields)
            ->map(fn (string $field) => $deal->getAttribute($field))
            ->filter(fn ($value) => $this->hasValue($value))
            ->map(fn ($value) => trim((string) $value))
            ->values();

        if ($values->isEmpty()) {
            return null;
        }

        // Exemptions and inclusions are states, not amounts, so they always
        // win over a mirrored numeric total from a historical field.
        $special = $values->first(function (string $value): bool {
            $parsed = $this->rules->parseMoneyText($value);

            return ! empty($parsed['exonerated']) || ! empty($parsed['included']);
        });
        if ($special !== null) {
            return $special;
        }

        return $values
            ->sortByDesc(fn (string $value) => (float) ($this->rules->parseMoneyText($value)['total'] ?? 0))
            ->first();
    }

    private function hasValue(mixed $value): bool
    {
        return $value !== null && trim((string) $value) !== '';
    }
}
