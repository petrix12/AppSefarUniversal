<?php

namespace Tests\Unit;

use App\Models\Negocio;
use App\Models\TlProject;
use App\Services\HubspotDealPaymentAnalyzer;
use App\Services\TeamleaderProjectPaymentAnalyzer;
use PHPUnit\Framework\TestCase;

class HubspotDealPaymentAnalyzerTest extends TestCase
{
    public function test_hubspot_phase_values_take_priority_over_the_linked_historical_project(): void
    {
        $deal = new Negocio([
            'id' => 91,
            'hubspot_id' => 'hubspot-deal-91',
            'dealname' => 'Expediente CRM',
            'fase_1_preestab' => '1.000 EUR',
            'monto_fase_1_pagado' => 300,
        ]);
        $history = new TlProject([
            'id' => 'tl-project-91',
            'custom_fields' => [
                ['definition' => ['id' => '73173887-a0e8-0f4f-bb55-b61f33d3c6e9'], 'value' => '500 EUR'],
                ['definition' => ['id' => 'a1b50c58-8175-0d13-9856-f661e783dc08'], 'value' => '500 EUR'],
            ],
        ]);

        $phase = $this->analyzer()->analyzeDeal($deal, $history)['phases'][1];

        $this->assertSame('hubspot', $phase['payment_origin']);
        $this->assertSame(1000.0, $phase['effective_preestab_amount']);
        $this->assertSame(300.0, $phase['effective_paid_amount']);
        $this->assertSame(700.0, $phase['balance_amount']);
        $this->assertSame('partial', $phase['status']);
    }

    public function test_linked_teamleader_phase_is_used_only_when_hubspot_has_no_phase_data(): void
    {
        $deal = new Negocio([
            'id' => 92,
            'hubspot_id' => 'hubspot-deal-92',
            'dealname' => 'Expediente sin pagos migrados a CRM',
        ]);
        $history = new TlProject([
            'id' => 'tl-project-92',
            'custom_fields' => [
                ['definition' => ['id' => '73173887-a0e8-0f4f-bb55-b61f33d3c6e9'], 'value' => '750 EUR'],
                ['definition' => ['id' => 'a1b50c58-8175-0d13-9856-f661e783dc08'], 'value' => '250 EUR'],
            ],
        ]);

        $analysis = $this->analyzer()->analyzeDeal($deal, $history);
        $phase = $analysis['phases'][1];

        $this->assertSame('teamleader_history', $phase['payment_origin']);
        $this->assertSame(500.0, $phase['balance_amount']);
        $this->assertSame('hubspot:92', $analysis['project_id']);
        $this->assertSame('tl-project-92', $analysis['legacy_teamleader_project_id']);
    }

    private function analyzer(): HubspotDealPaymentAnalyzer
    {
        return new HubspotDealPaymentAnalyzer(new TeamleaderProjectPaymentAnalyzer());
    }
}
