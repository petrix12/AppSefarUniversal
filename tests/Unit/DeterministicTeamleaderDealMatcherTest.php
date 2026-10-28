<?php

namespace Tests\Unit;

use App\Services\DeterministicTeamleaderDealMatcher;
use App\Services\TeamleaderProjectPaymentAnalyzer;
use PHPUnit\Framework\TestCase;

class DeterministicTeamleaderDealMatcherTest extends TestCase
{
    private function matcher(): DeterministicTeamleaderDealMatcher
    {
        return new DeterministicTeamleaderDealMatcher(new TeamleaderProjectPaymentAnalyzer());
    }

    private function deal(array $overrides = []): array
    {
        return array_replace(['id' => 1, 'dealname' => 'María Pérez - Española Sefardí', 'servicio_solicitado2' => 'Española Sefardi'], $overrides);
    }

    private function project(array $overrides = []): array
    {
        return array_replace(['id' => 'project-1', 'title' => 'Maria Perez - Espanola Sefardi', 'service' => '', 'process_code' => '', 'phase_fields' => []], $overrides);
    }

    public function test_exact_complete_title_and_service_embedded_in_title_match_without_ai(): void
    {
        $match = $this->matcher()->plan([$this->deal()], [$this->project()])[0];
        $this->assertSame('matched', $match['status']);
        $this->assertSame('project-1', $match['project_id']);
    }

    public function test_unique_process_code_can_match_different_titles(): void
    {
        $match = $this->matcher()->plan([$this->deal(['codigo_de_proceso' => 'EXP-123'])], [$this->project(['title' => 'Otro título', 'process_code' => 'exp-123'])])[0];
        $this->assertSame('matched', $match['status']);
    }

    public function test_same_name_without_another_signal_does_not_match(): void
    {
        $match = $this->matcher()->plan([$this->deal(['dealname' => 'Maria Perez Garcia'])], [$this->project(['title' => 'Maria Perez Garcia'])])[0];
        $this->assertSame('no_match', $match['status']);
    }

    public function test_duplicate_project_candidates_are_ambiguous(): void
    {
        $match = $this->matcher()->plan([$this->deal()], [$this->project(), $this->project(['id' => 'project-2'])])[0];
        $this->assertSame('ambiguous', $match['status']);
    }

    public function test_reverse_ambiguity_is_not_resolved_by_iteration_order(): void
    {
        $matches = $this->matcher()->plan([$this->deal(), $this->deal(['id' => 2])], [$this->project()]);
        $this->assertSame(['ambiguous', 'ambiguous'], array_column($matches, 'status'));
    }

    public function test_existing_project_reservations_and_existing_links_are_preserved(): void
    {
        $matches = $this->matcher()->plan([$this->deal(), $this->deal(['id' => 2, 'teamleader_id' => 'existing'])], [$this->project()], ['project-1']);
        $this->assertCount(1, $matches);
        $this->assertSame('no_match', $matches[0]['status']);
    }

    public function test_conflicting_codes_veto_an_identical_name(): void
    {
        $match = $this->matcher()->plan([$this->deal(['codigo_de_proceso' => 'EXP-123'])], [$this->project(['process_code' => 'EXP-456'])])[0];
        $this->assertSame('no_match', $match['status']);
    }

    public function test_conflicting_phase_prices_veto_a_name_match(): void
    {
        $match = $this->matcher()->plan([$this->deal(['fase_1_preestab' => '500 EUR'])], [$this->project(['phase_fields' => [1 => ['preestab' => '1000 EUR']]])])[0];
        $this->assertSame('no_match', $match['status']);
    }

    public function test_service_and_two_positive_phase_amounts_match_without_the_name(): void
    {
        $match = $this->matcher()->plan([$this->deal(['fase_1_preestab' => '500 EUR', 'fase_2_preestab' => '1000 EUR'])], [$this->project([
            'title' => 'Nombre diferente', 'service' => 'Española Sefardi',
            'phase_fields' => [1 => ['preestab' => '500 EUR'], 2 => ['preestab' => '1.000 EUR']],
        ])])[0];
        $this->assertSame('matched', $match['status']);
    }

    public function test_zero_amounts_and_different_currencies_are_not_matching_evidence(): void
    {
        $match = $this->matcher()->plan([$this->deal(['fase_1_preestab' => '0', 'fase_2_preestab' => '500 USD'])], [$this->project([
            'title' => 'Nombre diferente', 'service' => 'Española Sefardi',
            'phase_fields' => [1 => ['preestab' => '0'], 2 => ['preestab' => '500 EUR']],
        ])])[0];
        $this->assertSame('no_match', $match['status']);
    }

    public function test_placeholder_codes_are_not_process_identifiers(): void
    {
        $match = $this->matcher()->plan([$this->deal(['dealname' => 'Primer trato', 'codigo_de_proceso' => 'NULL'])], [$this->project(['title' => 'Segundo trato', 'process_code' => 'null'])])[0];
        $this->assertSame('no_match', $match['status']);
    }
}
