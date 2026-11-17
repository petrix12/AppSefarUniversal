<?php

namespace Tests\Feature;

use App\Models\Compras;
use App\Models\TlProject;
use App\Models\User;
use App\Services\CosPaymentReconciliationService;
use App\Services\TeamleaderPhasePaymentService;
use App\Services\TeamleaderProjectPaymentAnalyzer;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CosPortalInstallmentSummaryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array']);
        Cache::flush();
        Mail::fake();
        Http::preventStrayRequests();
        Schema::create('compras', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_user');
            $table->string('source')->nullable();
            $table->string('servicio_hs_id')->nullable();
            $table->text('descripcion')->nullable();
            $table->integer('pagado')->default(0);
            $table->decimal('monto', 12, 2);
            $table->unsignedBigInteger('deal_id')->nullable();
            $table->integer('phasenum')->nullable();
            $table->string('hash_factura')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
        Schema::create('negocios', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('teamleader_id')->nullable();
        });
    }

    #[DataProvider('receiptScopes')]
    public function test_the_paid_installment_is_counted_only_for_its_client_project_and_phase(
        int $receiptUser, string $receiptProject, int $receiptPhase, bool $matches,
    ): void {
        $user = new User(['id' => 290, 'servicio' => 'test-service']);
        $receipt = Compras::create([
            'id_user' => $receiptUser, 'source' => TeamleaderPhasePaymentService::INSTALLMENT_SOURCE,
            'pagado' => 1, 'monto' => 51.36, 'phasenum' => $receiptPhase,
            'hash_factura' => 'confirmed-test-receipt', 'paid_at' => '2026-11-14',
            'metadata' => [
                'portal_record_kind' => 'installment', 'teamleader_project_id' => $receiptProject,
                'phase' => $receiptPhase, 'paid_amount_at_sync' => 3448.64,
                'foreign_payment_conversions_at_sync' => $this->knownConversions(),
            ],
        ]);
        $originalReceipt = $receipt->fresh()->getRawOriginal();
        Http::fake(function ($request) use ($matches) {
            $this->assertFalse($matches, 'A confirmed conversion must not need another ECB request.');
            $first = str_contains($request->url(), '2022-10-28');
            return Http::response("TIME_PERIOD,OBS_VALUE\n".($first ? "2022-10-28,0.9951\n" : "2023-03-23,1.0879\n"));
        });
        $service = app(TeamleaderPhasePaymentService::class);
        $analysis = $this->analysis();
        $service->sync($user, $analysis, collect());
        $phase = $analysis['projects'][0]['phases'][98];

        $this->assertSame($matches ? 3500.0 : 3448.64, $phase['effective_paid_amount']);
        $this->assertSame($matches ? 0.0 : 51.36, $phase['balance_amount']);
        $this->assertSame($matches ? 'paid' : 'partial', $phase['status']);
        $this->assertSame($matches ? 51.36 : 0.0, $phase['portal_paid_amount']);
        $this->assertSame($originalReceipt, $receipt->fresh()->getRawOriginal());
        $count = Compras::count();
        $again = $this->analysis();
        $service->sync($user, $again, collect());
        $this->assertSame($phase['effective_paid_amount'], $again['projects'][0]['phases'][98]['effective_paid_amount']);
        $this->assertSame($count, Compras::count());
        $this->assertSame($originalReceipt, $receipt->fresh()->getRawOriginal());

        if ($matches) {
            $row = $service->paymentSummaryRow($phase + ['source' => 'Teamleader', 'project_id' => 'test-project']);
            $result = app(CosPaymentReconciliationService::class)->reconcile(
                collect(), collect(), Compras::where('pagado', 1)->get(), collect([$row])
            );
            $this->assertSame(3500.0, $result['summary']->firstWhere('currency', 'EUR')['paid']);
            $this->assertSame(0.0, $result['summary']->firstWhere('currency', 'EUR')['debt']);
            Http::assertNothingSent();
        }
        Mail::assertNothingSent();
    }

    public static function receiptScopes(): array
    {
        return [
            'same phase, no deal_id required' => [290, 'test-project', 98, true],
            'different client' => [291, 'test-project', 98, false],
            'different project' => [290, 'other-project', 98, false],
            'different phase' => [290, 'test-project', 99, false],
        ];
    }

    public function test_a_confirmed_complete_checkout_does_not_become_debt_when_a_rate_is_unavailable(): void
    {
        $user = new User(['id' => 290]);
        Compras::create([
            'id_user' => 290, 'source' => TeamleaderPhasePaymentService::INSTALLMENT_SOURCE,
            'pagado' => 1, 'monto' => 51.36, 'phasenum' => 98, 'paid_at' => '2026-11-14',
            'metadata' => ['teamleader_project_id' => 'test-project', 'phase' => 98, 'paid_amount_at_sync' => 3448.64],
        ]);
        Http::fake(['*' => Http::response('', 503)]);
        $analysis = $this->analysis();
        $service = app(TeamleaderPhasePaymentService::class);
        $service->sync($user, $analysis, collect());
        $phase = $analysis['projects'][0]['phases'][98];

        $this->assertFalse($phase['payment_conversion_complete']);
        $this->assertTrue($phase['payment_total_complete']);
        $this->assertSame(3500.0, $phase['effective_paid_amount']);
        $this->assertSame(0.0, $phase['balance_amount']);
        $this->assertSame('paid', $service->paymentSummaryRow($phase)['display_status']);
        Mail::assertNothingSent();
    }

    private function analysis(): array
    {
        return (new TeamleaderProjectPaymentAnalyzer())->analyzeProjects(collect([new TlProject([
            'id' => 'test-project', 'title' => 'Caso de prueba',
            'custom_fields' => [
                ['definition' => ['id' => 'a42ed217-b570-0973-9052-fab97214c229'], 'value' => '3500€'],
                ['definition' => ['id' => '4339375f-ed77-02d9-a157-7da9f9e4bfac'], 'value' => 'Abono 1736.11$ 28/10/2022 + 1853.76$ 23/03/2023'],
            ],
        ])]));
    }

    private function knownConversions(): array
    {
        return [
            ['amount' => 1736.11, 'currency' => 'USD', 'payment_date' => '2022-10-28', 'reference_rate' => .9951, 'eur_amount' => 1744.66, 'source' => 'ECB'],
            ['amount' => 1853.76, 'currency' => 'USD', 'payment_date' => '2023-03-23', 'reference_rate' => 1.0879, 'eur_amount' => 1703.98, 'source' => 'ECB'],
        ];
    }
}
