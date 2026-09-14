<?php

namespace Tests\Unit;

use App\Models\Agcliente;
use App\Services\GenealogyDocumentService;
use PHPUnit\Framework\TestCase;

class GenealogyDocumentPolicyTest extends TestCase
{
    public function test_client_is_only_asked_for_passport_birth_and_marriage_documents(): void
    {
        $client = new Agcliente(['IDPersona' => 1]);

        $this->assertSame(
            ['passport', 'birth_certificate', 'marriage_certificate'],
            array_keys(GenealogyDocumentService::allowedKindsForPerson($client))
        );
    }

    public function test_ancestor_can_be_asked_for_all_four_document_types(): void
    {
        $ancestor = new Agcliente(['IDPersona' => 16]);

        $this->assertSame(
            ['passport', 'birth_certificate', 'marriage_certificate', 'death_certificate'],
            array_keys(GenealogyDocumentService::allowedKindsForPerson($ancestor))
        );
    }

    public function test_legacy_labels_are_normalized_to_the_safe_document_kinds(): void
    {
        $this->assertSame('birth_certificate', GenealogyDocumentService::inferKind('Acta de Nacimiento'));
        $this->assertSame('marriage_certificate', GenealogyDocumentService::inferKind('ACTA DE MATRIMONIO'));
        $this->assertNull(GenealogyDocumentService::inferKind('Contrato interno HubSpot'));
    }
}
