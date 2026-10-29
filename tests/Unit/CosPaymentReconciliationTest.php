<?php

namespace Tests\Unit;

use App\Models\Compras;
use App\Models\Negocio;
use App\Services\CosPaymentReconciliationService;
use App\Services\TeamleaderPhasePaymentService;
use PHPUnit\Framework\TestCase;

class CosPaymentReconciliationTest extends TestCase
{
    private function deal(int $id = 1, string $project = 'project-1'): Negocio
    {
        return new Negocio(['id' => $id, 'hubspot_id' => 'HS-'.$id, 'teamleader_id' => $project]);
    }

    private function purchase(int $id, array $overrides = []): Compras
    {
        $purchase = new Compras(array_replace(['deal_id' => 1, 'phasenum' => 1, 'monto' => 2000, 'source' => 'legacy', 'metadata' => []], $overrides));
        $purchase->id = $id;
        return $purchase;
    }

    private function service(): CosPaymentReconciliationService
    {
        return new CosPaymentReconciliationService(new TeamleaderPhasePaymentService());
    }

    private function phase(int $id = 2, array $overrides = []): Compras
    {
        return $this->purchase($id, array_replace(['deal_id' => null, 'source' => 'teamleader_phase', 'metadata' => ['teamleader_project_id' => 'project-1', 'phase' => 1]], $overrides));
    }

    public function test_the_same_2000_phase_in_hubspot_and_teamleader_is_2000_not_4000(): void
    {
        $result = $this->service()->reconcile(collect([$this->deal()]), collect([$this->purchase(1), $this->phase()]), collect(), collect());
        $this->assertSame(2000.0, $result['summary']->first()['debt']);
        $this->assertSame([2], $result['pending']->pluck('id')->all());
        $this->assertSame(2000.0, $result['pending']->first()->monto);
    }

    public function test_two_distinct_processes_with_the_same_amount_are_still_two_debts(): void
    {
        $result = $this->service()->reconcile(collect([$this->deal(), $this->deal(2, 'project-2')]), collect([
            $this->purchase(1), $this->purchase(2, ['deal_id' => 2]),
        ]), collect(), collect());
        $this->assertSame(4000.0, $result['summary']->first()['debt']);
    }

    public function test_distinct_phases_are_not_deduplicated(): void
    {
        $result = $this->service()->reconcile(collect([$this->deal()]), collect([
            $this->phase(), $this->phase(3, ['phasenum' => 2, 'metadata' => ['teamleader_project_id' => 'project-1', 'phase' => 2]]),
        ]), collect(), collect());
        $this->assertSame(4000.0, $result['summary']->first()['debt']);
    }

    public function test_genuine_installment_receipts_are_preserved_but_the_imported_paid_mirror_is_removed(): void
    {
        $paid = collect([
            $this->purchase(1, ['source' => 'teamleader_phase_installment', 'monto' => 200]),
            $this->purchase(2, ['source' => 'teamleader_phase_installment', 'monto' => 200]),
            $this->phase(3, ['source' => 'teamleader_phase_history', 'monto' => 400]),
        ]);
        $result = $this->service()->reconcile(collect([$this->deal()]), collect(), $paid, collect());
        $this->assertSame(400.0, $result['summary']->first()['paid']);
        $this->assertSame([1, 2], $result['paid']->pluck('id')->all());
    }

    public function test_field_rows_and_actual_payable_records_do_not_add_the_same_debt_twice(): void
    {
        $row = ['deal_id' => 1, 'phase' => 1, 'source' => 'HubSpot', 'project_id' => 'HS-1', 'effective_paid_amount' => 0, 'collectible_balance_amount' => 2000];
        $result = $this->service()->reconcile(collect([$this->deal()]), collect([$this->purchase(1), $this->phase()]), collect(), collect([$row, $row]));
        $this->assertSame(2000.0, $result['summary']->first()['debt']);
        $this->assertCount(1, $result['rows']);
        $this->assertCount(0, $result['pending_rows']);
    }

