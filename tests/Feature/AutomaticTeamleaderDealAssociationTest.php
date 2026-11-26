<?php

namespace Tests\Feature;

use App\Models\Negocio;
use App\Models\TlProject;
use App\Models\User;
use App\Services\AutomaticTeamleaderDealAssociationService;
use App\Services\HubspotService;
use App\Services\TeamleaderService;
use App\Services\UnificationAiSuggestionService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AutomaticTeamleaderDealAssociationTest extends TestCase
{
    public function test_automatic_link_requires_a_score_strictly_above_eighty(): void
    {
        $this->artisan('migrate', ['--path' => 'database/migrations/2014_10_12_000000_create_users_table.php'])->assertSuccessful();
        Schema::table('users', fn (Blueprint $table) => $table->string('tl_id')->nullable());
        Schema::create('negocios', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            foreach (['hubspot_id', 'teamleader_id', 'codigo_de_proceso', 'servicio_solicitado', 'dealname'] as $column) {
                $table->string($column)->nullable();
            }
            $table->timestamps();
        });
        Schema::create('tl_projects', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('title');
            $table->string('status');
            $table->string('customer_id');
            $table->string('customer_type');
            $table->json('custom_fields');
            $table->timestamps();
        });
        $user = User::withoutEvents(fn () => User::factory()->create(['tl_id' => 'tl-contact']));
        $deal = Negocio::create([
            'user_id' => $user->id, 'hubspot_id' => '123', 'codigo_de_proceso' => 'CN-42',
            'dealname' => 'Carta de Naturaleza', 'servicio_solicitado' => 'Carta de Naturaleza',
        ]);
        TlProject::create([
            'id' => 'project', 'title' => 'Carta de Naturaleza', 'status' => 'active',
            'customer_id' => 'tl-contact', 'customer_type' => 'contact',
            'custom_fields' => [
                ['id' => 'fcd48891-20f6-049a-a05f-f78a6f951b4d', 'value' => 'Carta de Naturaleza'],
                ['id' => 'a42f63f5-d527-0544-ab50-9c03857707f2', 'value' => 'CN-42'],
            ],
        ]);
        $this->mock(TeamleaderService::class);
        $this->mock(HubspotService::class);
        $ai = $this->mock(UnificationAiSuggestionService::class);
        $ai->shouldReceive('available')->andReturnTrue();
        $ai->shouldReceive('suggestDealAssociations')->twice()->andReturn(
            ['suggestions' => [['id' => 'project', 'confidence' => 80]]],
            ['suggestions' => [['id' => 'project', 'confidence' => 81]]],
        );
        $association = app(AutomaticTeamleaderDealAssociationService::class);

        $this->assertSame(0, $association->associateLocalBatch(write: true)['linked']);
        $this->assertNull($deal->fresh()->teamleader_id);
        $this->assertSame(1, $association->associateLocalBatch(write: true)['linked']);
        $this->assertSame('project', $deal->fresh()->teamleader_id);
        $this->assertSame(0, $association->associateLocalBatch(write: true)['processed']);
    }
}
