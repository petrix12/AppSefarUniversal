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

    public function test_a_paid_carta_de_naturaleza_hides_regular_phases_for_that_project(): void
    {
        $hubspot = $this->hubspot(true, false);
        $hubspot['projects'][0]['phases'] = [
            1 => $this->phase(1, 'Fase 1', 'paid', 1000, 1000, 0),
            2 => $this->phase(2, 'Fase 2', 'pending', 1000, 0, 1000),
            98 => $this->phase(98, 'Carta de Naturaleza', 'partial', 6067, 2631, 3436, 'Abono 2.631 EUR'),
        ];

        $decision = $this->resolve($hubspot, $this->teamleader(false));
        $project = $decision['analysis']['projects'][0];

        $this->assertSame('carta_naturaleza', $project['payment_scope']);
        $this->assertSame([98], array_keys($project['phases']));
        $this->assertSame(6067.0, $decision['analysis']['totals']['preestab_amount']);
        $this->assertSame(2631.0, $decision['analysis']['totals']['paid_amount']);
        $this->assertSame(3436.0, $decision['analysis']['totals']['balance_amount']);
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

    private function phase(
        int $number,
        string $label,
        string $status,
        float $preestablished,
        float $paid,
        float $balance,
        string $paidRaw = '',
    ): array {
        return [
            'phase' => $number,
            'payment_label' => $label,
            'status' => $status,
            'effective_preestab_amount' => $preestablished,
            'effective_paid_amount' => $paid,
            'balance_amount' => $balance,
            'overpaid_amount' => 0.0,
            'difference_amount' => $preestablished - $paid,
            'paid_raw' => $paidRaw,
            'needs_review' => false,
        ];
    }
}
