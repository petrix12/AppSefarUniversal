<?php

namespace Tests\Unit;

use App\Services\GenealogyTreeServiceMatcher;
use PHPUnit\Framework\TestCase;

class GenealogyTreeServiceMatcherTest extends TestCase
{
    public function test_it_identifies_main_and_individual_genealogy_tree_processes(): void
    {
        $this->assertTrue(GenealogyTreeServiceMatcher::requiresGetInfo(
            'Española - Carta de Naturaleza',
            'Nacionalidad Española por Carta de Naturaleza',
        ));
        $this->assertTrue(GenealogyTreeServiceMatcher::requiresGetInfo(
            'Italiana - Hermano',
            'Nacionalidad Italiana - Hermano',
        ));
        $this->assertTrue(GenealogyTreeServiceMatcher::requiresGetInfo(
            'BO2026-SOLICITUD-ESTRATEGICA-CREACION-EXPEDIENTE-GENEALOGICO',
            'Creación del expediente genealógico',
        ));
        $this->assertTrue(GenealogyTreeServiceMatcher::requiresGetInfo(
            'Árbol genealógico de Deslinde',
        ));
        $this->assertTrue(GenealogyTreeServiceMatcher::requiresGetInfo(
            'Nacionalidad Portuguesa para Familiares',
        ));
    }

    public function test_it_leaves_non_genealogy_services_on_their_configured_timing(): void
    {
        $this->assertFalse(GenealogyTreeServiceMatcher::requiresGetInfo(
            'Gestión Documental',
        ));
        $this->assertFalse(GenealogyTreeServiceMatcher::requiresGetInfo(
            'Constitución de Empresa',
        ));
    }
}
