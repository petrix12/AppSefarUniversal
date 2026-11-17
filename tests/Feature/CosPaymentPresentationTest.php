<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class CosPaymentPresentationTest extends TestCase
{
    public function test_complete_summary_shows_the_paid_total_and_the_portal_installment(): void
    {
        $html = $this->summary(false);

        $this->assertStringContainsString('3.500 EUR', $html);
        $this->assertStringContainsString('51,36 EUR pagados en la App', $html);
        $this->assertStringContainsString('0 EUR', $html);
        $this->assertStringNotContainsString('Pendiente de cálculo', $html);
        $this->assertStringNotContainsString('sobrepago', mb_strtolower($html));
    }

    public function test_missing_conversion_does_not_present_a_partial_balance_as_definitive(): void
    {
        $html = $this->summary(true);

        $this->assertStringContainsString('Pagado / abonado (parcial)', $html);
        $this->assertStringContainsString('Pendiente de cálculo', $html);
        $this->assertStringNotContainsString('1.796,02 EUR', $html);
    }

    public function test_receipt_keeps_the_cents_in_the_line_and_total(): void
    {
        $html = view('crud.comprobantes.pdfintel', [
            'datos_factura' => [[
                'id' => 1, 'name' => 'Cliente de prueba', 'passport' => 'TEST-ID',
                'email' => 'test@example.test', 'phone' => '', 'fecha_de_registro' => '2020-01-01',
                'met' => 'stripe', 'idcus' => null, 'idcharge' => null,
                'created_at' => '2026-11-14', 'hash_factura' => 'test-receipt',
            ]],
            'productos' => [['descripcion' => 'Abono Carta de Naturaleza', 'monto' => 51.36]],
        ])->render();

        $this->assertSame(2, substr_count($html, '51,36€'));
        $this->assertStringNotContainsString('>51€', $html);
    }

    public function test_internal_crm_invoice_information_is_hidden_in_client_preview(): void
    {
        config(['crm_sync.hubspot.portal_id' => '20053496']);
        $data = ['purchases' => [['source' => 'teamleader_phase_installment', 'metadata' => [
            'crm_payment_sync' => ['providers' => ['hubspot' => [
                'status' => 'synced', 'invoice_status' => 'paid', 'remote_invoice_id' => '9000123',
            ], 'teamleader' => ['status' => 'synced']]],
        ]]]];
        $admin = view('crud.users.partials.payment-crm-status', $data + ['cosViewRoleId' => 1])->render();
        $client = view('crud.users.partials.payment-crm-status', $data + ['cosViewRoleId' => 5])->render();
        $this->assertStringContainsString('Factura HubSpot #9000123', $admin);
        $this->assertStringContainsString('Pagada', $admin);
        $this->assertStringContainsString('/record/0-53/9000123', $admin);
        $this->assertStringNotContainsString('HubSpot', $client);
        $this->assertStringNotContainsString('Teamleader', $client);
        $this->assertStringNotContainsString('9000123', $client);
    }

    private function summary(bool $pending): string
    {
        // Render the actual finance fragment of the large COS view, without
        // booting unrelated CRM, genealogy or payment collection screens.
        $template = file_get_contents(resource_path('views/crud/users/edit.blade.php'));
        $start = strpos($template, '@if($rolId !== 5)'."\n                        @php\n                            \$tlPaymentTotals");
        $end = strpos($template, '@if($rolId !== 5)'."\n                        @php\n                            \$tlContactMain", $start);
        $this->assertNotFalse($start);
        $this->assertNotFalse($end);
        return Blade::render(substr($template, $start, $end - $start), [
            'rolId' => 1,
            'teamleaderProjectPayments' => ['totals' => [
                'projects' => 1, 'preestab_amount' => 3500, 'paid_amount' => $pending ? 1703.98 : 3500,
                'balance_amount' => $pending ? 1796.02 : 0, 'conversion_pending_count' => $pending ? 1 : 0,
            ]],
            'teamleaderMigration' => ['contact' => null], 'comprasSinDealNoPagadas' => collect(),
            'statusPaymentRows' => [[
                'project_id' => 'test-project', 'source' => 'HubSpot', 'phase' => 98,
                'project_title' => 'Carta de Naturaleza · Caso de prueba', 'payment_label' => 'Carta de Naturaleza',
                'status' => $pending ? 'review' : 'paid', 'display_status' => $pending ? 'conversion_pending' : 'paid',
                'effective_preestab_amount' => 3500, 'effective_paid_amount' => $pending ? 1703.98 : 3500,
                'balance_amount' => $pending ? 1796.02 : 0, 'collectible_balance_amount' => 0,
                'preestab_raw' => '3500€', 'paid_raw' => 'Abono 1736.11$ 28/10/2022 + 1853.76$ 23/03/2023',
                'payment_total_complete' => ! $pending, 'portal_paid_amount' => $pending ? 0 : 51.36,
            ]],
        ], deleteCachedView: true);
    }
}
