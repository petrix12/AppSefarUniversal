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

    public function test_a_pending_portuguese_action_appears_before_granted_spanish_nationality_with_service_offers(): void
    {
        $spanish = [
            'servicio' => 'Española Sefardi', 'warning' => null,
            'description' => 'Nacionalidad Concedida', 'currentStepName' => '¡Nacionalidad concedida!',
            'currentStepGen' => 17, 'currentStepJur' => 8, 'progressPercentageJur' => 89,
            'currentStepDetails' => ['ctas' => [['text' => 'Asistencia con DNI y pasaporte', 'url' => 'https://example.test/servicios']]],
        ];
        $portuguese = [
            'servicio' => 'Portuguesa Sefardi', 'warning' => '<b>¡Solicita tu Auditoría de Expedientes!</b>',
            'currentStepName' => 'Activa tu Auditoría de Expedientes',
            'currentStepGen' => 17, 'currentStepJur' => 6, 'progressPercentageJur' => 60,
        ];

        $result = CosPresentation::statuses([$spanish, $portuguese]);

        $this->assertSame([$portuguese, $spanish], $result);
        $this->assertSame($result, CosPresentation::statuses($result));
    }

    public function test_pending_document_and_payment_actions_keep_their_order_and_other_processes_remain_visible(): void
    {
        $waiting = ['servicio' => 'Española Sefardi', 'warning' => null];
        $documents = ['servicio' => 'Portuguesa Sefardi', 'warning' => 'Tienes solicitudes de documentos pendientes.'];
        $blank = ['servicio' => 'Italiana', 'warning' => '<p>   </p>'];
        $payment = ['servicio' => 'Española LMD', 'warning' => 'Es necesario que realices el siguiente pago.'];

        $this->assertSame(
            [$documents, $payment, $waiting, $blank],
            CosPresentation::statuses([$waiting, $documents, $blank, $payment]),
        );
        $this->assertSame([], CosPresentation::statuses([]));
    }
}
