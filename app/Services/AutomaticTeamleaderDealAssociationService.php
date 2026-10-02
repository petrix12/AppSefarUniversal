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

        if (! $this->ai->available()) {
            return ['linked' => 0, 'skipped' => 'openrouter_not_configured'];
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

        $cooldownMinutes = max(1, (int) config('services.openrouter.auto_association_cooldown_minutes', 1440));
        if (! Cache::add('cos.auto_association.attempted.' . $user->id, true, now()->addMinutes($cooldownMinutes))) {
            return ['linked' => 0, 'skipped' => 'cooldown'];
        }

        $linked = 0;
        $minimum = (int) config('services.openrouter.auto_association_min_confidence', 95);
        $minimumMargin = (int) config('services.openrouter.auto_association_min_margin', 15);

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
            $normalizedName = $this->normalizeTitle((string) ($properties['dealname'] ?? ''));
            $rankedCandidates = collect($candidates)
                ->map(function (array $candidate) use ($normalizedName): array {
                    similar_text($normalizedName, $this->normalizeTitle($candidate['title']), $similarity);
                    $candidate['_name_similarity'] = $similarity;
                    return $candidate;
                })
                ->sortByDesc('_name_similarity')
                ->take(40)
                ->map(fn (array $candidate) => collect($candidate)->except('_name_similarity')->all())
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
                if (! $project) {
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
        $service = collect($project['custom_fields'] ?? [])->first(
            fn ($field) => ($field['definition']['id'] ?? $field['id'] ?? null) === self::SERVICE_FIELD_ID
        );

        return [
            'id' => (string) $project['id'],
            'title' => (string) ($project['title'] ?? ''),
            'status' => (string) ($project['status'] ?? ''),
            'estimated_value' => $project['estimated_value'] ?? [],
            'service' => (string) ($service['value'] ?? ''),
        ];
    }

    private function normalizeTitle(string $title): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', ' ', Str::ascii(mb_strtolower($title))) ?: '');
    }
}
