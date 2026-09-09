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

        $this->assertStringContainsString('style="--progress: 1;"', $html);
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

        $this->assertStringContainsString('style="--progress: 0;"', $html);
        $this->assertSame(
            0,
            preg_match('/class="progress-step active"\s+data-step="19"/', $html)
        );
    }

    public function test_progress_lines_end_at_the_last_active_visual_step(): void
    {
        $html = view('crud.users.partials.progress-bars', [
            'index' => 0,
            'proceso' => [
                'servicio' => 'Servicio de prueba',
                'currentStepGen' => 1,
                'currentStepJur' => 1,
            ],
            'cos' => [
                'Servicio de prueba' => [
                    'genealogico' => [
                        ['paso' => 1, 'nombre_corto' => 'Gen 1', 'promesa' => ''],
                        ['paso' => 2, 'nombre_corto' => 'Gen 2', 'promesa' => ''],
                        ['paso' => 3, 'nombre_corto' => 'Gen 3', 'promesa' => ''],
                        ['paso' => 4, 'nombre_corto' => 'Gen 4', 'promesa' => ''],
                    ],
                    'juridico' => [
                        ['paso' => 1, 'nombre_corto' => 'Jur 1', 'promesa' => ''],
                        ['paso' => 2, 'nombre_corto' => 'Jur 2', 'promesa' => ''],
                        ['paso' => 3, 'nombre_corto' => 'Jur 3', 'promesa' => ''],
                        ['paso' => 4, 'nombre_corto' => 'Jur 4', 'promesa' => ''],
                    ],
                ],
            ],
        ])->render();

        // currentStep = 1 activa los dos primeros hitos: índice 1 de 3.
        $this->assertSame(2, substr_count($html, 'style="--progress: 0.33333333333333;"'));
    }
}
