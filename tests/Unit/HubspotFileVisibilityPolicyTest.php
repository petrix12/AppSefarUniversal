<?php

namespace Tests\Unit;

use App\Services\HubspotService;
use PHPUnit\Framework\TestCase;

class HubspotFileVisibilityPolicyTest extends TestCase
{
    public function test_only_the_three_controlled_hubspot_fields_are_client_eligible(): void
    {
        $this->assertTrue(HubspotService::isClientEligibleFileSource(
            'hubspot',
            'hubspot:pasaporte__documento_:hash'
        ));
        $this->assertTrue(HubspotService::isClientEligibleFileSource(
            'hubspot',
            'hubspot:documentos_adicionales:hash'
        ));
        $this->assertFalse(HubspotService::isClientEligibleFileSource(
            'hubspot',
            'hubspot:comprobante_de_pago___registro:hash'
        ));
        $this->assertFalse(HubspotService::isClientEligibleFileSource('hubspot', null));
    }
}
