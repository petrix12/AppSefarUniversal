<?php

namespace Tests\Unit;

use App\Services\ClientPaymentSourceResolver;
use PHPUnit\Framework\TestCase;

class ClientPaymentSourceResolverTest extends TestCase
{
    public function test_hubspot_is_selected_when_it_is_the_only_source(): void
    {
        $decision = $this->resolve($this->hubspot(true, false), $this->teamleader(false));

        $this->assertSame('hubspot', $decision['source']);
        $this->assertSame('solo_hubspot', $decision['reason']);
    }

    public function test_teamleader_is_selected_when_it_is_the_only_source(): void
    {
        $decision = $this->resolve($this->hubspot(false, false), $this->teamleader(true));

        $this->assertSame('teamleader', $decision['source']);
        $this->assertSame('solo_teamleader', $decision['reason']);
    }

    public function test_hubspot_is_selected_when_both_sources_have_an_associated_deal(): void
    {
        $decision = $this->resolve($this->hubspot(true, true), $this->teamleader(true));

        $this->assertSame('hubspot', $decision['source']);
        $this->assertSame('tratos_asociados', $decision['reason']);
    }

    public function test_teamleader_is_selected_when_both_sources_lack_a_correlation(): void
    {
        $decision = $this->resolve($this->hubspot(true, false), $this->teamleader(true));

        $this->assertSame('teamleader', $decision['source']);
        $this->assertSame('historico_sin_correlacion', $decision['reason']);
    }

    private function resolve(array $hubspot, array $teamleader): array
    {
        return (new ClientPaymentSourceResolver())->resolve($hubspot, $teamleader);
    }

    private function hubspot(bool $hasDeals, bool $hasLinks): array
    {
        return [
            'has_hubspot_deals' => $hasDeals,
            'has_linked_deals' => $hasLinks,
            'projects' => $hasDeals ? [['project_id' => 'hubspot:1']] : [],
        ];
    }

    private function teamleader(bool $hasProjects): array
    {
        return ['projects' => $hasProjects ? [['project_id' => 'teamleader:1']] : []];
    }
}
