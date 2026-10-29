<?php

namespace Tests\Unit;

use App\Services\LocalCosPortfolioService;
use Tests\TestCase;

class LocalCosPortfolioTest extends TestCase
{
    private function client(): array
    {
        return ['id' => 1, 'name' => 'Ana Pérez', 'email' => 'ana@example.test', 'passport' => 'ANA123'];
    }

    private function project(array $overrides = []): array
    {
        return array_replace([
            'id' => 'project-1', 'title' => 'Ana Pérez - Española Sefardi', 'service' => 'Española Sefardi',
            'process_code' => '', 'certificate' => '2024-01-02',
            'phase_fields' => [1 => ['preestab' => '1000 EUR', 'paid' => '300 EUR']],
        ], $overrides);
    }

    public function test_a_client_can_have_certificate_and_debt_directly_from_teamleader_without_any_hubspot_association(): void
    {
        $row = app(LocalCosPortfolioService::class)->clientRow($this->client(), [], [$this->project()]);
        $this->assertTrue($row['has_certificate']);
        $this->assertSame('debt', $row['status']);
        $this->assertSame(700.0, $row['known_balance_eur']);
    }

    public function test_a_virtual_automatic_match_deduplicates_sources_and_uses_administration_payments(): void
    {
        $deal = ['id' => 5, 'dealname' => 'Ana Pérez', 'monto_fase_1_pagado' => '600 EUR'];
        $row = app(LocalCosPortfolioService::class)->clientRow($this->client(), [$deal], [$this->project()], [
            ['deal_id' => 5, 'project_id' => 'project-1', 'status' => 'matched'],
        ]);
        $this->assertCount(1, $row['cases']);
        $this->assertSame(400.0, $row['known_balance_eur']);
        $this->assertSame([], $row['review_reasons']);
    }

    public function test_unmatched_sources_are_never_added_together(): void
    {
        $row = app(LocalCosPortfolioService::class)->clientRow($this->client(), [
            ['id' => 5, 'dealname' => 'Ana Pérez', 'fase_1_preestab' => '1000 EUR'],
        ], [$this->project()]);
        $this->assertSame(700.0, $row['known_balance_eur']);
        $this->assertSame('debt_review', $row['status']);
    }

    public function test_unknown_currency_is_reviewed_instead_of_inventing_euro_debt(): void
    {
        $row = app(LocalCosPortfolioService::class)->clientRow($this->client(), [], [$this->project([
            'phase_fields' => [1 => ['preestab' => '1000 EUR', 'paid' => '300 USD']],
        ])]);
        $this->assertSame(0.0, $row['known_balance_eur']);
        $this->assertSame('review', $row['status']);
    }

    public function test_missing_payment_values_are_not_treated_as_a_paid_client(): void
    {
        $row = app(LocalCosPortfolioService::class)->clientRow($this->client(), [], [$this->project(['phase_fields' => []])]);
        $this->assertSame('no_data', $row['status']);
    }

    public function test_small_balances_remain_financial_balances_but_are_not_portal_charges(): void
    {
        $row = app(LocalCosPortfolioService::class)->clientRow($this->client(), [], [$this->project([
            'phase_fields' => [1 => ['preestab' => '1000 EUR', 'paid' => '980 EUR']],
        ])]);
        $this->assertSame(20.0, $row['known_balance_eur']);
        $this->assertSame(0.0, $row['collectible_balance_eur']);
    }

    public function test_a_more_recent_portal_installment_reduces_the_imported_balance(): void
    {
        $row = app(LocalCosPortfolioService::class)->clientRow($this->client(), [], [$this->project()], [], [[
            'monto' => 400,
            'metadata' => ['teamleader_project_id' => 'project-1', 'phase' => 1, 'last_portal_installment_id' => 99, 'paid_amount_at_sync' => 300],
        ]]);
        $this->assertSame(400.0, $row['known_balance_eur']);
    }

    public function test_carta_naturaleza_progress_is_not_a_certificate_date(): void
    {
        $row = app(LocalCosPortfolioService::class)->clientRow($this->client(), [[
            'id' => 5, 'n7__enviado_al_dto_juridico' => '2024-01-02', 'certificadoDescargado' => 1,
        ]], []);
        $this->assertFalse($row['has_certificate']);
    }

    public function test_duplicate_passports_never_resolve_to_an_arbitrary_contact(): void
    {
        $match = app(LocalCosPortfolioService::class)->contactFor($this->client(), [
            'id' => [], 'passport' => ['ana123' => ['contact-1', 'contact-2']], 'email' => ['ana@example.test' => ['contact-1']],
        ]);
        $this->assertNull($match['id']);
        $this->assertStringContainsString('ambigua', $match['reason']);
    }

    public function test_hidden_phase_preserves_internal_debt_without_becoming_a_portal_charge(): void
    {
        $row = app(LocalCosPortfolioService::class)->clientRow($this->client(), [], [$this->project()], [], [[
            'metadata' => ['teamleader_project_id' => 'project-1', 'phase' => 1, 'hidden_from_client' => true],
        ]]);
        $this->assertSame(700.0, $row['known_balance_eur']);
        $this->assertSame(0.0, $row['collectible_balance_eur']);
    }

    public function test_historical_receipts_are_reconciled_instead_of_recreating_a_paid_debt(): void
    {
        $row = app(LocalCosPortfolioService::class)->clientRow($this->client(), [[
            'id' => 5, 'teamleader_id' => 'project-1',
        ]], [$this->project()], [], [[
            'deal_id' => 5, 'phasenum' => 1, 'pagado' => 1, 'source' => null, 'monto' => 1000,
        ]]);
        $this->assertSame(0.0, $row['known_balance_eur']);
        $this->assertSame('no_recorded_balance', $row['status']);
    }

    public function test_duplicate_hubspot_records_without_projects_are_not_summed_as_separate_debts(): void
    {
        $row = app(LocalCosPortfolioService::class)->clientRow($this->client(), [
            ['id' => 5, 'hubspot_id' => 'HS-1', 'fase_1_preestab' => '1000 EUR'],
            ['id' => 6, 'hubspot_id' => 'HS-1', 'fase_1_preestab' => '1000 EUR'],
        ], []);
        $this->assertSame(0.0, $row['known_balance_eur']);
        $this->assertSame('review', $row['status']);
    }

    public function test_the_global_report_does_not_add_imported_payment_totals_to_their_portal_receipts(): void
    {
        $row = app(LocalCosPortfolioService::class)->clientRow($this->client(), [[
            'id' => 5, 'teamleader_id' => 'project-1',
        ]], [$this->project(['phase_fields' => [1 => ['preestab' => '2000 EUR', 'paid' => '1000 EUR']]])], [], [
            ['deal_id' => 5, 'phasenum' => 1, 'pagado' => 1, 'source' => null, 'monto' => 1000],
            ['deal_id' => 5, 'phasenum' => 1, 'pagado' => 1, 'source' => 'teamleader_phase_history', 'monto' => 1000],
        ]);
        $this->assertSame(1000.0, $row['known_balance_eur']);
        $this->assertSame('debt', $row['status']);
    }
}
