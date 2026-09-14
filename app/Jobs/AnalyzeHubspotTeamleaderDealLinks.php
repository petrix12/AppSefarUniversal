<?php

namespace App\Jobs;

use App\Models\Negocio;
use App\Models\User;
use App\Services\HubspotDealTeamleaderProjectLinkService;
use App\Services\HubspotTeamleaderProjectAiMatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AnalyzeHubspotTeamleaderDealLinks implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 90;

    public int $uniqueFor = 300;

    public function __construct(public int $userId)
    {
        $this->onConnection('cos');
        // Reuse the existing COS worker so enabling AI matching does not
        // require a second production queue process.
        $this->onQueue('cos-refresh');
    }

    public function uniqueId(): string
    {
        return (string) $this->userId;
    }

    public function handle(
        HubspotDealTeamleaderProjectLinkService $links,
        HubspotTeamleaderProjectAiMatcher $matcher,
    ): void {
        if (! $matcher->available()) {
            return;
        }

        $user = User::find($this->userId);
        if (! $user) {
            return;
        }

        $overview = $links->overview($user);
        if (! ($overview['available'] ?? false)) {
            return;
        }

        $candidates = collect($overview['candidates'] ?? [])
            ->flatMap(function (array $matches, $negocioId) use ($overview) {
                $deal = $overview['deals']->firstWhere('id', $negocioId);
                if (! $deal instanceof Negocio) {
                    return collect();
                }

                return collect($matches)
                    ->filter(fn (array $match) => ($match['match_method'] ?? '') === 'similar_title')
                    ->map(fn (array $match) => [
                        'identity' => $deal->id.':'.$match['project']->id,
                        'negocio_id' => (int) $deal->id,
                        'teamleader_project_id' => (string) $match['project']->id,
                        'deal_title' => (string) ($deal->dealname ?: $deal->servicio_solicitado2 ?: ''),
                        'project_title' => (string) $match['project']->title,
                        'deal_amount' => $deal->amount,
                        'project_amount' => $match['project']->budget_amount,
                        'deterministic_confidence' => (int) $match['confidence'],
                        'evidence' => (array) ($match['evidence'] ?? []),
                    ]);
            })
            ->sortByDesc('deterministic_confidence')
            ->take(24)
            ->values()
            ->all();

        if ($candidates === []) {
            return;
        }

        try {
            $result = $matcher->analyse($candidates);
        } catch (\Throwable $exception) {
            Log::warning('COS: análisis IA de enlaces no disponible', [
                'user_id' => $user->id,
                'error' => $exception->getMessage(),
            ]);

            return;
        }

        collect($result['matches'] ?? [])
            ->groupBy('negocio_id')
            ->each(function ($matches, $negocioId) use ($overview, $links, $user, $result): void {
                $ranked = $matches->sortByDesc('ai_confidence')->values();
                $winner = $ranked->first();
                $runnerUp = $ranked->get(1);
                if (! $winner || (int) $winner['ai_confidence'] < 80
                    || ($runnerUp && (int) $winner['ai_confidence'] - (int) $runnerUp['ai_confidence'] < 10)) {
                    return;
                }

                $deal = $overview['deals']->firstWhere('id', $negocioId);
                $project = $overview['projects']->firstWhere('id', (string) $winner['teamleader_project_id']);
                if (! $deal || ! $project) {
                    return;
                }

                $links->link($user, $deal, $project, 'ai_similarity', (int) $winner['ai_confidence'], [
                    'deterministic' => $winner['evidence'],
                    'ai' => [
                        'model' => $result['model'] ?? null,
                        'reason' => $winner['ai_reason'],
                        'confidence' => (int) $winner['ai_confidence'],
                    ],
                ]);
            });
    }
}
