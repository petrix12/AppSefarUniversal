<?php

namespace App\Services;

use App\Models\Negocio;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AutomaticTeamleaderDealAssociationService
{
    private const SERVICE_FIELD_ID = 'fcd48891-20f6-049a-a05f-f78a6f951b4d';

    public function __construct(
        private TeamleaderService $teamleader,
        private HubspotService $hubspot,
        private UnificationAiSuggestionService $ai,
        private TeamleaderDealEnrichmentService $enrichment,
    ) {
    }

    /**
     * Link only an unambiguous, high-confidence pair. Called during the
     * asynchronous COS refresh, never during the page's synchronous render.
     */
    public function associate(User $user, array $hubspotDeals, ?array $teamleaderProjects = null): array
    {
        if (blank($user->tl_id) || $hubspotDeals === []) {
            return ['linked' => 0, 'skipped' => 'no_teamleader_contact_or_hubspot_deals'];
        }

        $teamleaderDeals = $teamleaderProjects
            ?? $this->teamleader->getProjectsWithDetailsByCustomerId((string) $user->tl_id);
        if ($teamleaderDeals === []) {
            return ['linked' => 0, 'skipped' => 'no_teamleader_projects'];
        }

        $linkedIds = Negocio::whereNotNull('teamleader_id')->pluck('teamleader_id')->map(fn ($id) => (string) $id)->all();
        foreach ($hubspotDeals as $hubspotDeal) {
            $hubspotId = (string) ($hubspotDeal['id'] ?? '');
            if ($hubspotId === '') {
                continue;
            }
            $localDeal = Negocio::where('user_id', $user->id)->where('hubspot_id', $hubspotId)->first();
            if (! $localDeal || blank($localDeal->teamleader_id)) {
                continue;
            }

            $project = collect($teamleaderDeals)->first(
                fn ($item) => (string) ($item['id'] ?? '') === (string) $localDeal->teamleader_id
            );
            if (! $project) {
                continue;
            }

            try {
                $this->enrichment->sync($localDeal, $project, $hubspotDeal);
            } catch (\Throwable $exception) {
                Log::warning('COS: no se pudo reintentar el enriquecimiento HubSpot/Teamleader', [
                    'user_id' => $user->id,
                    'negocio_id' => $localDeal->id,
                    'hubspot_id' => $hubspotId,
                    'teamleader_id' => $localDeal->teamleader_id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        $candidates = collect($teamleaderDeals)
            ->filter(fn ($project) => is_array($project) && filled($project['id'] ?? null))
            ->reject(fn ($project) => in_array((string) $project['id'], $linkedIds, true))
            ->map(fn (array $project) => $this->candidate($project))
            ->values()
            ->all();

        if ($candidates === []) {
            return ['linked' => 0, 'skipped' => 'no_unlinked_teamleader_projects'];
        }

        $linked = 0;
        $minimum = (int) config('services.openrouter.auto_association_min_confidence', 95);
        $minimumMargin = (int) config('services.openrouter.auto_association_min_margin', 15);

        // Auto-link only when several deal fields agree. The title is a weak
        // tie-breaker, never enough to link on its own.
        foreach ($hubspotDeals as $hubspotDeal) {
            $hubspotId = (string) ($hubspotDeal['id'] ?? '');
            $properties = $hubspotDeal['properties'] ?? [];
            if ($hubspotId === '') {
                continue;
            }

            $localDeal = Negocio::where('user_id', $user->id)->where('hubspot_id', $hubspotId)->first();
            if (! $localDeal || filled($localDeal->teamleader_id)) {
                continue;
            }

            $ranked = collect($candidates)
                ->map(fn (array $candidate) => array_merge($candidate, $this->compareFields($properties, $candidate)))
                ->sortByDesc('_field_confidence')
                ->values();
            $best = $ranked->first();
            $runnerUp = $ranked->get(1);
            $confidence = (int) ($best['_field_confidence'] ?? 0);
            $margin = $runnerUp ? $confidence - (int) $runnerUp['_field_confidence'] : 100;
            $minimumFieldConfidence = (int) config('services.openrouter.auto_association_min_field_confidence', 80);
            $minimumEvidenceSignals = (int) config('services.openrouter.auto_association_min_field_signals', 2);
            $minimumFieldMargin = (int) config('services.openrouter.auto_association_min_margin', 15);
            if (! $best
                || $confidence < $minimumFieldConfidence
                || (int) $best['_field_signal_count'] < $minimumEvidenceSignals
                || $margin < $minimumFieldMargin) {
                continue;
            }

            $reverseMatches = collect($hubspotDeals)
                ->filter(function (array $item) use ($user): bool {
                    $itemId = (string) ($item['id'] ?? '');
                    $itemDeal = Negocio::where('user_id', $user->id)->where('hubspot_id', $itemId)->first();
                    return $itemId !== '' && $itemDeal && blank($itemDeal->teamleader_id);
                })
                ->map(fn (array $item) => [
                    'id' => (string) $item['id'],
                    'confidence' => $this->compareFields($item['properties'] ?? [], $best)['_field_confidence'],
                ])
                ->sortByDesc('confidence')
                ->values();
            $bestHubspotMatch = $reverseMatches->first();
            $nextHubspotMatch = $reverseMatches->get(1);
            $reverseMargin = $nextHubspotMatch
                ? (int) $bestHubspotMatch['confidence'] - (int) $nextHubspotMatch['confidence']
                : 100;
            if (($bestHubspotMatch['id'] ?? null) !== $hubspotId || $reverseMargin < $minimumFieldMargin) {
                continue;
            }

            $projectId = (string) ($best['id'] ?? '');
            if ($projectId === '' || Negocio::where('teamleader_id', $projectId)->exists()) {
                continue;
            }

            $project = collect($teamleaderDeals)->first(fn ($item) => (string) ($item['id'] ?? '') === $projectId);
            if (! $project) {
                continue;
            }

            $localDeal->teamleader_id = $projectId;
            if (\Illuminate\Support\Facades\Schema::hasColumn($localDeal->getTable(), 'teamleader_match_confidence')) {
                $localDeal->teamleader_match_confidence = $confidence;
                $localDeal->teamleader_match_reason = Str::limit((string) $best['_field_match_reason'], 500, '');
                $localDeal->teamleader_matched_at = now();
            }
            $localDeal->save();

            try {
                $this->enrichment->sync($localDeal, $project, $hubspotDeal);
            } catch (\Throwable $exception) {
                Log::warning('COS: no se pudo enriquecer una asociación por campos HubSpot/Teamleader', [
                    'user_id' => $user->id,
                    'negocio_id' => $localDeal->id,
                    'hubspot_id' => $hubspotId,
                    'teamleader_id' => $projectId,
                    'error' => $exception->getMessage(),
                ]);
            }

            $linked++;
            $linkedIds[] = $projectId;
            $candidates = array_values(array_filter($candidates, fn ($item) => (string) $item['id'] !== $projectId));
            Log::info('Asociación HubSpot/Teamleader aplicada desde coincidencia de campos', [
                'user_id' => $user->id,
                'negocio_id' => $localDeal->id,
                'hubspot_id' => $hubspotId,
                'teamleader_id' => $projectId,
                'confidence' => $confidence,
                'margin' => $margin,
                'matched_fields' => $best['_field_signal_count'],
            ]);
        }

        if (! $this->ai->available()) {
            return ['linked' => $linked, 'skipped' => 'openrouter_not_configured'];
        }

        if ($candidates === []) {
            return ['linked' => $linked];
        }

        $cooldownMinutes = max(1, (int) config('services.openrouter.auto_association_cooldown_minutes', 1440));
        if (! Cache::add('cos.auto_association.attempted.' . $user->id, true, now()->addMinutes($cooldownMinutes))) {
            return ['linked' => $linked, 'skipped' => 'cooldown'];
        }

        foreach ($hubspotDeals as $hubspotDeal) {
            $hubspotId = (string) ($hubspotDeal['id'] ?? '');
            if ($hubspotId === '') {
                continue;
            }

            $localDeal = Negocio::where('user_id', $user->id)->where('hubspot_id', $hubspotId)->first();
            if (! $localDeal) {
                continue;
            }

            if (filled($localDeal->teamleader_id)) {
                continue;
            }

            $properties = $hubspotDeal['properties'] ?? [];
            $rankedCandidates = collect($candidates)
                ->map(function (array $candidate) use ($properties): array {
                    $fieldEvidence = $this->compareFields($properties, $candidate);
                    similar_text($this->normalizeTitle((string) ($properties['dealname'] ?? '')), $this->normalizeTitle($candidate['title']), $similarity);
                    $candidate['_field_score'] = $fieldEvidence['_field_confidence'];
                    $candidate['_name_similarity'] = $similarity;
                    return $candidate;
                })
                ->sortBy([
                    ['_field_score', 'desc'],
                    ['_name_similarity', 'desc'],
                ])
                ->take(40)
                ->map(fn (array $candidate) => collect($candidate)->except(['_field_score', '_name_similarity'])->all())
                ->values()
                ->all();

            if ($rankedCandidates === []) {
                continue;
            }

            try {
                $suggestion = $this->ai->suggestDealAssociations($properties, $rankedCandidates);
                $rankedSuggestions = collect($suggestion['suggestions'] ?? [])
                    ->sortByDesc('confidence')
                    ->values();
                $best = $rankedSuggestions->first();
                $runnerUp = $rankedSuggestions->get(1);
                $confidence = (int) ($best['confidence'] ?? 0);
                $margin = $runnerUp ? $confidence - (int) $runnerUp['confidence'] : 100;

                if (! $best || $confidence < $minimum || $margin < $minimumMargin) {
                    continue;
                }

                // Recheck uniqueness immediately before persisting. The project
                // must still belong to this customer's live Teamleader account.
                $projectId = (string) ($best['id'] ?? '');
                if ($projectId === '' || Negocio::where('teamleader_id', $projectId)->exists()) {
                    continue;
                }
                $project = collect($teamleaderDeals)->first(fn ($item) => (string) ($item['id'] ?? '') === $projectId);
                $matchingCandidate = collect($candidates)->first(fn ($item) => (string) ($item['id'] ?? '') === $projectId);
                if (! $project || ! $matchingCandidate) {
                    continue;
                }

                $reverseMatches = collect($hubspotDeals)
                    ->filter(function (array $item) use ($user): bool {
                        $itemId = (string) ($item['id'] ?? '');
                        $itemDeal = Negocio::where('user_id', $user->id)->where('hubspot_id', $itemId)->first();
                        return $itemId !== '' && $itemDeal && blank($itemDeal->teamleader_id);
                    })
                    ->map(fn (array $item) => [
                        'id' => (string) $item['id'],
                        'confidence' => $this->compareFields($item['properties'] ?? [], $matchingCandidate)['_field_confidence'],
                    ])
                    ->sortByDesc('confidence')
                    ->values();
                $bestHubspotMatch = $reverseMatches->first();
                $nextHubspotMatch = $reverseMatches->get(1);
                $reverseMargin = $nextHubspotMatch
                    ? (int) $bestHubspotMatch['confidence'] - (int) $nextHubspotMatch['confidence']
                    : 100;
                if (($bestHubspotMatch['id'] ?? null) !== $hubspotId || $reverseMargin < $minimumMargin) {
                    continue;
                }

                $localDeal->teamleader_id = $projectId;
                if (\Illuminate\Support\Facades\Schema::hasColumn($localDeal->getTable(), 'teamleader_match_confidence')) {
                    $localDeal->teamleader_match_confidence = $confidence;
                    $localDeal->teamleader_match_reason = Str::limit((string) ($best['reason'] ?? ''), 500, '');
                    $localDeal->teamleader_matched_at = now();
                }
                $localDeal->save();

                // IDs are durable before any enrichment write to HubSpot.
                $comparisons = $this->enrichment->sync($localDeal, $project, $hubspotDeal);
                $linked++;

                Log::info('Asociación automática HubSpot/Teamleader aplicada desde COS', [
                    'user_id' => $user->id,
                    'negocio_id' => $localDeal->id,
                    'hubspot_id' => $hubspotId,
                    'teamleader_id' => $projectId,
                    'confidence' => $confidence,
                    'margin' => $margin,
                    'model' => $suggestion['model'] ?? null,
                    'field_differences' => count($comparisons),
                ]);

                $linkedIds[] = $projectId;
                $candidates = array_values(array_filter($candidates, fn ($candidate) => (string) $candidate['id'] !== $projectId));
            } catch (\Throwable $exception) {
                Log::warning('Falló la asociación automática de negocios al abrir COS', [
                    'user_id' => $user->id,
                    'hubspot_id' => $hubspotId,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return ['linked' => $linked];
    }

    private function candidate(array $project): array
    {
        $customFields = collect($project['custom_fields'] ?? []);
        $fieldValue = fn (string $id) => $customFields->first(
            fn ($field) => (string) ($field['definition']['id'] ?? $field['id'] ?? '') === $id
        )['value'] ?? null;
        $phaseFields = [];
        foreach (TeamleaderProjectPaymentAnalyzer::PHASE_FIELDS as $phase => $fields) {
            $phaseFields[$phase] = [
                'preestab' => $fieldValue($fields['preestab']['id']),
                'paid' => $fieldValue($fields['paid']['id']),
            ];
        }

        return [
            'id' => (string) $project['id'],
            'title' => (string) ($project['title'] ?? ''),
            'status' => (string) ($project['status'] ?? ''),
            'estimated_value' => $project['estimated_value'] ?? [],
            'service' => (string) ($fieldValue(self::SERVICE_FIELD_ID) ?? ''),
            'process_code' => (string) ($fieldValue('a42f63f5-d527-0544-ab50-9c03857707f2') ?? ''),
            'phase_fields' => $phaseFields,
        ];
    }

    private function compareFields(array $hubspot, array $candidate): array
    {
        $score = 0;
        $signals = 0;
        $matched = [];

        $hubspotCode = $this->normalizeValue((string) ($hubspot['codigo_de_proceso'] ?? ''));
        $teamleaderCode = $this->normalizeValue((string) ($candidate['process_code'] ?? ''));
        if ($hubspotCode !== '' && $teamleaderCode !== '' && $hubspotCode === $teamleaderCode) {
            $score += 75;
            $signals++;
            $matched[] = 'código de proceso';
        }

        $hubspotService = $this->normalizeValue((string) ($hubspot['servicio_solicitado2'] ?? $hubspot['servicio_solicitado'] ?? ''));
        $teamleaderService = $this->normalizeValue((string) ($candidate['service'] ?? ''));
        if ($hubspotService !== '' && $teamleaderService !== '' && $hubspotService === $teamleaderService) {
            $score += 25;
            $signals++;
            $matched[] = 'servicio';
        }

        $hubspotPhases = [
            1 => ['preestab' => 'fase_1_preestab', 'paid' => 'fase_1_pagado__teamleader_'],
            2 => ['preestab' => 'fase_2_preestab', 'paid' => 'fase_2_pagado__teamleader_'],
            3 => ['preestab' => 'fase_3_preestab', 'paid' => 'fase_3_pagado__teamleader_'],
            98 => ['preestab' => 'carta_nat_preestab', 'paid' => 'carta_nat_pagado'],
            99 => ['preestab' => 'cil___fcje_preestab', 'paid' => 'cil___fcje_pagado'],
        ];
        foreach ($hubspotPhases as $phase => $properties) {
            $phaseMatched = false;
            foreach ($properties as $kind => $property) {
                $hubspotProperty = in_array($phase, [1, 2, 3], true) && $kind === 'paid'
                    ? "fase_{$phase}_pagado__teamleader_"
                    : $property;
                $hubspotValue = $this->moneyValue($hubspot[$hubspotProperty] ?? $hubspot[$property] ?? null);
                $teamleaderValue = $this->moneyValue(data_get($candidate, "phase_fields.{$phase}.{$kind}"));
                if ($hubspotValue !== null && $teamleaderValue !== null && abs($hubspotValue - $teamleaderValue) < 0.01) {
                    $phaseMatched = true;
                }
            }
            if ($phaseMatched) {
                $score += 15;
                $signals++;
                $matched[] = 'fase '.$phase;
            }
        }

        $hubspotAmount = $this->moneyValue($hubspot['amount'] ?? null);
        $teamleaderAmount = $this->moneyValue(data_get($candidate, 'estimated_value.amount'));
        if ($hubspotAmount !== null && $teamleaderAmount !== null && abs($hubspotAmount - $teamleaderAmount) < 0.01) {
            $score += 15;
            $signals++;
            $matched[] = 'importe del trato';
        }

        $name = $this->normalizeTitle((string) ($hubspot['dealname'] ?? ''));
        $candidateName = $this->normalizeTitle((string) ($candidate['title'] ?? ''));
        if ($name !== '' && $candidateName !== '') {
            similar_text($name, $candidateName, $similarity);
            $score += min(10, (int) floor($similarity / 10));
        }

        return [
            '_field_confidence' => min(100, $score),
            '_field_signal_count' => $signals,
            '_field_match_reason' => $matched === [] ? 'Sin campos coincidentes.' : 'Campos coincidentes: '.implode(', ', $matched).'.',
        ];
    }

    private function moneyValue(mixed $value): ?float
    {
        if (! is_scalar($value) || trim((string) $value) === '') {
            return null;
        }

        $parsed = app(TeamleaderProjectPaymentAnalyzer::class)->parseMoneyText((string) $value);
        return ! empty($parsed['has_amount']) ? round((float) $parsed['total'], 2) : null;
    }

    private function normalizeValue(string $value): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', ' ', Str::ascii(mb_strtolower($value))) ?: '');
    }

    private function normalizeTitle(string $title): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', ' ', Str::ascii(mb_strtolower($title))) ?: '');
    }
}
