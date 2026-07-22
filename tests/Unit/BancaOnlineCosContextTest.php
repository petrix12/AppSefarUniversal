<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\BancaOnlineCosContext;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BancaOnlineCosContextTest extends TestCase
{
    public function test_it_returns_sanitized_public_cos_context(): void
    {
        $user = new User([
            'cosready' => 1,
            'arraycos_expire' => Carbon::now()->addDay(),
            'arraycos' => [[
                'servicio' => 'Espanola Sefardi',
                'currentStepName' => '<b>Revision documental</b>',
                'currentStepGen' => 4,
                'currentStepJur' => 2,
                'progressPercentageGen' => 45.5,
                'progressPercentageJur' => 35,
                'description' => 'Regla interna',
                'teamleader_id' => 'TL-1',
                'currentStepDetails' => [
                    'promesa' => 'Detalle visible',
                    'ctas' => [['label' => 'Interno']],
                ],
            ]],
        ]);

        $context = (new BancaOnlineCosContext())->forUser($user);

        $this->assertTrue($context['visible']);
        $this->assertTrue($context['fresh']);
        $this->assertSame('Revision documental', $context['entries'][0]['current_step']);
        $this->assertSame(46, $context['entries'][0]['progress_genealogic']);
        $this->assertArrayNotHasKey('teamleader_id', $context['entries'][0]);
        $this->assertArrayNotHasKey('description', $context['entries'][0]);
    }
}
