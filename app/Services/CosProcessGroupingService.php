<?php

namespace App\Services;

use App\Models\Negocio;
use App\Models\TlProject;
use App\Models\TlContact;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CosProcessGroupingService
{
    public function __construct(
        private DeterministicTeamleaderDealMatcher $matcher,
        private LocalCosPortfolioService $portfolio,
    ) {
    }

    public function loadForClient(User $user): array
    {
        $deals = Negocio::where('user_id', $user->id)->get();
        $contact = $this->contactFor($user);
        $linkedIds = $deals->pluck('teamleader_id')->filter()->all();
        $projects = TlProject::where(function ($query) use ($contact, $linkedIds) {
            $query->whereRaw('1 = 0');
            if ($contact) {
                $query->orWhere(fn ($own) => $own->where('customer_type', 'contact')->where('customer_id', $contact->id));
            }
            if ($linkedIds !== []) $query->orWhereIn('id', $linkedIds);
        })->get();
        return $this->forClient($user, $deals, $projects);
    }

    /** Virtual grouping is shared by the staff and applicant status views. No GET writes links. */
    public function forClient(User $user, Collection $deals, Collection $projects): array
    {
        $canonical = $this->matcher->canonicalDeals($deals->map->toArray()->all());
        $existingIds = $deals->pluck('teamleader_id')->filter()->unique();
        $missing = $existingIds->diff($projects->pluck('id'));
        if ($missing->isNotEmpty()) {
            $projects = $projects->concat(TlProject::whereIn('id', $missing)->get())->unique('id')->values();
        }
        $reserved = Negocio::whereNotNull('teamleader_id')->pluck('teamleader_id')->all();
        $ownedProjects = $this->ownedProjects($user, $projects);
        $facts = $ownedProjects->map(fn ($project) => $this->matcher->projectFacts($project->toArray()))->all();
        $decisions = $this->matcher->plan($canonical, $facts, $reserved);
        $proposals = [];
        foreach ($decisions as $decision) {
            if ($decision['status'] === 'matched') {
                $proposals[$decision['deal_id']] = ['client_id' => $user->id, ...$decision];
            }
        }
        $linksByHs = [];
        $proposedIds = [];
        foreach ($canonical as $deal) {
            $link = $deal['teamleader_id'] ?? null;
            if (blank($link) && isset($proposals[$deal['id']])) {
                $link = $proposals[$deal['id']]['project_id'];
                $proposedIds[$link] = true;
            }
            if (filled($deal['hubspot_id'] ?? null) && filled($link)) {
                $linksByHs[(string) $deal['hubspot_id']][] = (string) $link;
            }
        }
        $effective = $deals->map(function ($deal) use ($linksByHs, $proposals) {
            $copy = clone $deal;
            $links = array_unique($linksByHs[(string) $deal->hubspot_id] ?? []);
            if (blank($copy->teamleader_id)) {
                $copy->teamleader_id = count($links) === 1 ? $links[0] : ($proposals[$deal->id]['project_id'] ?? null);
            }
            return $copy;
        });
        $groups = [];
        foreach ($effective as $deal) {
            $key = $this->dealKey($deal);
            $groups[$key] ??= ['key' => $key, 'title' => $deal->dealname ?: $deal->servicio_solicitado2 ?: $deal->servicio_solicitado ?: 'Proceso', 'deals' => [], 'projects' => [], 'proposed' => false];
            $groups[$key]['deals'][] = ['id' => $deal->id, 'hubspot_id' => $deal->hubspot_id, 'title' => $deal->dealname ?: 'Negocio '.$deal->id];
        }
        foreach ($projects as $project) {
            $key = 'tl:'.$project->id;
            $groups[$key] ??= ['key' => $key, 'title' => $project->title, 'deals' => [], 'projects' => [], 'proposed' => false];
            $groups[$key]['projects'][] = ['id' => (string) $project->id, 'title' => $project->title];
            $groups[$key]['proposed'] = isset($proposedIds[$project->id]);
        }

        return [
            'deals' => $effective,
            'projects' => $projects,
            'groups' => array_values($groups),
            'proposals' => array_values($proposals),
            'unlinked_deals' => collect($canonical)->filter(fn ($deal) => blank($deal['teamleader_id'] ?? null))->values()->all(),
            'available_projects' => $ownedProjects->reject(fn ($project) => in_array((string) $project->id, $reserved, true))->values(),
        ];
    }

    public function associateAutomatically(array $context): int
    {
        return $this->portfolio->applyMatches($context['proposals']);
    }

    public function associateManually(User $user, int $dealId, string $projectId, array $context): void
    {
        if (! $context['available_projects']->contains(fn ($project) => (string) $project->id === $projectId)) {
            throw ValidationException::withMessages(['teamleader_id' => 'El proyecto no está disponible para este cliente.']);
        }
        DB::transaction(function () use ($user, $dealId, $projectId) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            TlProject::whereKey($projectId)->lockForUpdate()->firstOrFail();
            $deal = Negocio::where('user_id', $user->id)->whereKey($dealId)->lockForUpdate()->firstOrFail();
            if (filled($deal->teamleader_id) || blank($deal->hubspot_id)
                || Negocio::where('teamleader_id', $projectId)->exists()) {
                throw ValidationException::withMessages(['teamleader_id' => 'El proceso ya está asociado. Recarga el estatus.']);
            }
            $deal->teamleader_id = $projectId;
            $deal->save();
        });
    }

    public function dealKey(Negocio $deal): string
    {
        return filled($deal->teamleader_id) ? 'tl:'.trim((string) $deal->teamleader_id)
            : (filled($deal->hubspot_id) ? 'hs:'.$deal->hubspot_id : 'local:'.$deal->id);
    }

    private function ownedProjects(User $user, Collection $projects): Collection
    {
        $contact = $this->contactFor($user);
        return $contact ? $projects->filter(fn ($project) => $project->customer_type === 'contact' && (string) $project->customer_id === (string) $contact->id)->values() : collect();
    }

    private function contactFor(User $user): ?TlContact
    {
        $contact = filled($user->tl_id) ? TlContact::find($user->tl_id) : null;
        if (! $contact && filled($user->passport)) {
            $matches = TlContact::whereRaw('LOWER(TRIM(passport)) = ?', [mb_strtolower(trim($user->passport))])->get();
            if ($matches->count() > 1) return null;
            $contact = $matches->first();
        }
        if (! $contact) {
            $emails = collect([$user->email, $user->email_2])->filter()->map(fn ($value) => mb_strtolower(trim($value)))->unique()->all();
            $matches = TlContact::whereIn(DB::raw('LOWER(TRIM(email))'), $emails)->get();
            if ($matches->count() > 1) return null;
            $contact = $matches->first();
        }
        if (! $contact || User::role('Cliente')->where('tl_id', $contact->id)->where('id', '!=', $user->id)->exists()) {
            return null;
        }
        return $contact;
    }
}
