<?php

namespace Tests\Unit;

use App\Services\CosPresentation;
use PHPUnit\Framework\TestCase;

class CosPresentationTest extends TestCase
{
    public function test_portuguese_cached_status_and_nested_buttons_are_renamed_only_for_portuguese(): void
    {
        $status = [
            'servicio' => 'Portuguesa Sefardi',
            'currentStepName' => 'Recurso de Urgencia',
            'warning' => '<b>¡Solicita tu Recurso de Urgencia!</b>',
            'currentStepDetails' => ['ctas' => [['text' => 'Solicita el Recurso de Urgencia', 'url' => 'https://example.com/']]],
        ];
        $other = array_replace($status, ['servicio' => 'Española Sefardi']);
        [$result, $untouched] = CosPresentation::statuses([$status, $other]);
        $this->assertSame('Auditoría de Expedientes', $result['currentStepName']);
        $this->assertSame('<b>¡Solicita tu Auditoría de Expedientes!</b>', $result['warning']);
        $this->assertSame('Solicita la Auditoría de Expedientes', $result['currentStepDetails']['ctas'][0]['text']);
        $this->assertSame('https://example.com/', $result['currentStepDetails']['ctas'][0]['url']);
        $this->assertSame($other, $untouched);
    }
}
