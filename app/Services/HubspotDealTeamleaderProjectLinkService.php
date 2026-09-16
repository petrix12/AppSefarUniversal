<?php

namespace App\Services;

use App\Models\HubspotDealTeamleaderProjectLink;
use App\Models\Negocio;
use App\Models\TlContact;
use App\Models\TlProject;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Links HubSpot's local deal projection to the immutable Teamleader project
 * history. It never calls Teamleader and never mutates a tl_* record.
 */
class HubspotDealTeamleaderProjectLinkService
{
    public function overview(User $user): array
    {
        if (! $this->available()) {
            return $this->emptyOverview();
        }

        $deals = Negocio::query()
            ->where('user_id', $user->id)
            ->whereNotNull('hubspot_id')
            ->where('hubspot_id', '!=', '')
            ->orderBy('dealname')
            ->get();
        $projects = $this->projectsFor($user, $deals);
        $links = HubspotDealTeamleaderProjectLink::query()
            ->whereIn('negocio_id', $deals->pluck('id'))
            ->with('project')
            ->get()
            ->keyBy('negocio_id');

        $candidates = [];
        foreach ($deals as $deal) {
            if ($links->has($deal->id)) {
                continue;
            }

            $candidates[$deal->id] = $this->candidatesFor($deal, $projects);
        }

        return [
            'available' => true,
            'deals' => $deals,
            'projects' => $projects,
            'links' => $links,
            'candidates' => $candidates,
            'summary' => [
                'deals' => $deals->count(),
                'projects' => $projects->count(),
                'linked' => $links->count(),
                'pending_review' => collect($candidates)->filter()->count(),
            ],
        ];
    }

    /** Auto-link only exact, single matches. Partial matches remain reviewable. */
    public function detectAndLink(User $user, ?int $linkedBy = null): array
    {
        $overview = $this->overview($user);
        if (! $overview['available']) {
            return ['linked' => 0, 'review' => 0, 'unavailable' => true];
        }

        $linked = 0;
        foreach ($overview['candidates'] as $negocioId => $candidates) {
            $automatic = collect($candidates)
                ->filter(fn (array $candidate) => in_array($candidate['match_method'], ['exact_title', 'legacy_reference'], true))
                ->values();

            if ($automatic->count() !== 1) {
                continue;
            }

            $candidate = $automatic->first();
            $deal = $overview['deals']->firstWhere('id', $negocioId);
            if ($deal) {
                $this->link($user, $deal, $candidate['project'], $candidate['match_method'], $candidate['confidence'], $candidate['evidence'], $linkedBy);
                $linked++;
            }
        }

        $after = $this->overview($user);

        return [
            'linked' => $linked,
            'review' => $after['summary']['pending_review'],
            'unavailable' => false,
        ];
    }

    public function link(
        User $user,
        Negocio $deal,
        TlProject $project,
        string $method = 'manual',
        int $confidence = 100,
        array $evidence = [],
        ?int $linkedBy = null,
    ): HubspotDealTeamleaderProjectLink {
        abort_unless($deal->user_id === $user->id && filled($deal->hubspot_id), 422, 'El trato no pertenece al cliente.');
        abort_unless($this->projectsFor($user, collect([$deal]))->contains('id', $project->id), 422, 'El proyecto no pertenece al histórico de este cliente.');

        $link = HubspotDealTeamleaderProjectLink::updateOrCreate(
            ['negocio_id' => $deal->id],
            [
                'hubspot_deal_id' => (string) $deal->hubspot_id,
                'teamleader_project_id' => (string) $project->id,
                'match_method' => $method,
                'confidence' => max(0, min(100, $confidence)),
                'evidence' => $evidence,
                'linked_by' => $linkedBy,
            ]
        );

        // This is the local projection of the HubSpot deal, used by COS and
        // payment history. No Teamleader record is touched.
        $deal->forceFill(['teamleader_id' => (string) $project->id])->save();

        return $link->fresh('project');
    }

    public function unlink(User $user, Negocio $deal): void
    {
        $link = HubspotDealTeamleaderProjectLink::query()
            ->where('negocio_id', $deal->id)
            ->firstOrFail();

        abort_unless($deal->user_id === $user->id, 422, 'El trato no pertenece al cliente.');
        $projectId = (string) $link->teamleader_project_id;
        $link->delete();

        if ((string) $deal->teamleader_id === $projectId) {
            $deal->forceFill(['teamleader_id' => null])->save();
        }
    }

