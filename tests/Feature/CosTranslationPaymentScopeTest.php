<?php

namespace Tests\Feature;

use App\Models\Compras;
use App\Models\Negocio;
use App\Models\User;
use App\Services\HubspotService;
use App\Services\AutomaticTeamleaderDealAssociationService;
use App\Services\UnificationAiSuggestionService;
use App\Services\TeamleaderService;
use App\Services\TeamleaderDealEnrichmentService;
use App\Services\TeamleaderPhasePaymentService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CosTranslationPaymentScopeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Mail::fake();
        Schema::create('negocios', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            foreach (['hubspot_id', 'teamleader_id', 'servicio_solicitado2', 'servicio_solicitado', 'fase_1_preestab', 'fase_1_pagado__teamleader_'] as $field) {
                $table->string($field)->nullable();
            }
            $table->json('teamleader_comparisons')->nullable();
            $table->timestamps();
        });
        Schema::create('compras', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_user');
            $table->unsignedBigInteger('deal_id')->nullable();
            $table->string('source')->nullable();
            $table->integer('pagado')->default(0);
            $table->integer('phasenum')->nullable();
            $table->decimal('monto', 12, 2);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('compras');
        Schema::dropIfExists('negocios');
        parent::tearDown();
    }

    public function test_enrichment_never_fills_translation_with_the_nationality_project_amounts(): void
    {
        $deal = $this->deal('Traducción al Portugués');
        $this->mock(HubspotService::class)->shouldNotReceive('updateDeals');
        app(TeamleaderDealEnrichmentService::class)->sync($deal, $this->project(), [
            'id' => '123', 'properties' => ['servicio_solicitado2' => 'Traducción al Portugués'],
        ]);
        $this->assertNull($deal->fresh()->fase_1_preestab);
        $this->assertNull($deal->fresh()->fase_1_pagado__teamleader_);
        Http::assertNothingSent();
    }

    public function test_nationality_enrichment_still_works_despite_a_stale_translation_it_field(): void
    {
        $deal = $this->deal('Portuguesa Sefardi');
        $this->mock(HubspotService::class)->shouldReceive('updateDeals')->once()->with('123', [
            'fase_1_pagado__teamleader_' => '1000 EUR', 'fase_1_preestab' => '1050 EUR',
        ]);
        app(TeamleaderDealEnrichmentService::class)->sync($deal, $this->project(), [
            'id' => '123', 'properties' => ['servicio_solicitado2' => 'Portuguesa Sefardi', 'servicio_solicitado' => 'Traducción al Portugués'],
        ]);
        $this->assertSame('1050 EUR', $deal->fresh()->fase_1_preestab);
        Http::assertNothingSent();
    }

    public function test_old_translation_phase_url_is_rejected_even_with_a_nationality_project_title(): void
    {
        $deal = $this->deal('Traducción al Portugués');
        $purchase = Compras::create([
            'id_user' => 10, 'deal_id' => $deal->id, 'source' => TeamleaderPhasePaymentService::PURCHASE_SOURCE,
            'monto' => 50, 'phasenum' => 1, 'metadata' => [
                'phase' => 1, 'teamleader_project_id' => 'project-1', 'teamleader_project_title' => 'Cliente - Nacionalidad Portuguesa',
            ],
        ]);
        $original = $purchase->fresh()->getAttributes();
        $this->assertFalse(app(TeamleaderPhasePaymentService::class)->canPayPortalPurchase($purchase));
        $this->assertSame($original, $purchase->fresh()->getAttributes());
        Http::assertNothingSent();
    }

    public function test_translation_link_cannot_create_balances_or_import_history_from_an_unclassified_project(): void
    {
        $deal = $this->deal('Traducción al Portugués');
        $analysis = ['projects' => [[
            'project_id' => 'project-1', 'project_title' => 'Cliente', 'phases' => [1 => [
                'phase' => 1, 'preestab_raw' => '1050 EUR', 'paid_raw' => '1000 EUR',
                'effective_preestab_amount' => 1050.0, 'effective_paid_amount' => 1000.0, 'balance_amount' => 50.0,
            ]],
        ]]];
        app(TeamleaderPhasePaymentService::class)->sync(new User(['id' => 10]), $analysis, collect([$deal]));
        $this->assertSame([], $analysis['projects'][0]['phases']);
        $this->assertSame(0, Compras::count());
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    private function deal(string $service): Negocio
    {
        return Negocio::create([
            'user_id' => 10, 'hubspot_id' => '123', 'teamleader_id' => 'project-1',
            'servicio_solicitado2' => $service, 'servicio_solicitado' => 'Traducción al Portugués',
        ]);
    }

    public function test_an_ai_suggestion_cannot_link_translation_to_nationality_using_the_copied_code_and_amounts(): void
    {
        config(['cache.default' => 'array']);
        Cache::flush();
        $deal = Negocio::create([
            'user_id' => 10, 'hubspot_id' => '123', 'servicio_solicitado2' => 'Traducción al Portugués',
        ]);
        $this->mock(TeamleaderService::class)->shouldNotReceive('getProjectsWithDetailsByCustomerId');
        $this->mock(HubspotService::class)->shouldNotReceive('updateDeals');
        $ai = $this->mock(UnificationAiSuggestionService::class);
        $ai->shouldReceive('available')->once()->andReturn(true);
        $ai->shouldReceive('suggestDealAssociations')->once()->andReturn(['suggestions' => [[
            'id' => 'project-1', 'confidence' => 100, 'reason' => 'Mismo cliente y código',
        ]]]);
        $project = $this->project();
        $project['custom_fields'][] = [
            'definition' => ['id' => 'a42f63f5-d527-0544-ab50-9c03857707f2'], 'value' => 'SAME-CODE',
        ];
        $result = app(AutomaticTeamleaderDealAssociationService::class)->associate(
            new User(['id' => 10, 'tl_id' => 'contact-1']), [[
                'id' => '123', 'properties' => [
                    'servicio_solicitado2' => 'Traducción al Portugués', 'codigo_de_proceso' => 'SAME-CODE',
                    'fase_1_preestab' => '1050 EUR', 'fase_1_pagado__teamleader_' => '1000 EUR',
                ],
            ]], [$project],
        );
        $this->assertSame(0, $result['linked']);
        $this->assertNull($deal->fresh()->teamleader_id);
        Http::assertNothingSent();
    }

    private function project(): array
    {
        return ['id' => 'project-1', 'title' => 'Cliente - Nacionalidad Portuguesa', 'custom_fields' => [
            ['definition' => ['id' => '73173887-a0e8-0f4f-bb55-b61f33d3c6e9'], 'value' => '1050 EUR'],
            ['definition' => ['id' => 'a1b50c58-8175-0d13-9856-f661e783dc08'], 'value' => '1000 EUR'],
        ]];
    }
}