    public function test_a_hidden_phase_is_not_reintroduced_through_its_hubspot_mirror(): void
    {
        $row = ['deal_id' => 1, 'phase' => 1, 'project_id' => 'HS-1', 'collectible_balance_amount' => 2000];
        $phase = $this->phase(2, ['metadata' => ['teamleader_project_id' => 'project-1', 'phase' => 1, 'hidden_from_client' => true]]);
        $result = $this->service()->reconcile(collect([$this->deal()]), collect([$this->purchase(1), $phase]), collect(), collect([$row]));
        $this->assertSame(0.0, $result['summary']->first()['debt']);
        $this->assertCount(0, $result['pending_rows']);
    }

    public function test_generic_purchases_and_different_currencies_are_not_collapsed(): void
    {
        $result = $this->service()->reconcile(collect([$this->deal()]), collect([
            $this->purchase(1), $this->phase(2, ['metadata' => ['teamleader_project_id' => 'project-1', 'phase' => 1, 'display_currency' => 'USD']]),
            $this->purchase(3, ['deal_id' => null, 'phasenum' => null]),
        ]), collect(), collect());
        $this->assertCount(3, $result['pending']);
        $this->assertSame(4000.0, $result['summary']->firstWhere('currency', 'EUR')['debt']);
        $this->assertSame(2000.0, $result['summary']->firstWhere('currency', 'USD')['debt']);
    }

    public function test_a_partial_portal_receipt_does_not_hide_the_rest_of_the_recorded_payment(): void
    {
        $row = ['project_id' => 'project-1', 'source' => 'Teamleader', 'phase' => 1, 'effective_paid_amount' => 1000, 'balance_amount' => 1000];
        $paid = collect([
            $this->purchase(1, ['monto' => 200]),
            $this->phase(2, ['monto' => 1000, 'source' => 'teamleader_phase_history']),
        ]);
        $result = $this->service()->reconcile(collect([$this->deal()]), collect(), $paid, collect([$row]));
        $this->assertSame(1000.0, $result['summary']->first()['paid']);
        $this->assertSame(800.0, $result['paid_rows']->first()['display_amount']);
        $this->assertSame([1], $result['paid']->pluck('id')->all());
    }

    public function test_a_settled_teamleader_phase_does_not_leave_its_old_hubspot_charge_pending(): void
    {
        $result = $this->service()->reconcile(collect([$this->deal()]), collect([$this->purchase(1)]), collect([
            $this->phase(2, ['pagado' => 1]),
        ]), collect());
        $this->assertCount(0, $result['pending']);
        $this->assertSame(0.0, $result['summary']->first()['debt']);
        $this->assertSame(2000.0, $result['summary']->first()['paid']);
    }

    public function test_stale_crm_fields_do_not_top_up_an_actual_reconciled_phase_balance(): void
    {
        $row = ['deal_id' => 1, 'phase' => 1, 'source' => 'HubSpot', 'project_id' => 'HS-1', 'collectible_balance_amount' => 2000];
        $result = $this->service()->reconcile(collect([$this->deal()]), collect([
            $this->purchase(1), $this->phase(2, ['monto' => 1000]),
        ]), collect(), collect([$row]));
        $this->assertSame(1000.0, $result['summary']->first()['debt']);
        $this->assertCount(0, $result['pending_rows']);
    }

    public function test_a_paid_phase_summary_does_not_duplicate_its_imported_payment_entries(): void
    {
        $result = $this->service()->reconcile(collect([$this->deal()]), collect(), collect([
            $this->phase(1, ['monto' => 1000]),
            $this->phase(2, ['monto' => 600, 'source' => 'teamleader_phase_history']),
            $this->phase(3, ['monto' => 400, 'source' => 'teamleader_phase_history']),
        ]), collect());
        $this->assertSame(1000.0, $result['summary']->first()['paid']);
        $this->assertSame([2, 3], $result['paid']->pluck('id')->all());
    }
}
