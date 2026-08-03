<?php

namespace Tests\Unit;

use App\Services\CosService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class CosServiceTest extends TestCase
{
    public function test_it_normalizes_carta_de_naturaleza_general_alias(): void
    {
        $this->assertSame(
            'Nacionalidad por Carta de Naturaleza',
            $this->normalizeServiceName('Española - Carta de Naturaleza General ')
        );
    }

    public function test_it_keeps_canonical_carta_de_naturaleza_service_name(): void
    {
        $this->assertSame(
            'Nacionalidad por Carta de Naturaleza',
            $this->normalizeServiceName('Nacionalidad por Carta de Naturaleza')
        );
    }

    private function normalizeServiceName(string $serviceName): string
    {
        $class = new ReflectionClass(CosService::class);
        $service = $class->newInstanceWithoutConstructor();
        $method = $class->getMethod('normalizeServiceName');
        $method->setAccessible(true);

        return $method->invoke($service, $serviceName);
    }
}