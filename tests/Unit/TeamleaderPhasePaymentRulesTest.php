<?php

namespace Tests\Unit;

use App\Models\Compras;
use App\Models\TlProject;
use App\Services\TeamleaderProjectPaymentAnalyzer;
use App\Services\TeamleaderPhasePaymentService;
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
            'status' => 'on_hold',
            'customer' => ['id' => 'contact-1', 'type' => 'contact'],
            'participants' => [['participant' => ['id' => 'user-1', 'type' => 'user'], 'role' => 'decision_maker']],
            'milestones' => [['name' => 'Inicio', 'starts_on' => '2026-09-01']],
            'budget' => ['amount' => 1200, 'currency' => 'EUR'],
            'starts_on' => '2026-09-01',
            'purchase_order_number' => 'PO-2026-001',
        ], [
            ['id' => 'payment-field', 'value' => '500 EUR'],
        ]);

        $this->assertSame('Proyecto de prueba', $payload['title']);
        $this->assertSame('Descripción vigente', $payload['description']);
        $this->assertSame(['id' => 'contact-1', 'type' => 'contact'], $payload['customer']);
        $this->assertSame(['amount' => 1200, 'currency' => 'EUR'], $payload['budget']);
        $this->assertSame('on_hold', $payload['status']);
        $this->assertSame('PO-2026-001', $payload['purchase_order_number']);
        $this->assertSame([['id' => 'payment-field', 'value' => '500 EUR']], $payload['custom_fields']);
    }

    public function test_full_updater_merges_changes_without_discarding_existing_fields(): void
    {
        $teamleader = $this->createMock(TeamleaderService::class);
        $teamleader->expects($this->once())
            ->method('getProjectDetails')
            ->with('project-full-update')
            ->willReturn([
                'id' => 'project-full-update',
                'title' => 'Expediente vigente',
                'status' => 'active',
                'custom_fields' => [
                    ['definition' => ['id' => 'keep'], 'value' => 'Conservar'],
                    ['definition' => ['id' => 'change'], 'value' => 'Anterior'],
                ],
            ]);
        $teamleader->expects($this->once())
            ->method('updateProject')
            ->with('project-full-update', $this->callback(function (array $payload): bool {
                $fields = collect($payload['custom_fields'])->keyBy('id');

                return $payload['title'] === 'Expediente vigente'
                    && $payload['status'] === 'active'
                    && $fields->get('keep')['value'] === 'Conservar'
                    && $fields->get('change')['value'] === 'Nuevo';
            }));

        $writer = new TeamleaderProjectPaymentWriter($teamleader);
        $updater = new \App\Services\TeamleaderProjectFullUpdater($teamleader, $writer);
        $updater->updateCustomFields('project-full-update', [
            ['id' => 'change', 'value' => 'Nuevo'],
        ]);
    }
    public function test_historical_phase_abonos_reconcile_with_teamleader_without_double_counting(): void
    {
        $service = new TeamleaderPhasePaymentService();
        $reconciledPaid = new \ReflectionMethod($service, 'reconciledPaidAmount');

        $this->assertSame(2491.9, $reconciledPaid->invoke($service, 2491.9, 2491.9));
        $this->assertSame(2491.9, $reconciledPaid->invoke($service, 0.0, 2491.9));
        $this->assertSame(2500.0, $reconciledPaid->invoke($service, 2500.0, 2491.9));
    }
    public function test_a_balance_below_fifty_is_not_collectible_but_keeps_the_actual_paid_amount(): void
    {
        $service = new TeamleaderPhasePaymentService();
        $pending = new \ReflectionMethod($service, 'isPortalPaymentPending');
        $recordAmount = new \ReflectionMethod($service, 'portalRecordAmount');

        $this->assertFalse($pending->invoke($service, 49.99));
        $this->assertTrue($pending->invoke($service, 50.00));
        $this->assertSame(2491.9, $recordAmount->invoke($service, 2491.9, 10.1));
        $this->assertSame(750.0, $recordAmount->invoke($service, 0.0, 750.0));
    }

    public function test_portal_exposes_only_the_next_sequential_phase_for_each_project(): void
    {
        $service = new TeamleaderPhasePaymentService();
        $purchase = function (int $id, string $projectId, int $phase): Compras {
            $record = new Compras([
                'source' => TeamleaderPhasePaymentService::PURCHASE_SOURCE,
                'pagado' => 0,
                'monto' => 100,
                'phasenum' => $phase,
                'metadata' => [
                    'teamleader_project_id' => $projectId,
                    'phase' => $phase,
                ],
            ]);
            $record->id = $id;

            return $record;
        };

        $available = $service->currentPortalPurchases(collect([
            $purchase(101, 'project-a', 1),
            $purchase(102, 'project-a', 2),
            $purchase(103, 'project-a', 3),
            $purchase(104, 'project-a', 98),
            $purchase(201, 'project-b', 2),
            $purchase(202, 'project-b', 3),
        ]));

        $this->assertSame([101, 104, 201], $available->pluck('id')->all());
    }
    public function test_a_review_required_phase_blocks_following_phases_without_becoming_payable(): void
    {
        $service = new TeamleaderPhasePaymentService();
        $purchase = function (int $id, int $phase, string $disposition = 'pending'): Compras {
            $record = new Compras([
                'source' => TeamleaderPhasePaymentService::PURCHASE_SOURCE,
                'pagado' => 0,
                'monto' => 100,
                'phasenum' => $phase,
                'metadata' => [
                    'teamleader_project_id' => 'project-review-block',
                    'phase' => $phase,
                    'portal_disposition' => $disposition,
                ],
            ]);
            $record->id = $id;

            return $record;
        };

        $available = $service->currentPortalPurchases(collect([
            $purchase(102, 2, 'review_required'),
            $purchase(103, 3),
        ]));

        $this->assertTrue($available->isEmpty());
    }
    public function test_payment_writer_appends_an_installment_without_replacing_previous_teamleader_entries(): void
    {
        $paidFieldId = 'a1b50c58-8175-0d13-9856-f661e783dc08';
        $teamleader = $this->createMock(TeamleaderService::class);
        $teamleader->expects($this->once())
            ->method('getProjectDetails')
            ->with('project-installments')
            ->willReturn([
                'id' => 'project-installments',
                'title' => 'Expediente de prueba',
                'custom_fields' => [
                    [
                        'definition' => ['id' => $paidFieldId],
                        'value' => 'Abono 100 EUR 2026-05-18 [COS:purchase-previous]',
                    ],
                    [
                        'definition' => ['id' => 'unrelated-field'],
                        'value' => 'Conservar esta información',
                    ],
                ],
            ]);
        $teamleader->expects($this->once())
            ->method('updateProject')
            ->with('project-installments', $this->callback(function (array $payload) use ($paidFieldId): bool {
                $fields = collect($payload['custom_fields'])->keyBy('id');

                return $fields->get($paidFieldId)['value']
                    === 'Abono 100 EUR 2026-05-18 [COS:purchase-previous] + Abono 50 EUR 2026-09-07 [COS:purchase-123]'
                    && $fields->get('unrelated-field')['value'] === 'Conservar esta información';
            }));

        (new TeamleaderProjectPaymentWriter($teamleader))->appendPhasePayment(
            'project-installments',
            1,
            50.00,
            '2026-09-07',
            'purchase-123'
        );
    }

    public function test_payment_writer_uses_its_reference_to_avoid_a_duplicate_installment(): void
    {
        $teamleader = $this->createMock(TeamleaderService::class);
        $teamleader->expects($this->once())
            ->method('getProjectDetails')
            ->willReturn([
                'id' => 'project-idempotent',
                'custom_fields' => [[
                    'definition' => ['id' => 'a1b50c58-8175-0d13-9856-f661e783dc08'],
                    'value' => 'Abono 50 EUR 2026-09-07 [COS:purchase-123]',
                ]],
            ]);
        $teamleader->expects($this->never())->method('updateProject');

        (new TeamleaderProjectPaymentWriter($teamleader))->appendPhasePayment(
            'project-idempotent',
            1,
            50.00,
            '2026-09-07',
            'purchase-123'
        );
    }

    public function test_usd_amounts_wait_for_date_based_conversion_before_affecting_euro_debt(): void
    {
        $analyzer = new TeamleaderProjectPaymentAnalyzer();
        $parsed = $analyzer->parseMoneyText('Abono 100 USD 2026-05-18 + Abono 40 EUR 2026-06-01');

        $this->assertSame([40.0], $parsed['amounts']);
        $this->assertSame(['USD'], $parsed['foreign_currencies']);
        $this->assertTrue($parsed['requires_currency_conversion']);
        $this->assertSame(['2026-05-18', '2026-06-01'], $parsed['payment_dates']);

        $project = new TlProject([
            'id' => 'project-usd-review',
            'custom_fields' => [
                ['definition' => ['id' => '73173887-a0e8-0f4f-bb55-b61f33d3c6e9'], 'value' => '300 EUR'],
                ['definition' => ['id' => 'a1b50c58-8175-0d13-9856-f661e783dc08'], 'value' => 'Abono 100 USD 2026-05-18'],
            ],
        ]);
        $phase = $analyzer->analyzeProject($project)['phases'][1];

        $this->assertSame(0.0, $phase['effective_paid_amount']);
        $this->assertSame(300.0, $phase['balance_amount']);
        $this->assertSame('review', $phase['status']);
        $this->assertSame(['currency_conversion_required'], $phase['review_reasons']);
    }
}
