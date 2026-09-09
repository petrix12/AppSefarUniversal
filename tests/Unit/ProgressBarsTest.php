<?php

namespace Tests\Unit;

use Tests\TestCase;

class ProgressBarsTest extends TestCase
{
    public function test_downloaded_certificate_completes_every_genealogical_step_even_if_the_last_step_number_is_out_of_sync(): void
    {
        $html = view('crud.users.partials.progress-bars', [
            'index' => 0,
            'proceso' => [
                'servicio' => 'Portuguesa Sefardi',
                'certificadoDescargado' => 1,
                'currentStepGen' => 17,
                'currentStepJur' => -1,
                'progressPercentageGen' => 94,
            ],
            'cos' => [
                'Portuguesa Sefardi' => [
                    'genealogico' => [
                        ['paso' => 1, 'nombre_corto' => 'Registro', 'promesa' => ''],
                        ['paso' => 19, 'nombre_corto' => 'Certificado Aprobado', 'promesa' => ''],
                    ],
                    'juridico' => [],
                ],
            ],
        ])->render();

        $this->assertStringContainsString('style="width: 100%;"', $html);
        $this->assertMatchesRegularExpression(
            '/class="progress-step active"\s+data-step="19"/',
            $html
        );
    }

    public function test_spanish_certificate_does_not_force_the_portuguese_completion_behavior(): void
    {
        $html = view('crud.users.partials.progress-bars', [
            'index' => 0,
            'proceso' => [
                'servicio' => 'Española Sefardi',
                'certificadoDescargado' => 1,
                'currentStepGen' => 17,
                'currentStepJur' => -1,
                'progressPercentageGen' => 94,
            ],
            'cos' => [
                'Española Sefardi' => [
                    'genealogico' => [
                        ['paso' => 1, 'nombre_corto' => 'Registro', 'promesa' => ''],
                        ['paso' => 19, 'nombre_corto' => 'Certificado Aprobado', 'promesa' => ''],
                    ],
                    'juridico' => [],
                ],
            ],
        ])->render();

        $this->assertStringContainsString('style="width: 94%;"', $html);
        $this->assertSame(
            0,
            preg_match('/class="progress-step active"\s+data-step="19"/', $html)
        );
    }
}
