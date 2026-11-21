<?php

namespace Tests\Feature;

use App\Models\Compras;
use App\Models\Factura;
use App\Models\Negocio;
use App\Models\User;
use App\Services\CosPaymentCrmSyncService;
use App\Services\Payments\ConfirmedPaymentInvoiceService;
use App\Services\TeamleaderPhasePaymentService;
use App\Services\TeamleaderProjectPaymentAnalyzer;
use App\Services\TeamleaderService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CosPaymentCrmSyncTest extends TestCase
{
    private array $remote = ['invoices' => [], 'line_items' => [], 'commerce_payments' => []];
    private array $creates = [];
    private string $paidText = 'Abono 3448.64 EUR 2026-01-01';
    private ?string $loseResponse = null;
    private bool $remoteAccepted = true;
    private string $contactEmail = 'client@example.com';
    private array $notes = [];
    private array $project = [];
    private int $projectWrites = 0;
    private bool $loseNoteResponse = false;
    private ?int $hubspotRejection = null;

    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array', 'services.hubspot.key' => 'test-only',
            'app.url' => 'https://app.example.test', 'cos_payments.crm_sync_enabled' => true]);
        Cache::flush();
        Http::preventStrayRequests();
        Schema::create('users', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('email'); $t->string('passport')->nullable();
            $t->string('hs_id')->nullable(); $t->string('tl_id')->nullable(); $t->timestamps();
        });
        Schema::create('facturas', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('id_cliente'); $t->string('hash_factura');
            $t->string('met')->nullable(); $t->string('idcharge')->nullable(); $t->string('idcus')->nullable(); $t->timestamps();
        });
        Schema::create('negocios', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('user_id'); $t->string('teamleader_id')->nullable();
            $t->string('hubspot_id')->nullable(); $t->timestamps();
        });
        Schema::create('compras', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('id_user'); $t->string('source')->nullable();
            $t->string('servicio_hs_id')->nullable(); $t->text('descripcion')->nullable(); $t->integer('pagado')->default(0);
            $t->decimal('monto', 12, 2); $t->unsignedBigInteger('deal_id')->nullable(); $t->integer('phasenum')->nullable();
            $t->decimal('montooriginal', 12, 2)->nullable(); $t->integer('porcentajedescuento')->nullable();
            $t->string('hash_factura')->nullable(); $t->json('metadata')->nullable(); $t->timestamp('paid_at')->nullable(); $t->timestamps();
        });
        \Illuminate\Support\Facades\DB::table('users')->insert(['id' => 290, 'name' => 'Cliente', 'email' => 'client@example.com', 'passport' => 'P-290',
            'hs_id' => '19063', 'tl_id' => '4a853f3d-0c10-0b3d-9c7d-20d3a2be67ad']);
        Negocio::create(['id' => 2377, 'user_id' => 290, 'hubspot_id' => '6867596984',
            'teamleader_id' => 'e93270e9-4065-0f1d-bc62-63e3431a1a0a']);
        Factura::forceCreate(['id' => 2035, 'id_cliente' => 290, 'hash_factura' => 'existing-document', 'met' => 'stripe']);
    }

    public function test_checkout_retries_keep_one_receipt_and_reduce_the_balance_once(): void
    {
        $balance = Compras::create(['id_user' => 290, 'source' => TeamleaderPhasePaymentService::PURCHASE_SOURCE,
            'monto' => 51.36, 'phasenum' => 98, 'metadata' => ['phase' => 98,
                'teamleader_project_id' => 'e93270e9-4065-0f1d-bc62-63e3431a1a0a', 'paid_amount_at_sync' => 3448.64]]);
        $service = app(TeamleaderPhasePaymentService::class);
        $receipt = $service->recordPortalInstallment($balance, 51.36, 'existing-document', Carbon::parse('2026-11-14'), 'gateway-1');
        $again = $service->recordPortalInstallment($balance, 51.36, 'different-retry-hash', now(), 'gateway-1');
        $this->assertSame($receipt->id, $again->id);
        $this->assertSame('existing-document', $again->hash_factura);
        $this->assertSame(0.0, $balance->fresh()->monto);
        $this->assertSame(1, $balance->fresh()->pagado);
        $this->assertSame(2, Compras::count());
        $this->assertSame(1, Factura::count());
        $this->assertSame('pending', data_get($again->metadata, 'crm_payment_sync.providers.hubspot.status'));
        Http::assertNothingSent();
    }

    public function test_retrying_a_partial_installment_does_not_consume_the_remaining_balance(): void
    {
        $balance = Compras::create(['id_user' => 290, 'source' => TeamleaderPhasePaymentService::PURCHASE_SOURCE,
            'monto' => 100, 'phasenum' => 98, 'metadata' => []]);
        $service = app(TeamleaderPhasePaymentService::class);
        $one = $service->recordPortalInstallment($balance, 25, 'existing-document', now(), 'gateway-1');
        $two = $service->recordPortalInstallment($balance, 25, 'existing-document', now(), 'gateway-1');
        $this->assertSame($one->id, $two->id);
        $this->assertSame(75.0, $balance->fresh()->monto);
        $this->assertSame(2, Compras::count());
        Http::assertNothingSent();
    }

    public function test_one_confirmed_transaction_reuses_its_existing_invoice(): void
    {
        $service = app(ConfirmedPaymentInvoiceService::class);
        $first = $service->getOrCreate(290, 'stripe', 'test-gateway-reference', 'first-hash', 'customer');
        $second = $service->getOrCreate(290, 'stripe', 'test-gateway-reference', 'retry-hash', 'customer');
        $this->assertSame($first->id, $second->id);
        $this->assertSame('first-hash', $second->hash_factura);
        $this->assertSame(2, Factura::count()); // existing fixture plus the new transaction
        $this->assertSame(0, Compras::count());
    }

    public function test_registration_has_a_paid_invoice_even_before_any_deal_or_project_exists(): void
    {
        Negocio::query()->delete();
        $receipt = Compras::create(['id_user' => 290, 'monto' => 50, 'pagado' => 1,
            'hash_factura' => 'existing-document', 'paid_at' => '2026-11-17']);
        $registration = app(\App\Services\Payments\RegistrationPaymentReceiptService::class);
        $registration->record(Factura::findOrFail(2035), [$receipt], 'registration-gateway');
        $this->fakeHubspot(); $this->fakeTeamleader();
        $service = app(CosPaymentCrmSyncService::class); $service->process($receipt->id);
        $state = data_get($receipt->fresh()->metadata, 'crm_payment_sync.providers');
        $this->assertSame('synced', $state['hubspot']['status']);
        $this->assertSame('synced', $state['teamleader']['status']);
        $this->assertSame('registration-payment-'.$receipt->id, $state['hubspot']['reference']);
        $this->assertSame('50.00', array_values($this->remote['invoices'])[0]['properties']['hs_amount_billed']);
        $this->assertSame('paid', array_values($this->remote['invoices'])[0]['properties']['hs_invoice_status']);
        $this->assertSame('contact', $this->notes[0]['subject_type']);
        $this->assertSame(0, $this->projectWrites); $this->assertSame(0, Negocio::count());
        $this->assertSame(1, Compras::count()); $this->assertSame(1, Factura::count());
        $this->assertSame('Abono 3448.64 EUR 2026-01-01', $this->paidText);
        $registration->record(Factura::findOrFail(2035), [$receipt], 'registration-gateway');
        $service->process($receipt->id, null, true);
        $this->assertSame(['invoices' => 1, 'line_items' => 1, 'commerce_payments' => 1], $this->creates);
        $this->assertCount(1, $this->notes);
    }

    public function test_a_full_registration_coupon_has_a_zero_invoice_without_inventing_cash_revenue(): void
    {
        Negocio::query()->delete(); Factura::findOrFail(2035)->update(['met' => 'cupon']);
        $receipt = Compras::create(['id_user' => 290, 'monto' => 50, 'pagado' => 1,
            'hash_factura' => 'existing-document', 'paid_at' => '2026-11-17']);
        app(\App\Services\Payments\RegistrationPaymentReceiptService::class)->record(Factura::findOrFail(2035), [$receipt]);
        $this->fakeHubspot();
        app(CosPaymentCrmSyncService::class)->process($receipt->id, 'hubspot');
        $this->assertSame(0.0, $receipt->fresh()->monto);
        $this->assertSame('50', (string) (float) $receipt->fresh()->montooriginal);
        $this->assertSame(100, (int) $receipt->fresh()->porcentajedescuento);
        $this->assertSame('0.00', array_values($this->remote['invoices'])[0]['properties']['hs_amount_billed']);
        $this->assertSame('paid', array_values($this->remote['invoices'])[0]['properties']['hs_invoice_status']);
        $this->assertSame(0, $this->creates['commerce_payments'] ?? 0);
        $this->assertSame(1, Compras::count()); $this->assertSame(1, Factura::count());
    }

    public function test_a_consultation_receipt_does_not_increase_the_clients_nationality_project_payments(): void
    {
        $receipt = Compras::create(['id_user'=>290, 'source'=>\App\Models\KalendiaConsultationOrder::SOURCE,
            'descripcion'=>'Consulta jurídica', 'monto'=>125.50, 'pagado'=>1, 'hash_factura'=>'existing-document', 'paid_at'=>now()]);
        app(\App\Services\Payments\RegistrationPaymentReceiptService::class)->record(Factura::findOrFail(2035),[$receipt],'consultation-gateway');
        $context=app(CosPaymentCrmSyncService::class)->context($receipt->fresh());
        $this->assertSame('consultation',$context['kind']);$this->assertNull($context['deal']);
        $this->fakeHubspot();$this->fakeTeamleader();
        app(CosPaymentCrmSyncService::class)->process($receipt->id);
        $this->assertSame(0,$this->projectWrites);$this->assertSame('Abono 3448.64 EUR 2026-01-01',$this->paidText);
        $this->assertSame('125.50',array_values($this->remote['invoices'])[0]['properties']['hs_amount_billed']);
        $this->assertSame('synced',data_get($receipt->fresh()->metadata,'crm_payment_sync.providers.hubspot.status'));
        $this->assertSame('synced',data_get($receipt->fresh()->metadata,'crm_payment_sync.providers.teamleader.status'));
        $this->assertDatabaseCount('compras',1);$this->assertDatabaseCount('facturas',1);
    }

    public function test_an_unpaid_registration_cannot_be_queued_as_a_paid_invoice(): void
    {
        $receipt = Compras::create(['id_user' => 290, 'monto' => 50, 'pagado' => 0, 'hash_factura' => 'existing-document']);
        $this->expectException(\InvalidArgumentException::class);
        app(\App\Services\Payments\RegistrationPaymentReceiptService::class)->record(Factura::findOrFail(2035), [$receipt]);
    }

    public function test_native_hubspot_payment_and_paid_invoice_reuse_the_original_local_receipt(): void
    {
        $receipt = $this->receipt();
        $this->fakeHubspot(); $this->fakeTeamleader();
        $service = app(CosPaymentCrmSyncService::class);
        $service->queueReceipt($receipt);
        $service->process($receipt->id);
        $state = data_get($receipt->fresh()->metadata, 'crm_payment_sync');
        $this->assertSame('synced', $state['providers']['hubspot']['status']);
        $this->assertSame('paid', $state['providers']['hubspot']['invoice_status']);
        $this->assertSame('synced', $state['providers']['teamleader']['status']);
        $this->assertFalse($state['pending']);
        $this->assertSame(1, Compras::count()); $this->assertSame(1, Factura::count());
        $this->assertSame(['invoices' => 1, 'line_items' => 1, 'commerce_payments' => 1], $this->creates);
        $this->assertSame(1, $this->projectWrites); $this->assertCount(1, $this->notes);
        $this->assertSame('contact', $this->notes[0]['subject_type']);
        $this->assertStringContainsString('Proyecto Teamleader: e93270e9-4065-0f1d-bc62-63e3431a1a0a', $this->notes[0]['content']);
        $this->assertStringContainsString('/viewcomprobante/2035', $this->notes[0]['content']);
        $this->assertStringContainsString('[COS:phase-payment-7786]', $this->paidText);
        $this->assertSame(1, substr_count($this->paidText, '[COS:phase-payment-7786]'));
        $invoice = array_values($this->remote['invoices'])[0];
        $this->assertSame('false', $invoice['properties']['hs_invoice_billable']);
        $this->assertSame('APP-2035-7786', $invoice['properties']['hs_number']);
        $this->assertSame('51.36', $invoice['properties']['hs_amount_billed']);
        $this->assertSame('paid', $invoice['properties']['hs_invoice_status']);
        $this->assertArrayNotHasKey('hs_balance_due', $invoice['properties']);
        $payment = array_values($this->remote['commerce_payments'])[0];
        $this->assertSame('51.36', $payment['properties']['hs_initial_amount']);
        $this->assertSame('succeeded', $payment['properties']['hs_latest_status']);
        $this->assertSame('none', $payment['properties']['hs_processor_type']);
        $this->assertCount(3, $payment['associations']);
        $service->queueReceipt($receipt); $service->process($receipt->id, null, true);
        $this->assertSame(1, Compras::count()); $this->assertSame(1, Factura::count());
        $this->assertSame(['invoices' => 1, 'line_items' => 1, 'commerce_payments' => 1], $this->creates);
    }

    public function test_a_lost_response_is_recovered_by_reference_without_creating_another_remote_payment(): void
    {
        $receipt = $this->receipt(); $this->fakeHubspot();
        $this->loseResponse = 'commerce_payments';
        $service = app(CosPaymentCrmSyncService::class); $service->queueReceipt($receipt);
        $service->process($receipt->id, 'hubspot');
        $this->assertSame('retry', data_get($receipt->fresh()->metadata, 'crm_payment_sync.providers.hubspot.status'));
        $service->process($receipt->id, 'hubspot', true);
        $this->assertSame('synced', data_get($receipt->fresh()->metadata, 'crm_payment_sync.providers.hubspot.status'));
        $this->assertArrayNotHasKey('http_status', data_get($receipt->fresh()->metadata, 'crm_payment_sync.providers.hubspot'));
        $this->assertSame(1, $this->creates['commerce_payments']);
        $this->assertSame(1, Compras::count()); $this->assertSame(1, Factura::count());
    }

    public function test_an_existing_blank_non_billable_invoice_is_completed_in_place(): void
    {
        $receipt = $this->receipt(); $this->fakeHubspot();
        $comment = 'Pago COS [COS:phase-payment-7786] · Comprobante App 2035 pagado · https://app.example.test/viewcomprobante/2035';
        $this->remote['invoices']['607389666816'] = ['id' => '607389666816', 'properties' => [
            'hs_comments' => $comment, 'hs_invoice_billable' => 'false', 'hs_currency' => 'EUR',
        ]];
        $service = app(CosPaymentCrmSyncService::class); $service->queueReceipt($receipt); $service->process($receipt->id, 'hubspot');
        $state = data_get($receipt->fresh()->metadata, 'crm_payment_sync.providers.hubspot');
        $this->assertSame('synced', $state['status']);
        $this->assertSame('607389666816', $state['remote_invoice_id']);
        $this->assertSame('paid', $this->remote['invoices']['607389666816']['properties']['hs_invoice_status']);
        $this->assertSame('51.36', $this->remote['invoices']['607389666816']['properties']['hs_amount_billed']);
        $this->assertSame(0, $this->creates['invoices'] ?? 0);
        $this->assertFalse($state['calculated_totals_available']);
        $this->assertSame(1, Compras::count());
    }

    public function test_an_unknown_creation_result_never_causes_a_second_post(): void
    {
        $receipt = $this->receipt(); $this->fakeHubspot();
        $this->loseResponse = 'invoices'; $this->remoteAccepted = false;
        $service = app(CosPaymentCrmSyncService::class); $service->queueReceipt($receipt);
        $service->process($receipt->id, 'hubspot');
        $service->process($receipt->id, 'hubspot', true);
        $state = data_get($receipt->fresh()->metadata, 'crm_payment_sync.providers.hubspot');
        $this->assertSame('retry', $state['status']);
        $this->assertTrue($state['remote_invoice_create_started']);
        $this->assertSame(1, $this->creates['invoices']);
        $this->assertSame(1, Compras::count());
    }

    public function test_a_contact_identity_conflict_stops_outbound_writes(): void
    {
        $receipt = $this->receipt(); $this->contactEmail = 'another-person@example.com'; $this->fakeHubspot();
        $service = app(CosPaymentCrmSyncService::class); $service->queueReceipt($receipt);
        $service->process($receipt->id, 'hubspot');
        $this->assertSame('review', data_get($receipt->fresh()->metadata, 'crm_payment_sync.providers.hubspot.status'));
        $this->assertSame([], $this->creates);
        $this->assertSame(1, Compras::count());
    }

    public function test_cross_client_deal_links_are_not_used_for_payments(): void
    {
        $receipt = $this->receipt();
        Negocio::create(['user_id' => 999, 'teamleader_id' => 'different-project', 'hubspot_id' => '6867596984']);
        $service = app(CosPaymentCrmSyncService::class); $service->queueReceipt($receipt);
        $service->process($receipt->id, 'hubspot');
        $this->assertSame('review', data_get($receipt->fresh()->metadata, 'crm_payment_sync.providers.hubspot.status'));
        Http::assertNothingSent();
    }

    public function test_a_confirmed_receipt_on_a_translation_deal_requires_review_before_either_crm_is_written(): void
    {
        Schema::table('negocios', fn (Blueprint $table) => $table->string('servicio_solicitado2')->nullable());
        Negocio::whereKey(2377)->update(['servicio_solicitado2' => 'Traducción al Portugués']);
        $receipt = $this->receipt();
        $service = app(CosPaymentCrmSyncService::class);
        $service->queueReceipt($receipt);
        $service->process($receipt->id);
        $states = data_get($receipt->fresh()->metadata, 'crm_payment_sync.providers');
        $this->assertSame('review', $states['hubspot']['status']);
        $this->assertSame('review', $states['teamleader']['status']);
        $this->assertSame(1, Compras::count());
        $this->assertSame(1, Factura::count());
        $this->assertSame(51.36, $receipt->fresh()->monto);
        Http::assertNothingSent();
    }

    public function test_a_lost_teamleader_note_response_is_recovered_without_repeating_the_abono(): void
    {
        $receipt = $this->receipt(); $this->fakeTeamleader(); $this->loseNoteResponse = true;
        $service = app(CosPaymentCrmSyncService::class); $service->queueReceipt($receipt);
        $service->process($receipt->id, 'teamleader');
        $this->assertSame('retry', data_get($receipt->fresh()->metadata, 'crm_payment_sync.providers.teamleader.status'));
        $service->process($receipt->id, 'teamleader', true);
        $this->assertSame('synced', data_get($receipt->fresh()->metadata, 'crm_payment_sync.providers.teamleader.status'));
        $this->assertCount(1, $this->notes); $this->assertSame(1, $this->projectWrites);
        $this->assertSame(1, Compras::count());
    }

    public function test_teamleader_http_failures_keep_the_exact_operation_and_status(): void
    {
        $receipt = $this->receipt(); config(['services.teamleader.min_request_gap_ms' => 0]);
        $this->partialMock(TeamleaderService::class, fn ($m) => $m->shouldReceive('getAccessToken')->andReturn('test-only'));
        Http::fake(['https://api.focus.teamleader.eu/projects.info' => Http::response(['errors' => [['title' => 'do-not-store-response-body']]], 403)]);
        $service = app(CosPaymentCrmSyncService::class); $service->queueReceipt($receipt); $service->process($receipt->id, 'teamleader');
        $state = data_get($receipt->fresh()->metadata, 'crm_payment_sync.providers.teamleader');
        $this->assertSame('retry', $state['status']);
        $this->assertSame('projects.info', $state['last_operation']);
        $this->assertSame(403, $state['http_status']);
        $this->assertStringNotContainsString('do-not-store-response-body', json_encode($state));
        $this->assertSame(1, Compras::count());
    }

    public function test_a_provider_permission_failure_does_not_repeat_or_block_the_other_provider(): void
    {
        $receipt = $this->receipt(); $this->fakeHubspot(); $this->fakeTeamleader(); $this->hubspotRejection = 403;
        $service = app(CosPaymentCrmSyncService::class); $service->queueReceipt($receipt); $service->process($receipt->id);
        $state = data_get($receipt->fresh()->metadata, 'crm_payment_sync.providers');
        $this->assertSame('retry', $state['hubspot']['status']);
        $this->assertStringContainsString('HTTP 403', $state['hubspot']['last_error']);
        $this->assertSame('GET /crm/v3/objects/contacts/19063', $state['hubspot']['last_operation']);
        $this->assertSame('MISSING_SCOPES', $state['hubspot']['http_category']);
        $this->assertSame(['crm.objects.contacts.read'], $state['hubspot']['required_scopes']);
        $this->assertStringNotContainsString('do-not-store-response-body', json_encode($state));
        $this->assertSame('synced', $state['teamleader']['status']);
        $this->hubspotRejection = null;
        $service->process($receipt->id, null, true);
        $this->assertSame('synced', data_get($receipt->fresh()->metadata, 'crm_payment_sync.providers.hubspot.status'));
        $this->assertSame(1, $this->projectWrites); $this->assertCount(1, $this->notes);
    }

    public function test_even_a_forced_retry_respects_the_active_worker_lease(): void
    {
        $receipt = $this->receipt(); $service = app(CosPaymentCrmSyncService::class); $service->queueReceipt($receipt);
        $metadata = $receipt->fresh()->metadata;
        data_set($metadata, 'crm_payment_sync.providers.hubspot.status', 'processing');
        data_set($metadata, 'crm_payment_sync.providers.hubspot.lease_until', now()->addMinutes(5)->toIso8601String());
        $receipt->forceFill(['metadata' => $metadata])->save();
        $service->process($receipt->id, 'hubspot', true);
        $this->assertSame(0, data_get($receipt->fresh()->metadata, 'crm_payment_sync.providers.hubspot.attempts'));
        Http::assertNothingSent();
    }

    public function test_a_reference_with_a_different_remote_amount_requires_review_instead_of_another_abono(): void
    {
        $receipt = $this->receipt(); $this->fakeHubspot(); $this->fakeTeamleader();
        $this->paidText = 'Abono 61.36 EUR 2026-11-14 [COS:phase-payment-7786]';
        $this->project['custom_fields'][0]['value'] = $this->paidText;
        $service = app(CosPaymentCrmSyncService::class); $service->queueReceipt($receipt); $service->process($receipt->id);
        $state = data_get($receipt->fresh()->metadata, 'crm_payment_sync.providers');
        $this->assertSame('review', $state['hubspot']['status']);
        $this->assertSame('review', $state['teamleader']['status']);
        $this->assertSame(0, $this->projectWrites); $this->assertCount(0, $this->notes);
        $this->assertSame('Abono 61.36 EUR 2026-11-14 [COS:phase-payment-7786]', $this->paidText);
        $this->assertSame(1, Compras::count());
    }

    public function test_reading_the_teamleader_echo_does_not_import_the_portal_installment_again(): void
    {
        $receipt = $this->receipt();
        $project = new \App\Models\TlProject(['id' => data_get($receipt->metadata, 'teamleader_project_id'),
            'title' => 'Carta de Naturaleza', 'custom_fields' => [
                ['id' => 'a42ed217-b570-0973-9052-fab97214c229', 'value' => '3500 EUR'],
                ['id' => '4339375f-ed77-02d9-a157-7da9f9e4bfac',
                    'value' => 'Abono 3448.64 EUR 2026-01-01 + Abono 51.36 EUR 2026-11-14 [COS:phase-payment-7786]'],
            ]]);
        $service = app(TeamleaderPhasePaymentService::class);
        for ($i = 0; $i < 2; $i++) {
            $analysis = app(TeamleaderProjectPaymentAnalyzer::class)->analyzeProjects(collect([$project]));
            $service->sync(User::findOrFail(290), $analysis, Negocio::all());
            $this->assertSame(3500.0, $analysis['projects'][0]['phases'][98]['effective_paid_amount']);
            $this->assertSame(0.0, $analysis['projects'][0]['phases'][98]['balance_amount']);
            $this->assertSame(1, Compras::where('source', TeamleaderPhasePaymentService::INSTALLMENT_SOURCE)->count());
            $this->assertSame(0, Compras::where('source', TeamleaderPhasePaymentService::HISTORY_SOURCE)->where('monto', 51.36)->count());
        }
        $this->assertSame(1, Factura::count());
        Http::assertNothingSent();
    }

    public function test_read_only_command_does_not_queue_or_call_apis(): void
    {
        $receipt = $this->receipt(); $original = $receipt->fresh()->getRawOriginal();
        $this->artisan('cos:sync-payment-receipts', ['--receipt' => ['7786']])->assertSuccessful();
        $this->assertSame($original, $receipt->fresh()->getRawOriginal());
        Http::assertNothingSent();
    }

    public function test_pending_command_does_not_backfill_historical_receipts(): void
    {
        $this->receipt(); $this->fakeHubspot();
        $this->artisan('cos:sync-payment-receipts', ['--pending' => true, '--write' => true, '--provider' => 'hubspot'])->assertSuccessful();
        Http::assertNothingSent();
        app(CosPaymentCrmSyncService::class)->queueReceipt(Compras::findOrFail(7786));
        $this->artisan('cos:sync-payment-receipts', ['--pending' => true, '--write' => true, '--provider' => 'hubspot'])->assertSuccessful();
        $this->assertSame(1, $this->creates['commerce_payments']);
    }

    public function test_a_crm_echo_marker_is_neither_an_amount_nor_an_extra_historical_payment(): void
    {
        $parsed = app(TeamleaderProjectPaymentAnalyzer::class)->parseMoneyText(
            'Abono 3448.64 EUR 2026-01-01 + Abono 51.36 EUR 2026-11-14 [COS:phase-payment-7786]');
        $this->assertSame(3500.0, $parsed['total']);
        $this->assertCount(2, $parsed['dated_entries']);
        $this->assertSame('phase-payment-7786', $parsed['dated_entries'][1]['cos_reference']);
    }

    private function receipt(): Compras
    {
        return Compras::forceCreate(['id' => 7786, 'id_user' => 290,
            'source' => TeamleaderPhasePaymentService::INSTALLMENT_SOURCE, 'pagado' => 1,
            'monto' => 51.36, 'phasenum' => 98, 'hash_factura' => 'existing-document', 'paid_at' => '2026-11-14',
            'metadata' => ['teamleader_project_id' => 'e93270e9-4065-0f1d-bc62-63e3431a1a0a', 'phase' => 98,
                'paid_amount_at_sync' => 3448.64, 'portal_record_kind' => 'installment']]);
    }

    private function fakeHubspot(): void
    {
        Http::fake(function ($r) {
            if ($this->hubspotRejection) return Http::response(['message' => 'do-not-store-response-body',
                'category' => 'MISSING_SCOPES', 'errors' => [['context' => ['requiredScopes' => ['crm.objects.contacts.read']]]]], $this->hubspotRejection);
            $path = parse_url($r->url(), PHP_URL_PATH);
            if ($path === '/crm/v3/objects/contacts/19063') return Http::response(['id' => '19063',
                'properties' => ['email' => $this->contactEmail, 'numero_de_pasaporte' => 'P-290']]);
            if ($path === '/crm/v4/objects/deals/6867596984/associations/contacts') return Http::response(['results' => [['toObjectId' => 19063]]]);
            if (preg_match('~/crm/v4/objects/commerce_payments/([^/]+)/associations/(contacts|invoices)$~', $path, $a)) {
                $type = $a[2] === 'contacts' ? 387 : 542;
                $records = array_filter($this->remote['commerce_payments'][$a[1]]['associations'], fn ($record) => $record['types'][0]['associationTypeId'] === $type);
                return Http::response(['results' => array_values(array_map(fn ($record) => ['toObjectId' => $record['to']['id']], $records))]);
            }
            if ($path === '/crm/v3/objects/deals/6867596984') {
                if ($r->method() === 'PATCH') $this->paidText = $r['properties']['carta_nat_pagado'];
                return Http::response(['id' => '6867596984', 'properties' => ['carta_nat_pagado' => $this->paidText]]);
            }
            if ($r->method() === 'PUT' && str_contains($path, '/associations/default/')) return Http::response([], 200);
            if (preg_match('~/crm/v3/objects/(invoices|line_items|commerce_payments)(?:/([^/]+))?$~', $path, $m)) {
                $type = $m[1]; $id = $m[2] ?? null;
                if ($id === 'search') {
                    $filter = $r['filterGroups'][0]['filters'][0];
                    $matches = array_values(array_filter($this->remote[$type], fn ($v) => ($v['properties'][$filter['propertyName']] ?? null) === $filter['value']));
                    return Http::response(['total' => count($matches), 'results' => $matches]);
                }
                if ($id) {
                    $this->assertArrayHasKey($id, $this->remote[$type]);
                    if ($r->method() === 'PATCH') $this->remote[$type][$id]['properties'] = array_merge($this->remote[$type][$id]['properties'], $r['properties']);
                    return Http::response($this->remote[$type][$id]);
                }
                $this->creates[$type] = ($this->creates[$type] ?? 0) + 1;
                $id = (string) (100000 + count($this->remote[$type]));
                $record = ['id' => $id, 'properties' => $r['properties'], 'associations' => $r['associations']];
                if ($type === 'invoices') $record['properties'] += ['hs_invoice_status' => 'draft'];
                if ($this->remoteAccepted) $this->remote[$type][$id] = $record;
                // Real non-billable invoices do not calculate totals or acquire
                // a paid status just because a payment was associated.
                if ($this->loseResponse === $type) { $this->loseResponse = null; throw new ConnectionException('Simulated lost response'); }
                return Http::response($record, 201);
            }
            $this->fail('Unexpected HTTP call: '.$r->method().' '.$path);
        });
    }

    private function fakeTeamleader(): void
    {
        $this->project = ['id' => 'e93270e9-4065-0f1d-bc62-63e3431a1a0a', 'title' => 'Proyecto',
            'budget' => ['provided' => ['amount' => 3500, 'currency' => 'EUR'], 'spent' => ['total' => ['amount' => 100, 'currency' => 'EUR']]],
            'customer' => ['type' => 'contact', 'id' => '4a853f3d-0c10-0b3d-9c7d-20d3a2be67ad'],
            'custom_fields' => [['id' => '4339375f-ed77-02d9-a157-7da9f9e4bfac', 'value' => '3448.64 EUR']]];
        $this->mock(TeamleaderService::class, function ($m): void {
            $m->shouldReceive('getProjectDetails')->andReturnUsing(fn () => $this->project);
            $m->shouldReceive('webhookObject')->andReturnUsing(fn ($type) => $type === 'project' ? $this->project
                : ['id' => '4a853f3d-0c10-0b3d-9c7d-20d3a2be67ad', 'emails' => [['email' => 'client@example.com']]]);
            $m->shouldReceive('updatePaymentProject')->andReturnUsing(function ($id, $data): void {
                $this->assertSame(['amount' => 3500, 'currency' => 'EUR'], $data['budget']);
                $this->projectWrites++; $this->project = array_merge($this->project, $data);
            });
            $m->shouldReceive('paymentReceiptNotes')->andReturnUsing(fn () => $this->notes);
            $m->shouldReceive('createPaymentReceiptNote')->andReturnUsing(function ($id, $content, $type = 'project') {
                $this->notes[] = ['id' => 'tl-note', 'content' => $content, 'subject_type' => $type, 'subject_id' => $id];
                if ($this->loseNoteResponse) { $this->loseNoteResponse = false; throw new ConnectionException('Simulated lost note response'); }
                return 'tl-note';
            });
        });
    }
}