    private function candidatesFor(Negocio $deal, Collection $projects): array
    {
        $legacyProject = filled($deal->teamleader_id)
            ? $projects->firstWhere('id', (string) $deal->teamleader_id)
            : null;
        if ($legacyProject) {
            return [[
                'project' => $legacyProject,
                'match_method' => 'legacy_reference',
                'confidence' => 100,
                'evidence' => ['teamleader_id' => (string) $deal->teamleader_id],
            ]];
        }

        $dealTitle = $this->normaliseTitle($deal->dealname ?: $deal->servicio_solicitado2);
        if ($dealTitle === '') {
            return [];
        }

        return $projects
            ->map(function (TlProject $project) use ($deal, $dealTitle): ?array {
                $projectTitle = $this->normaliseTitle($project->title);
                if ($projectTitle === '') {
                    return null;
                }

                if ($dealTitle === $projectTitle) {
                    return [
                        'project' => $project,
                        'match_method' => 'exact_title',
                        'confidence' => 100,
                        'evidence' => ['title' => 'normalizado idéntico'],
                    ];
                }

                $tokenScore = $this->tokenSimilarity($dealTitle, $projectTitle);
                $amountScore = $this->sameAmount($deal->amount ?? null, $project->budget_amount ?? null) ? 15 : 0;
                $confidence = min(95, (int) round($tokenScore * 0.8) + $amountScore);

                if ($confidence < 55) {
                    return null;
                }

                return [
                    'project' => $project,
                    'match_method' => 'similar_title',
                    'confidence' => $confidence,
                    'evidence' => [
                        'title_similarity' => round($tokenScore, 1),
                        'amount_match' => $amountScore > 0,
                    ],
                ];
            })
            ->filter()
            ->sortByDesc('confidence')
            ->values()
            ->all();
    }

    private function projectsFor(User $user, Collection $deals): Collection
    {
        $directProjectIds = $deals->pluck('teamleader_id')->filter()->map('strval');
        $contactIds = collect([filled($user->tl_id) ? (string) $user->tl_id : null])->filter();

        if ($contactIds->isEmpty()) {
            $matches = collect();
            $passport = $this->normaliseValue($user->passport);
            if ($passport !== '') {
                $matches = $matches->merge(TlContact::query()
                    ->whereRaw('LOWER(TRIM(passport)) = ?', [$passport])
                    ->pluck('id'));
            }
            $emails = collect([$user->email, $user->email_2 ?? null, $user->email_alternativo ?? null])
                ->map(fn ($value) => $this->normaliseValue($value))
                ->filter()
                ->unique();
            if ($emails->isNotEmpty()) {
                $matches = $matches->merge(TlContact::query()
                    ->whereIn(DB::raw('LOWER(TRIM(email))'), $emails->all())
                    ->pluck('id'));
            }
            $matches = $matches->filter()->unique()->values();
            if ($matches->count() === 1) {
                $contactIds->push((string) $matches->first());
            }
        }

        if ($contactIds->isEmpty() && $directProjectIds->isEmpty()) {
            return collect();
        }

        return TlProject::query()
            ->where(function ($query) use ($contactIds, $directProjectIds) {
                if ($contactIds->isNotEmpty()) {
                    $query->where(function ($contactQuery) use ($contactIds) {
                        $contactQuery->where('customer_type', 'contact')->whereIn('customer_id', $contactIds->all());
                    });
                }
                if ($directProjectIds->isNotEmpty()) {
                    $contactIds->isNotEmpty()
                        ? $query->orWhereIn('id', $directProjectIds->all())
                        : $query->whereIn('id', $directProjectIds->all());
                }
            })
            ->orderByDesc('tl_updated_at')
            ->orderBy('title')
            ->get();
    }

    private function available(): bool
    {
        return Schema::hasTable('hubspot_deal_teamleader_project_links')
            && Schema::hasTable('negocios')
            && Schema::hasTable('tl_projects')
            && Schema::hasTable('tl_contacts');
    }

    private function emptyOverview(): array
    {
        return [
            'available' => false,
            'deals' => collect(),
            'projects' => collect(),
            'links' => collect(),
            'candidates' => [],
            'summary' => ['deals' => 0, 'projects' => 0, 'linked' => 0, 'pending_review' => 0],
        ];
    }

    private function normaliseTitle(?string $value): string
    {
        $value = Str::ascii(Str::lower(trim((string) $value)));
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value);

        return trim(preg_replace('/\s+/', ' ', (string) $value));
    }

    private function normaliseValue(?string $value): string
    {
        return mb_strtolower(trim((string) $value));
    }

    private function tokenSimilarity(string $first, string $second): float
    {
        $firstTokens = array_values(array_unique(array_filter(explode(' ', $first))));
        $secondTokens = array_values(array_unique(array_filter(explode(' ', $second))));
        $union = array_unique(array_merge($firstTokens, $secondTokens));

        return empty($union) ? 0.0 : (count(array_intersect($firstTokens, $secondTokens)) / count($union)) * 100;
    }

    private function sameAmount($first, $second): bool
    {
        if (! is_numeric($first) || ! is_numeric($second)) {
            return false;
        }

        return abs((float) $first - (float) $second) < 0.01;
    }
}
