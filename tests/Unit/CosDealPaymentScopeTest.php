<?php

namespace Tests\Unit;

use App\Services\CosDealPaymentScope;
use App\Services\DeterministicTeamleaderDealMatcher;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CosDealPaymentScopeTest extends TestCase
{
    #[DataProvider('dealServices')]
    public function test_nationality_phases_are_scoped_to_the_service(array $deal, bool $allowed): void
    {
        foreach ([0, 1, 2, 3, 98, 99] as $phase) {
            $this->assertSame($allowed, CosDealPaymentScope::allowsDealPhase($deal, $phase));
        }
    }

    public static function dealServices(): array
    {
        return [
            'Portuguese translation' => [['servicio_solicitado2' => 'Traducción al Portugués'], false],
            'case and accents' => [['servicio_solicitado2' => '  TRADUCCION AL PORTUGUES  '], false],
            'legacy service' => [['servicio_solicitado2' => '', 'servicio_solicitado' => 'Traducción al Portugués'], false],
            'explicit title fallback' => [['dealname' => 'Cliente de prueba - Traducción al Portugués'], false],
            'nationality wins over stale IT field' => [[
                'servicio_solicitado2' => 'Portuguesa Sefardi', 'servicio_solicitado' => 'Traducción al Portugués',
            ], true],
            'nationality' => [['servicio_solicitado2' => 'Española Sefardi'], true],
            'unspecified service retains existing behavior' => [['dealname' => 'Proceso del solicitante'], true],
            'translation mentioned in prose is not a service' => [['dealname' => 'Nacionalidad Portuguesa con traducción incluida'], true],
        ];
    }

    public function test_a_translation_project_cannot_supply_nationality_phases_in_either_direction(): void
    {
        $translation = ['servicio_solicitado2' => 'Traducción al Portugués'];
        $nationalityProject = ['title' => 'Cliente - Nacionalidad Portuguesa'];
        $translationProject = ['title' => 'Cliente', 'custom_fields' => [[
            'definition' => ['id' => DeterministicTeamleaderDealMatcher::SERVICE_FIELD],
            'value' => 'Traducción al Portugués',
        ]]];
        $this->assertFalse(CosDealPaymentScope::allowsProjectPhase($translationProject, 1));
        foreach (['fase_1_preestab', 'fase_1_pagado__teamleader_', 'monto_fase_2_pagado', 'fase_3_pagado', 'carta_nat_preestab', 'cil___fcje_pagado'] as $field) {
            $this->assertFalse(CosDealPaymentScope::allowsEnrichmentField($translation, $nationalityProject, $field));
            $this->assertFalse(CosDealPaymentScope::allowsEnrichmentField(['servicio_solicitado2' => 'Portuguesa Sefardi'], $translationProject, $field));
        }
        $this->assertTrue(CosDealPaymentScope::allowsEnrichmentField($translation, $translationProject, 'documentos'));
        $this->assertTrue(CosDealPaymentScope::allowsEnrichmentField($translation, $translationProject, 'amount'));
    }
}
