<?php

namespace Tests\Feature;

use App\Http\Controllers\NegocioController;
use App\Models\Negocio;
use App\Services\HubspotService;
use App\Services\TeamleaderService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class TeamleaderServiceMissingCustomerIdTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('tl_id')->nullable();
            $table->timestamps();
        });

        Schema::create('negocios', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('hubspot_id')->nullable();
            $table->string('teamleader_id')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('negocios');
        Schema::dropIfExists('users');

        parent::tearDown();
    }

    public function test_it_returns_no_projects_when_the_customer_has_no_teamleader_id(): void
    {
        Log::shouldReceive('channel')
            ->once()
            ->with('teamleader')
            ->andReturnSelf();
        Log::shouldReceive('warning')
            ->once()
            ->with(
                'Teamleader: customerId inválido al obtener proyectos con detalle',
                ['customer_id' => null]
            );

        $projects = app(TeamleaderService::class)->getProjectsWithDetailsByCustomerId(null);

        $this->assertSame([], $projects);
    }

    public function test_editing_a_deal_without_a_teamleader_customer_id_skips_the_project_lookup(): void
    {
        app('db')->table('users')->insert([
            'id' => 1,
            'name' => 'Cliente sin Teamleader',
            'email' => 'cliente@example.test',
            'tl_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $deal = Negocio::create([
            'user_id' => 1,
            'hubspot_id' => 'hubspot-deal',
            'teamleader_id' => null,
        ]);

        $teamleader = Mockery::mock(TeamleaderService::class);
        $teamleader->shouldNotReceive('getProjectsWithDetailsByCustomerId');

        $hubspot = Mockery::mock(HubspotService::class);
        $hubspot->shouldReceive('getDealById')
            ->once()
            ->with('hubspot-deal')
            ->andReturn([
                'properties' => ['lastmodifieddate' => '2026-01-01T00:00:00Z'],
            ]);

        $response = (new NegocioController($teamleader, $hubspot))->edit($deal->id);

        $this->assertSame([], $response->getData()['TLdeals']);
    }
}
