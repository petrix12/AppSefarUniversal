<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\ClientFileReviewService;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ClientFileReviewEligibilityTest extends TestCase
{
    public function test_only_a_client_with_hubspot_and_passport_can_trigger_a_file_review(): void
    {
        $client = new User(['hs_id' => '123', 'passport' => 'V123456']);
        $client->setRelation('roles', new Collection([new Role(['name' => 'Cliente'])]));

        $internal = new User(['hs_id' => '456', 'passport' => 'V654321']);
        $internal->setRelation('roles', new Collection([new Role(['name' => 'Administrador'])]));

        $missingHubspot = new User(['passport' => 'V111111']);
        $missingHubspot->setRelation('roles', new Collection([new Role(['name' => 'Cliente'])]));

        $service = new ClientFileReviewService();

        $this->assertTrue($service->canReview($client));
        $this->assertFalse($service->canReview($internal));
        $this->assertFalse($service->canReview($missingHubspot));
    }
}
