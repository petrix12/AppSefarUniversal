<?php

namespace Tests\Unit;

use App\Services\CosHelperService;
use App\Services\CosService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

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
