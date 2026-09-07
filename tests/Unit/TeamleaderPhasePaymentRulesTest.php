<?php

namespace Tests\Unit;

use App\Models\TlProject;
use App\Services\TeamleaderProjectPaymentAnalyzer;
use App\Services\TeamleaderProjectPaymentWriter;
use App\Services\TeamleaderService;
use PHPUnit\Framework\TestCase;

class TeamleaderPhasePaymentRulesTest extends TestCase
{
    public function test_each_phase_is_evaluated_as_an_independent_payment(): void
    {
        $project = new TlProject([
            'id' => 'project-independent-phases',
            'custom_fields' => [
                ['definition' => ['id' => '73173887-a0e8-0f4f-bb55-b61f33d3c6e9'], 'value' => '500 EUR'],
                ['definition' => ['id' => 'a1b50c58-8175-0d13-9856-f661e783dc08'], 'value' => '500 EUR'],
                ['definition' => ['id' => 'c66a9c15-c965-0812-ad5b-7e48f183c6f9'], 'value' => '1000 EUR'],
                ['definition' => ['id' => 'e41fdbbb-a25a-005b-af56-9f3ca623c700'], 'value' => '300 EUR'],
                ['definition' => ['id' => '9a1df9b7-c92f-09e5-b156-96af3f83dc0e'], 'value' => '350 EUR'],
            ],
        ]);

        $phases = (new TeamleaderProjectPaymentAnalyzer())->analyzeProject($project)['phases'];

        $this->assertSame('paid', $phases[1]['status']);
        $this->assertSame('pending', $phases[2]['status']);
        $this->assertSame(1000.0, $phases[2]['balance_amount']);
        $this->assertSame('review', $phases[3]['status']);
        $this->assertSame(50.0, $phases[3]['overpaid_amount']);
    }
    public function test_miscellaneous_preestablished_payments_are_independent(): void
    {
        $project = new TlProject([
            'id' => 'project-miscellaneous-payments',
            'custom_fields' => [
                ['definition' => ['id' => 'a42ed217-b570-0973-9052-fab97214c229'], 'value' => '450 EUR'],
                ['definition' => ['id' => 'aa1ce4b9-a410-00f2-a953-5f8c2713dc35'], 'value' => '800 EUR'],
                ['definition' => ['id' => 'f23fbe3b-5d13-0a41-a857-e9ab1c63dc42'], 'value' => '800 EUR'],
            ],
        ]);

        $payments = (new TeamleaderProjectPaymentAnalyzer())->analyzeProject($project)['phases'];

        $this->assertSame('Carta de Naturaleza', $payments[98]['payment_label']);
        $this->assertSame('pending', $payments[98]['status']);
        $this->assertSame(450.0, $payments[98]['balance_amount']);
        $this->assertSame('CIL / FCJE', $payments[99]['payment_label']);
        $this->assertSame('paid', $payments[99]['status']);
        $this->assertSame(0.0, $payments[99]['balance_amount']);
    }


    public function test_project_update_payload_preserves_mutable_project_data(): void
    {
        $writer = new TeamleaderProjectPaymentWriter(new TeamleaderService());

        $payload = $writer->projectUpdatePayload([
            'title' => 'Proyecto de prueba',
            'description' => 'Descripción vigente',
            'customer' => ['id' => 'contact-1', 'type' => 'contact'],
            'participants' => [['participant' => ['id' => 'user-1', 'type' => 'user'], 'role' => 'decision_maker']],
            'milestones' => [['name' => 'Inicio', 'starts_on' => '2026-09-01']],
            'budget' => ['amount' => 1200, 'currency' => 'EUR'],
            'starts_on' => '2026-09-01',
            'due_on' => '2026-12-01',
        ], [
            ['id' => 'payment-field', 'value' => '500 EUR'],
        ]);

        $this->assertSame('Proyecto de prueba', $payload['title']);
        $this->assertSame('Descripción vigente', $payload['description']);
        $this->assertSame(['id' => 'contact-1', 'type' => 'contact'], $payload['customer']);
        $this->assertSame(['amount' => 1200, 'currency' => 'EUR'], $payload['budget']);
        $this->assertCount(1, $payload['participants']);
        $this->assertCount(1, $payload['milestones']);
        $this->assertSame([['id' => 'payment-field', 'value' => '500 EUR']], $payload['custom_fields']);
    }
}
