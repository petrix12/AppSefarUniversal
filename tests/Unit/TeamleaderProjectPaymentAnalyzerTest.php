<?php

namespace Tests\Unit;

use App\Models\TlProject;
use App\Services\TeamleaderProjectPaymentAnalyzer;
use PHPUnit\Framework\TestCase;

class TeamleaderProjectPaymentAnalyzerTest extends TestCase
{
    public function test_it_extracts_euro_amount_and_ignores_dates(): void
    {
        $parsed = $this->analyzer()->parseMoneyText('1100.53€ 18/05/2026');

        $this->assertSame([1100.53], $parsed['amounts']);
        $this->assertSame(1100.53, $parsed['total']);
    }

    public function test_it_sums_abonos_in_the_same_field(): void
    {
        $parsed = $this->analyzer()->parseMoneyText('Abono 1500 Euros + Abono 1400');

        $this->assertSame([1500.0, 1400.0], $parsed['amounts']);
        $this->assertSame(2900.0, $parsed['total']);
    }

    public function test_it_understands_european_decimal_format(): void
    {
        $parsed = $this->analyzer()->parseMoneyText('1.100,53 € 18/05/2026');

        $this->assertSame([1100.53], $parsed['amounts']);
        $this->assertSame(1100.53, $parsed['total']);
    }

    public function test_it_marks_exonerated_without_treating_dates_as_amounts(): void
    {
        $parsed = $this->analyzer()->parseMoneyText('EXONERADO 2026/05/18');

        $this->assertTrue($parsed['exonerated']);
        $this->assertSame([], $parsed['amounts']);
        $this->assertSame(0.0, $parsed['total']);
    }

    public function test_it_does_not_treat_phase_number_as_amount_for_included_text(): void
    {
        $parsed = $this->analyzer()->parseMoneyText('INCLUIDO EN FASE 1 2026/05/18');

        $this->assertTrue($parsed['included']);
        $this->assertSame([], $parsed['amounts']);
        $this->assertSame(0.0, $parsed['total']);
    }

    public function test_it_calculates_phase_balance_from_teamleader_project_fields(): void
    {
        $project = new TlProject([
            'id' => 'project-1',
            'title' => 'Proyecto demo',
            'custom_fields' => [
                [
                    'definition' => ['id' => '73173887-a0e8-0f4f-bb55-b61f33d3c6e9', 'label' => 'Fase 1 Preestab'],
                    'value' => '3000 Euros 01/05/2026',
                ],
                [
                    'definition' => ['id' => 'a1b50c58-8175-0d13-9856-f661e783dc08', 'label' => 'Fase 1 Pagado'],
                    'value' => 'Abono 1500 Euros + Abono 400',
                ],
            ],
        ]);

        $analysis = $this->analyzer()->analyzeProject($project);

        $this->assertSame(3000.0, $analysis['phases'][1]['effective_preestab_amount']);
        $this->assertSame(1900.0, $analysis['phases'][1]['effective_paid_amount']);
        $this->assertSame(1100.0, $analysis['phases'][1]['balance_amount']);
        $this->assertSame('partial', $analysis['phases'][1]['status']);
    }

    private function analyzer(): TeamleaderProjectPaymentAnalyzer
    {
        return new TeamleaderProjectPaymentAnalyzer();
    }
}
