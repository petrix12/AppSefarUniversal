<?php

namespace Tests\Feature;

use App\Http\Controllers\TlContactController;
use App\Exceptions\TeamleaderAuthenticationException;
use App\Models\TlCompany;
use App\Models\TlContact;
use App\Models\TlDeal;
use App\Models\TlProject;
use App\Services\TeamleaderService;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class TlContactControllerTest extends TestCase
{
    private object $migration;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.connections.teamleader_contact_refresh_test', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        config()->set('database.default', 'teamleader_contact_refresh_test');
        DB::purge('teamleader_contact_refresh_test');

        $this->migration = require database_path('migrations/2026_03_13_164351_create_tl_sync_tables.php');
        $this->migration->up();
    }

    protected function tearDown(): void
    {
        $this->migration->down();
        DB::disconnect('teamleader_contact_refresh_test');

        parent::tearDown();
    }

    public function test_it_refreshes_a_contact_and_its_current_teamleader_deals_and_projects(): void
    {
        $contactId = '5d5a0466-b4ce-0bb6-b375-f26c92de7d78';
        $otherContactId = '8d5a0466-b4ce-0bb6-b375-f26c92de7d79';

        TlContact::create([
            'id' => $contactId,
            'first_name' => 'Anterior',
            'last_name' => 'Nombre',
            'raw_data' => [],
        ]);
        TlDeal::create([
            'id' => '7d5a0466-b4ce-0bb6-b375-f26c92de7d70',
            'title' => 'Deal reasignado',
            'customer_id' => $contactId,
            'customer_type' => 'contact',
            'raw_data' => [],
        ]);
        TlProject::create([
            'id' => '4d5a0466-b4ce-0bb6-b375-f26c92de7d73',
            'title' => 'Proyecto reasignado',
            'customer_id' => $contactId,
            'customer_type' => 'contact',
            'raw_data' => [],
        ]);

        $teamleader = Mockery::mock(TeamleaderService::class);
        $teamleader->shouldReceive('getContactById')
            ->once()
            ->with($contactId)
            ->andReturn($this->contactPayload($contactId));
        $teamleader->shouldReceive('listDealsByContactId')
            ->once()
            ->with($contactId, 1, 100)
            ->andReturn([
                'data' => [
                    ['id' => '6d5a0466-b4ce-0bb6-b375-f26c92de7d71'],
                ],
                'meta' => ['matches' => 1],
            ]);
        $teamleader->shouldReceive('getDealById')
            ->once()
            ->with('7d5a0466-b4ce-0bb6-b375-f26c92de7d70')
            ->andReturn($this->dealPayload('7d5a0466-b4ce-0bb6-b375-f26c92de7d70', $otherContactId, 'Deal reasignado'));
        $teamleader->shouldReceive('getDealById')
            ->once()
            ->with('6d5a0466-b4ce-0bb6-b375-f26c92de7d71')
            ->andReturn($this->dealPayload('6d5a0466-b4ce-0bb6-b375-f26c92de7d71', $contactId, 'Deal nuevo'));
        $teamleader->shouldReceive('listProjectsByContactId')
            ->once()
            ->with($contactId, 1, 100)
            ->andReturn([
                'data' => [
                    ['id' => '3d5a0466-b4ce-0bb6-b375-f26c92de7d74'],
                ],
                'meta' => ['matches' => 1],
            ]);
        $teamleader->shouldReceive('getProjectDetails')
            ->once()
            ->with('4d5a0466-b4ce-0bb6-b375-f26c92de7d73')
            ->andReturn($this->projectPayload('4d5a0466-b4ce-0bb6-b375-f26c92de7d73', $otherContactId, 'Proyecto reasignado'));
        $teamleader->shouldReceive('getProjectDetails')
            ->once()
            ->with('3d5a0466-b4ce-0bb6-b375-f26c92de7d74')
            ->andReturn($this->projectPayload('3d5a0466-b4ce-0bb6-b375-f26c92de7d74', $contactId, 'Proyecto nuevo'));

        $response = app(TlContactController::class)->refresh($contactId, $teamleader);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertDatabaseHas('tl_contacts', [
            'id' => $contactId,
            'first_name' => 'Nombre actualizado',
            'email' => 'actualizado@example.test',
        ]);
        $this->assertDatabaseHas('tl_deals', [
            'id' => '6d5a0466-b4ce-0bb6-b375-f26c92de7d71',
            'title' => 'Deal nuevo',
            'customer_id' => $contactId,
        ]);
        $this->assertDatabaseHas('tl_deals', [
            'id' => '7d5a0466-b4ce-0bb6-b375-f26c92de7d70',
            'customer_id' => $otherContactId,
        ]);
        $this->assertDatabaseHas('tl_projects', [
            'id' => '3d5a0466-b4ce-0bb6-b375-f26c92de7d74',
            'title' => 'Proyecto nuevo',
            'customer_id' => $contactId,
        ]);
        $this->assertDatabaseHas('tl_projects', [
            'id' => '4d5a0466-b4ce-0bb6-b375-f26c92de7d73',
            'customer_id' => $otherContactId,
        ]);
    }

    public function test_it_offers_an_administrator_a_reconnect_link_when_teamleader_requires_authentication(): void
    {
        $contactId = '5d5a0466-b4ce-0bb6-b375-f26c92de7d78';
        TlContact::create([
            'id' => $contactId,
            'first_name' => 'Contacto',
            'raw_data' => [],
        ]);

        $teamleader = Mockery::mock(TeamleaderService::class);
        $teamleader->shouldReceive('getContactById')
            ->once()
            ->with($contactId)
            ->andThrow(new TeamleaderAuthenticationException());

        $response = app(TlContactController::class)->refresh($contactId, $teamleader);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringContainsString('Reconectar Teamleader', session('error'));
        $this->assertStringContainsString(route('teamleader.redirect'), session('error'));
    }

    public function test_a_deal_resolves_its_contact_or_company_without_filtering_the_related_table(): void
    {
        $contact = TlContact::create([
            'id' => '5d5a0466-b4ce-0bb6-b375-f26c92de7d78',
            'first_name' => 'Contacto',
            'raw_data' => [],
        ]);
        $company = TlCompany::create([
            'id' => '8d5a0466-b4ce-0bb6-b375-f26c92de7d79',
            'name' => 'Empresa',
            'raw_data' => [],
        ]);
        $contactDeal = TlDeal::create([
            'id' => '7d5a0466-b4ce-0bb6-b375-f26c92de7d70',
            'customer_id' => $contact->id,
            'customer_type' => 'contact',
            'raw_data' => [],
        ]);
        $companyDeal = TlDeal::create([
            'id' => '6d5a0466-b4ce-0bb6-b375-f26c92de7d71',
            'customer_id' => $company->id,
            'customer_type' => 'company',
            'raw_data' => [],
        ]);

        $this->assertSame($contact->id, $contactDeal->contact->id);
        $this->assertSame($company->id, $companyDeal->company->id);
    }

    private function contactPayload(string $id): array
    {
        return [
            'id' => $id,
            'first_name' => 'Nombre actualizado',
            'last_name' => 'Apellido actualizado',
            'status' => 'active',
            'emails' => [['type' => 'primary', 'email' => 'actualizado@example.test']],
            'telephones' => [],
            'addresses' => [],
            'custom_fields' => [],
            'tags' => [],
            'added_at' => '2026-01-01T10:00:00+00:00',
            'updated_at' => '2026-09-01T10:00:00+00:00',
        ];
    }

    private function dealPayload(string $id, string $contactId, string $title): array
    {
        return [
            'id' => $id,
            'title' => $title,
            'status' => 'open',
            'estimated_value' => ['amount' => 1500, 'currency' => 'EUR'],
            'weighted_revenue' => ['amount' => 750],
            'lead' => ['customer' => ['id' => $contactId, 'type' => 'contact']],
            'responsible_user' => ['id' => '9d5a0466-b4ce-0bb6-b375-f26c92de7d72'],
            'source' => ['id' => 'origen'],
            'custom_fields' => [],
            'tags' => [],
            'created_at' => '2026-01-01T10:00:00+00:00',
            'updated_at' => '2026-09-01T10:00:00+00:00',
        ];
    }

    private function projectPayload(string $id, string $contactId, string $title): array
    {
        return [
            'id' => $id,
            'title' => $title,
            'status' => 'active',
            'customer' => ['id' => $contactId, 'type' => 'contact'],
            'budget' => ['amount' => 2500, 'currency' => 'EUR'],
            'responsible_user' => ['id' => '9d5a0466-b4ce-0bb6-b375-f26c92de7d72'],
            'participants' => [],
            'milestones' => [],
            'custom_fields' => [],
            'tags' => [],
            'created_at' => '2026-01-01T10:00:00+00:00',
            'updated_at' => '2026-09-01T10:00:00+00:00',
        ];
    }
}
