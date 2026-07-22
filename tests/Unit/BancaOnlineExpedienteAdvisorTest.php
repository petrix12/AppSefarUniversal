<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\BancaOnlineCatalog;
use App\Services\BancaOnlineCosContext;
use App\Services\BancaOnlineExpedienteAdvisor;
use App\Services\BancaOnlineFlow;
use App\Services\ClientStageResolver;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BancaOnlineExpedienteAdvisorTest extends TestCase
{
    private BancaOnlineExpedienteAdvisor $advisor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->advisor = new BancaOnlineExpedienteAdvisor(
            new BancaOnlineCatalog(),
            new BancaOnlineFlow(),
            new ClientStageResolver(),
            new BancaOnlineCosContext()
        );
    }

    public function test_it_uses_public_cos_status_to_detect_recommendation_context(): void
    {
        $user = new User([
            'pay' => 2,
            'contrato' => 1,
            'cosready' => 1,
            'arraycos_expire' => Carbon::now()->addDay(),
            'arraycos' => [[
                'servicio' => 'Espanola Sefardi',
                'currentStepName' => 'Revision del expediente',
                'progressPercentageGen' => 55,
                'progressPercentageJur' => 20,
            ]],
        ]);

        $context = $this->advisor->forUser($user, 'espana');

        $this->assertTrue($context['visible']);
        $this->assertSame('represented', $context['profile']);
        $this->assertSame('under_review', $context['detected_case_status']);
        $this->assertSame('under_review', $context['recommended_case_status']);
        $this->assertSame('cos', $context['next_action']['type']);
    }

    public function test_public_lookup_context_does_not_expose_private_details_without_authentication(): void
    {
        $user = new User([
            'pay' => 2,
            'contrato' => 1,
            'cosready' => 1,
            'arraycos' => [[
                'servicio' => 'Espanola Sefardi',
                'currentStepName' => 'Revision documental',
            ]],
        ]);

        $context = $this->advisor->forUser($user, 'espana');
        $public = $this->advisor->publicLookupContext($user, $context);

        $this->assertFalse($public['visible']);
        $this->assertTrue($public['has_private_context']);
        $this->assertArrayNotHasKey('documents', $public);
        $this->assertStringContainsString('seguridad', $public['summary']);
    }

    public function test_candidate_next_action_points_to_pending_registration_phase(): void
    {
        $user = new User([
            'pay' => 1,
            'contrato' => 0,
            'cosready' => 0,
        ]);

        $context = $this->advisor->forUser($user, 'espana');

        $this->assertSame('candidate', $context['profile']);
        $this->assertSame('getinfo', $context['next_action']['type']);
        $this->assertSame('Completar informacion del expediente', $context['next_action']['title']);
    }
}
