<?php

namespace Tests\Unit;

use App\Models\Negocio;
use App\Services\CosHelperService;
use App\Services\CosService;
use Carbon\Carbon;
use ReflectionClass;
use Tests\TestCase;

class CosServiceTest extends TestCase
{
    public function test_it_keeps_requested_carta_de_naturaleza_service_name(): void
    {
        $this->assertSame(
            'Española - Carta de Naturaleza General',
            $this->invokeCosServiceMethod('normalizeServiceName', 'Española - Carta de Naturaleza General ')
        );
    }

    public function test_it_resolves_carta_de_naturaleza_general_to_existing_cos_definition(): void
    {
        $this->assertSame(
            'Nacionalidad por Carta de Naturaleza',
            $this->invokeCosServiceMethod('resolveServiceName', 'Española - Carta de Naturaleza General ')
        );
    }

    public function test_cos_helper_exposes_carta_de_naturaleza_alias_with_same_phases(): void
    {
        $canonicalPhases = [
            'genealogico' => [['paso' => 1]],
            'juridico' => [['paso' => 1]],
        ];

        $result = $this->invokeCosHelperMethod('withServiceAliases', [
            'Nacionalidad por Carta de Naturaleza' => $canonicalPhases,
        ]);

        $this->assertSame($canonicalPhases, $result['Nacionalidad por Carta de Naturaleza']);
        $this->assertSame($canonicalPhases, $result['Española - Carta de Naturaleza General']);
    }

    public function test_recent_uploaded_report_does_not_mark_certificate_as_approved(): void
    {
        Carbon::setTestNow('2026-08-27 12:00:00');

        $status = $this->calculateStatusForNegocio([
            'servicio_solicitado' => 'Española Sefardi',
            'servicio_solicitado2' => 'Española Sefardi',
            'n3__informe_cargado' => '2026-08-20',
            'n7__enviado_al_dto_juridico' => '2026-08-20',
        ]);

        $this->assertSame(0, $status['certificadoDescargado']);
        $this->assertSame('Informe Cargado Recientemente', $status['description']);
        $this->assertSame(15, $status['currentStepGen']);
        $this->assertSame('Informe cargado', $status['currentStepName']);
    }

    public function test_certificate_is_approved_only_when_downloaded_field_exists(): void
    {
        $status = $this->calculateStatusForNegocio([
            'servicio_solicitado' => 'Española Sefardi',
            'servicio_solicitado2' => 'Española Sefardi',
            'n3__informe_cargado' => '2026-08-20',
            'n4__certificado_descargado' => '2026-08-21',
        ]);

        $this->assertSame(1, $status['certificadoDescargado']);
        $this->assertSame('Certificado Descargado', $status['description']);
        $this->assertSame('Certificado Aprobado', $status['currentStepName']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function calculateStatusForNegocio(array $attributes): array
    {
        $negocio = new Negocio($attributes);
        $user = (object) ['id' => 23681];

        return (new CosService($negocio, $user, collect([$negocio])))->calculateStatus();
    }

    private function invokeCosServiceMethod(string $methodName, string $serviceName): string
    {
        $class = new ReflectionClass(CosService::class);
        $service = $class->newInstanceWithoutConstructor();
        $method = $class->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invoke($service, $serviceName);
    }

    private function invokeCosHelperMethod(string $methodName, array $cos): array
    {
        $class = new ReflectionClass(CosHelperService::class);
        $helper = $class->newInstanceWithoutConstructor();
        $method = $class->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invoke($helper, $cos);
    }
}
