<?php

namespace App\Services;

use Illuminate\Support\Str;

/** Compares projects of an already identified client; never calls either CRM or AI. */
class DeterministicTeamleaderDealMatcher
{
    public const SERVICE_FIELD = 'fcd48891-20f6-049a-a05f-f78a6f951b4d';
    public const PROCESS_FIELD = 'a42f63f5-d527-0544-ab50-9c03857707f2';
    public const CERTIFICATE_FIELD = '62a2cd97-1898-00bf-885c-029939e4c40f';

    public const DEAL_PHASES = [
        1 => ['fase_1_preestab', 'fase_1_pagado', 'fase_1_pagado__teamleader_', 'monto_fase_1_pagado'],
        2 => ['fase_2_preestab', 'fase_2_pagado', 'fase_2_pagado__teamleader_', 'monto_fase_2_pagado'],
        3 => ['fase_3_preestab', 'fase_3_pagado', 'fase_3_pagado__teamleader_', 'monto_fase_3_pagado'],
        98 => ['carta_nat_preestab', 'carta_nat_pagado', 'carta_nat_pagado', 'carta_nat_montopagado'],
        99 => ['cil___fcje_preestab', 'cil___fcje_pagado', 'cil___fcje_pagado', 'cilfcje_montopagado'],
    ];

    public function __construct(private TeamleaderProjectPaymentAnalyzer $payments)
    {
    }

    public function projectFacts(array $project): array
    {
        $fields = [];
        foreach ($project['custom_fields'] ?? [] as $field) {
            $fields[$field['definition']['id'] ?? $field['id'] ?? ''] = $field['value'] ?? null;
        }
        $phases = [];
        foreach (TeamleaderProjectPaymentAnalyzer::PHASE_FIELDS as $phase => $definition) {
            $phases[$phase] = [
                'preestab' => $fields[$definition['preestab']['id']] ?? null,
                'paid' => $fields[$definition['paid']['id']] ?? null,
            ];
        }

        return [
            'id' => (string) $project['id'],
            'title' => (string) ($project['title'] ?? ''),
            'service' => (string) ($fields[self::SERVICE_FIELD] ?? ''),
            'process_code' => (string) ($fields[self::PROCESS_FIELD] ?? ''),
            'certificate' => $fields[self::CERTIFICATE_FIELD] ?? null,
            'phase_fields' => $phases,
        ];
    }

    /** Legacy local duplicates of the same remote deal are one process. */
    public function canonicalDeals(array $deals): array
    {
        return collect($deals)->groupBy(fn ($deal) => filled($deal['hubspot_id'] ?? null)
            ? 'hs:'.$deal['hubspot_id'] : 'local:'.$deal['id'])
            ->flatMap(function ($group) {
                $links = $group->pluck('teamleader_id')->filter()->unique();
                if ($links->count() > 1) {
                    return $group->all(); // Conflicting existing links require review.
                }
                return [$group->sortByDesc(fn ($deal) => filled($deal['teamleader_id'] ?? null))->first()];
            })->values()->all();
    }

    /** Mutual, unique matches only. Decisions use the whole set, never greedy elimination. */
    public function plan(array $deals, array $projects, array $reservedProjectIds = []): array
    {
        $reserved = array_fill_keys(array_map('strval', $reservedProjectIds), true);
        $deals = array_values(array_filter($deals, fn ($deal) => blank($deal['teamleader_id'] ?? null)));
        $projects = array_values(array_filter($projects, fn ($project) => ! isset($reserved[(string) $project['id']])));
        $matrix = [];
        foreach ($deals as $deal) {
            foreach ($projects as $project) {
                $matrix[(string) $deal['id']][(string) $project['id']] = $this->compare($deal, $project);
            }
        }

        $decisions = [];
        foreach ($deals as $deal) {
            $scores = $matrix[(string) $deal['id']] ?? [];
            uasort($scores, fn ($a, $b) => $b['score'] <=> $a['score']);
            $projectId = array_key_first($scores);
            $best = $projectId !== null ? $scores[$projectId] : ['score' => 0, 'reason' => 'Sin candidatos del mismo cliente'];
            $next = array_values($scores)[1]['score'] ?? 0;
            $reverse = [];
            foreach ($matrix as $dealId => $candidates) {
                $reverse[$dealId] = $projectId !== null ? $candidates[$projectId]['score'] : 0;
            }
            arsort($reverse);
            $reverseValues = array_values($reverse);
            $eligible = $best['score'] >= 80;
            $unique = $best['score'] - $next >= 10
                && (string) array_key_first($reverse) === (string) $deal['id']
                && ($reverseValues[0] ?? 0) - ($reverseValues[1] ?? 0) >= 10;
            $decisions[] = [
                'deal_id' => (int) $deal['id'],
                'project_id' => $projectId !== null ? (string) $projectId : null,
                'status' => ! $eligible ? 'no_match' : ($unique ? 'matched' : 'ambiguous'),
                'score' => $best['score'],
                'reason' => $best['reason'],
            ];
        }

        return $decisions;
    }

