<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\BancaOnlineFlow;
use Illuminate\Http\Request;
use Tests\TestCase;

class BancaOnlineFlowTest extends TestCase
{
    private BancaOnlineFlow $flow;

    protected function setUp(): void
    {
        parent::setUp();

        $this->flow = new BancaOnlineFlow();
    }

    public function test_it_normalizes_case_status_aliases(): void
    {
        $this->assertSame('not_started', $this->flow->normalizeCaseStatus('Todavia no he iniciado'));
        $this->assertSame('requirement_received', $this->flow->normalizeCaseStatus('requerimiento'));
        $this->assertSame('represented_active', $this->flow->normalizeCaseStatus('representado'));
        $this->assertNull($this->flow->normalizeCaseStatus('sin sentido'));
    }

    public function test_it_resolves_entry_points(): void
    {
        $this->assertSame('external', $this->flow->entryPoint(Request::create('/')));
        $this->assertSame('internal', $this->flow->entryPoint(Request::create('/'), new User(['id' => 44])));
        $this->assertSame('cos', $this->flow->entryPoint(Request::create('/', 'GET', ['from' => 'cos'])));
        $this->assertSame('admin_quote', $this->flow->entryPoint(Request::create('/', 'GET', ['quote_id' => 'Q-1'])));
    }

    public function test_it_recommends_plan_from_case_status(): void
    {
        $plans = [
            'solicitud-estrategica' => ['title' => 'Solicitud estrategica'],
            'administrativo' => ['title' => 'Administrativo'],
            'judicial' => ['title' => 'Judicial'],
        ];

        $this->assertSame('administrativo', $this->flow->recommendation('requirement_received', $plans)['plan_slug']);
        $this->assertSame('judicial', $this->flow->recommendation('denied', $plans)['plan_slug']);
        $this->assertSame('solicitud-estrategica', $this->flow->recommendation('other_process', $plans)['plan_slug']);
    }

    public function test_it_falls_back_to_available_plan(): void
    {
        $recommendation = $this->flow->recommendation('denied', [
            'solicitud-estrategica' => ['title' => 'Solicitud estrategica'],
        ]);

        $this->assertSame('solicitud-estrategica', $recommendation['plan_slug']);
        $this->assertTrue($recommendation['matched']);
    }

    public function test_rationale_uses_recommended_reason_only_for_recommended_plan(): void
    {
        $plans = [
            'administrativo' => ['public_title' => 'Plan Administrativo'],
            'judicial' => ['public_title' => 'Plan Judicial'],
        ];
        $recommendation = $this->flow->recommendation('requirement_received', $plans);

        $recommended = $this->flow->rationale('requirement_received', $plans['administrativo'], $recommendation, 'administrativo');
        $alternative = $this->flow->rationale('requirement_received', $plans['judicial'], $recommendation, 'judicial');

        $this->assertStringContainsString('requerimiento', $recommended['reason']);
        $this->assertStringContainsString('puede seleccionarse', $alternative['reason']);
        $this->assertSame('Plan Judicial', $alternative['title']);
    }
}
