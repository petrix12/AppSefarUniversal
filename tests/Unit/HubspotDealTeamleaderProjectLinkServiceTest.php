<?php

namespace Tests\Unit;

use App\Models\Negocio;
use App\Models\TlProject;
use App\Services\HubspotDealTeamleaderProjectLinkService;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class HubspotDealTeamleaderProjectLinkServiceTest extends TestCase
{
    public function test_an_identical_normalised_title_is_an_exact_match(): void
    {
        $service = new HubspotDealTeamleaderProjectLinkService();
        $deal = new Negocio(['dealname' => 'Nacionalidad Española — Ana Pérez']);
        $project = new TlProject([
            'id' => 'tl-project-1',
            'title' => 'Nacionalidad Espanola Ana Perez',
        ]);

        $candidates = $this->candidatesFor($service, $deal, collect([$project]));

        $this->assertCount(1, $candidates);
        $this->assertSame('exact_title', $candidates[0]['match_method']);
        $this->assertSame(100, $candidates[0]['confidence']);
        $this->assertSame('tl-project-1', $candidates[0]['project']->id);
    }

    public function test_legacy_project_reference_wins_over_title_heuristics(): void
    {
        $service = new HubspotDealTeamleaderProjectLinkService();
        $deal = new Negocio([
            'dealname' => 'Trato de HubSpot sin título coincidente',
            'teamleader_id' => 'tl-project-2',
        ]);
        $project = new TlProject(['id' => 'tl-project-2', 'title' => 'Proyecto histórico']);

        $candidates = $this->candidatesFor($service, $deal, collect([$project]));

        $this->assertSame('legacy_reference', $candidates[0]['match_method']);
        $this->assertSame(100, $candidates[0]['confidence']);
    }

    public function test_a_clear_high_confidence_similar_title_is_linked_automatically(): void
    {
        $service = new HubspotDealTeamleaderProjectLinkService();

        $candidate = $this->automaticCandidate($service, collect([
            ['match_method' => 'similar_title', 'confidence' => 82, 'project' => new TlProject(['id' => 'best'])],
            ['match_method' => 'similar_title', 'confidence' => 66, 'project' => new TlProject(['id' => 'other'])],
        ]));

        $this->assertNotNull($candidate);
        $this->assertSame('best', $candidate['project']->id);
    }

    public function test_ambiguous_similar_titles_remain_for_manual_review(): void
    {
        $service = new HubspotDealTeamleaderProjectLinkService();

        $candidate = $this->automaticCandidate($service, collect([
            ['match_method' => 'similar_title', 'confidence' => 84, 'project' => new TlProject(['id' => 'first'])],
            ['match_method' => 'similar_title', 'confidence' => 79, 'project' => new TlProject(['id' => 'second'])],
        ]));

        $this->assertNull($candidate);
    }

    private function candidatesFor(
        HubspotDealTeamleaderProjectLinkService $service,
        Negocio $deal,
        Collection $projects,
    ): array {
        $method = new \ReflectionMethod($service, 'candidatesFor');

        return $method->invoke($service, $deal, $projects);
    }

    private function automaticCandidate(HubspotDealTeamleaderProjectLinkService $service, Collection $candidates): ?array
    {
        $method = new \ReflectionMethod($service, 'automaticCandidate');

        return $method->invoke($service, $candidates);
    }
}
