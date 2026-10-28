<?php

namespace Tests\Feature;

use App\Models\Negocio;
use App\Models\TlContact;
use App\Models\TlProject;
use App\Models\User;
use App\Services\DeterministicTeamleaderDealMatcher;
use App\Services\LocalCosPortfolioService;
use App\Services\TeamleaderProjectPaymentAnalyzer;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CosPortfolioTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array']);
        $this->artisan('migrate', ['--path' => [
            'database/migrations/2014_10_12_000000_create_users_table.php',
            'database/migrations/2021_03_08_173429_create_permission_tables.php',
        ]])->assertExitCode(0);
        Schema::table('users', function (Blueprint $table) {
            $table->string('tl_id')->nullable();
            $table->string('email_2')->nullable();
            $table->unsignedBigInteger('owner_id')->nullable();
        });
        Schema::create('negocios', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            foreach (['hubspot_id', 'teamleader_id', 'dealname', 'servicio_solicitado2', 'teamleader_match_reason', 'n4__certificado_descargado'] as $column) {
                $table->string($column)->nullable();
            }
            $table->integer('teamleader_match_confidence')->nullable();
            $table->timestamp('teamleader_matched_at')->nullable();
            $table->timestamps();
        });
        Schema::create('tl_contacts', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('passport')->nullable();
            $table->string('email')->nullable();
            $table->timestamps();
        });
        Schema::create('tl_projects', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('title');
            $table->string('customer_id');
            $table->string('customer_type')->default('contact');
            $table->json('custom_fields')->nullable();
            $table->timestamp('tl_updated_at')->nullable();
            $table->timestamps();
        });
        Schema::create('compras', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_user');
            $table->unsignedBigInteger('deal_id')->nullable();
            $table->string('source')->nullable();
            $table->integer('phasenum')->nullable();
            $table->decimal('monto', 12, 2)->default(0);
            $table->boolean('pagado')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
        foreach (['Cliente', 'Administrador', 'Ventas', 'Genealogista'] as $role) {
            Role::findOrCreate($role, 'web');
        }
        Permission::findOrCreate('crud.users.index', 'web');
        Http::preventStrayRequests();
        Mail::fake();
    }

    private function actor(string $role = 'Administrador', bool $permission = true): User
    {
        $actor = User::factory()->create()->syncRoles([$role]);
        if ($permission) {
            $actor->givePermissionTo('crud.users.index');
        }
        return $actor;
    }

    private function client(int $number, ?int $owner = null): User
    {
        $client = User::factory()->create(['name' => 'Cliente '.$number, 'passport' => 'PASS-'.$number, 'tl_id' => 'contact-'.$number, 'owner_id' => $owner]);
        TlContact::create(['id' => $client->tl_id, 'passport' => $client->passport, 'email' => $client->email]);
        $definitions = TeamleaderProjectPaymentAnalyzer::PHASE_FIELDS[1];
        TlProject::create([
            'id' => 'project-'.$number, 'customer_id' => $client->tl_id,
            'title' => $client->name.' - Española Sefardi',
            'custom_fields' => [
                ['definition' => ['id' => $definitions['preestab']['id']], 'value' => '1000 EUR'],
                ['definition' => ['id' => $definitions['paid']['id']], 'value' => '250 EUR'],
                ['definition' => ['id' => DeterministicTeamleaderDealMatcher::CERTIFICATE_FIELD], 'value' => '2024-01-02'],
            ],
        ]);
        return $client;
    }

    private function deal(User $client): Negocio
    {
        return Negocio::create(['user_id' => $client->id, 'hubspot_id' => 'HS-'.$client->id, 'dealname' => $client->name.' - Española Sefardi', 'servicio_solicitado2' => 'Española Sefardi']);
    }

    public function test_bulk_report_includes_multiple_clients_and_previews_links_without_writes_or_external_calls(): void
    {
        $first = $this->client(1);
        $second = $this->client(2);
        $deal = $this->deal($first);
        $report = app(LocalCosPortfolioService::class)->generate();
        $this->assertSame(2, $report['summary']['clients']);
        $this->assertSame(2, $report['summary']['with_certificate_and_known_debt']);
        $this->assertSame(1, $report['summary']['automatic_matches']);
        $this->assertEqualsCanonicalizing([$first->id, $second->id], array_column($report['rows'], 'client_id'));
        $this->assertNull($deal->fresh()->teamleader_id);
        $this->assertDatabaseCount('compras', 0);
        Http::assertNothingSent();
        Mail::assertNothingOutgoing();
    }

    public function test_page_renders_global_results_and_the_export_includes_every_page(): void
    {
        $actor = $this->actor();
        for ($number = 1; $number <= 52; $number++) {
            $this->client($number);
        }
        $this->actingAs($actor)->get(route('crud.cos.portfolio'))
            ->assertOk()->assertViewHas('clients', fn ($rows) => $rows->total() === 52 && $rows->count() === 50);
        $response = $this->get(route('crud.cos.portfolio', ['certificate' => 'yes', 'export' => 'csv']))->assertOk();
        $lines = array_values(array_filter(explode("\n", trim($response->streamedContent()))));
        $this->assertCount(53, $lines);
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
    }

    public function test_ventas_can_only_view_export_and_associate_their_own_clients(): void
    {
        $actor = $this->actor('Ventas');
        $own = $this->client(1, $actor->id);
        $other = $this->client(2, $actor->id + 100);
        $ownDeal = $this->deal($own);
        $otherDeal = $this->deal($other);
        $this->actingAs($actor)->get(route('crud.cos.portfolio'))
            ->assertOk()->assertViewHas('clients', fn ($rows) => $rows->total() === 1 && $rows->first()['client_id'] === $own->id);
        $csv = $this->get(route('crud.cos.portfolio', ['export' => 'csv']))->streamedContent();
        $this->assertStringContainsString('PASS-1', $csv);
        $this->assertStringNotContainsString('PASS-2', $csv);
        $this->post(route('crud.cos.portfolio.associate'), ['deal_id' => $otherDeal->id])->assertRedirect();
        $this->assertSame('project-1', $ownDeal->fresh()->teamleader_id);
        $this->assertNull($otherDeal->fresh()->teamleader_id);
        $this->assertNull($ownDeal->fresh()->n4__certificado_descargado);
        $this->post(route('crud.cos.portfolio.associate'))->assertSessionHas('success', '0 asociaciones guardadas por reglas sin IA.');
        Http::assertNothingSent();
        Mail::assertNothingOutgoing();
    }

    public function test_access_requires_internal_user_permission(): void
    {
        $url = route('crud.cos.portfolio');
        $this->get($url)->assertRedirect(route('login'));
        $this->actingAs($this->actor('Genealogista', false))->get($url)->assertForbidden();
        $client = $this->client(1);
        $client->givePermissionTo('crud.users.index');
        $this->actingAs($client)->get($url)->assertForbidden();
        $this->post(route('crud.cos.portfolio.associate'))->assertForbidden();
    }

    public function test_shared_contact_does_not_automatically_assign_its_debt_to_two_users(): void
    {
        $first = $this->client(1);
        $second = User::factory()->create(['tl_id' => $first->tl_id]);
        $this->deal($first);
        $this->deal($second);
        $report = app(LocalCosPortfolioService::class)->generate();
        $this->assertSame(0, $report['summary']['automatic_matches']);
        $this->assertSame(0, $report['summary']['with_known_debt']);
        $this->assertSame(2, $report['summary']['requiring_review']);
    }

    public function test_ambiguous_deals_are_never_associated_in_a_batch(): void
    {
        $client = $this->client(1);
        $this->deal($client);
        $this->deal($client);
        $portfolio = app(LocalCosPortfolioService::class);
        $report = $portfolio->generate();
        $this->assertSame(0, $portfolio->applyMatches($report['associations']));
        $this->assertDatabaseCount('compras', 0);
        $this->assertSame(0, Negocio::whereNotNull('teamleader_id')->count());
    }

    public function test_csv_export_neutralizes_spreadsheet_formulas_in_customer_names(): void
    {
        $client = $this->client(1);
        $client->update(['name' => '=1+1']);
        $response = $this->actingAs($this->actor())->get(route('crud.cos.portfolio', ['export' => 'csv']))->assertOk();
        $this->assertStringContainsString("'=1+1", $response->streamedContent());
    }

    public function test_a_new_ambiguity_after_the_preview_prevents_the_batch_write(): void
    {
        $client = $this->client(1);
        $deal = $this->deal($client);
        $portfolio = app(LocalCosPortfolioService::class);
        $report = $portfolio->generate();
        $this->assertSame(1, $report['summary']['automatic_matches']);
        $this->deal($client);
        $this->assertSame(0, $portfolio->applyMatches($report['associations']));
        $this->assertNull($deal->fresh()->teamleader_id);
    }
}