    private function compare(array $deal, array $project): array
    {
        $code = $this->processCode((string) ($deal['codigo_de_proceso'] ?? ''));
        $otherCode = $this->processCode($project['process_code']);
        if ($code !== '' && $otherCode !== '' && $code !== $otherCode) {
            return ['score' => 0, 'reason' => 'Códigos de proceso distintos'];
        }
        $service = $this->service((string) (($deal['servicio_solicitado2'] ?? '') ?: ($deal['servicio_solicitado'] ?? '')));
        $otherService = $this->service($project['service']);
        if ($service !== '' && $otherService !== '' && $service !== $otherService) {
            return ['score' => 0, 'reason' => 'Servicios distintos'];
        }
        if ($code !== '' && $code === $otherCode) {
            return ['score' => 100, 'reason' => 'Código de proceso idéntico'];
        }

        $title = $this->normalize((string) ($deal['dealname'] ?? ''));
        $otherTitle = $this->normalize($project['title']);
        $exactTitle = $title !== '' && $title === $otherTitle && str_word_count($title) >= 3;
        // A complete title may include the service when its custom field is empty.
        $sameService = $service !== '' && ($service === $otherService
            || ($exactTitle && str_contains($otherTitle, $service)));
        $matchingPhases = 0;
        $conflictingAmount = false;
        foreach (self::DEAL_PHASES as $phase => $properties) {
            $matched = false;
            foreach (['preestab' => $properties[0], 'paid' => $properties[2]] as $kind => $property) {
                $left = $this->money($deal[$property] ?? null);
                $right = $this->money($project['phase_fields'][$phase][$kind] ?? null);
                if ($left !== null && $right !== null) {
                    $matched = $matched || abs($left - $right) < 0.01;
                    if ($kind === 'preestab' && abs($left - $right) >= 0.01) {
                        $conflictingAmount = true;
                    }
                }
            }
            $matchingPhases += (int) $matched;
        }
        if ($conflictingAmount) {
            return ['score' => 0, 'reason' => 'Importes preestablecidos distintos'];
        }
        if ($exactTitle && $sameService) {
            return ['score' => 90, 'reason' => 'Nombre completo y servicio idénticos'];
        }
        if ($sameService && $matchingPhases >= 2) {
            return ['score' => 85, 'reason' => 'Mismo servicio e importes de dos fases'];
        }
        if ($exactTitle && $matchingPhases >= 1) {
            return ['score' => 80, 'reason' => 'Nombre completo e importe de fase idénticos'];
        }

        return ['score' => 0, 'reason' => 'Evidencia insuficiente; el nombre solo no basta'];
    }

    private function money(mixed $raw): ?float
    {
        if (! is_scalar($raw)) {
            return null;
        }
        $parsed = $this->payments->parseMoneyText((string) $raw);

        return $parsed['has_amount'] && ! $parsed['requires_currency_conversion']
            && ! $parsed['exonerated'] && ! $parsed['included'] && $parsed['total'] > 0
                ? (float) $parsed['total'] : null;
    }

    public function normalize(string $value): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', ' ', Str::ascii(mb_strtolower($value))) ?? '');
    }

    private function service(string $value): string
    {
        $normalized = $this->normalize($value);

        return match ($normalized) {
            'nacionalidad portuguesa por origen sefardi' => 'portuguesa sefardi',
            'nacionalidad por carta de naturaleza', 'espanola carta de naturaleza general',
            'espanola carta de naturaleza' => 'carta de naturaleza',
            default => $normalized,
        };
    }

    private function processCode(string $value): string
    {
        $value = $this->normalize($value);

        return strlen($value) < 3 || in_array($value, ['null', 'none', 'n a', 's n', 'no aplica', 'sin codigo', 'sin dato', 'sin datos'], true) ? '' : $value;
    }
}
