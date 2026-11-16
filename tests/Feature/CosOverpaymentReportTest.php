<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CosOverpaymentReport;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CosOverpaymentReportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array']);
        $this->artisan('migrate', ['--path' => [
            'database/migrations/2014_10_12_000000_create_users_table.php',
            'database/migrations/2021_03_08_173429_create_permission_tables.php',
        ]])->assertExitCode(0);
        Schema::table('users', fn (Blueprint $table) => $table->unsignedBigInteger('owner_id')->nullable());
        foreach (['Administrador', 'Cliente', 'Ventas'] as $role) Role::findOrCreate($role, 'web');
        Permission::findOrCreate('crud.users.index', 'web');
        Http::preventStrayRequests();
    }

    private function actor(string $role): User
    {
        return User::factory()->create()->syncRoles([$role])->givePermissionTo('crud.users.index');
    }

    public function test_external_window_uses_the_last_reconciliation_in_a_separate_finance_route(): void
    {
        $admin = $this->actor('Administrador');
        $client = User::factory()->create();
        app(CosOverpaymentReport::class)->remember($client, [
            ['project_title' => 'Demo', 'payment_label' => 'Fase 1', 'effective_preestab_amount' => 100,
                'effective_paid_amount' => 140, 'overpaid_amount' => 40, 'internal_secret' => 'do not expose'],
            ['project_title' => 'Settled', 'overpaid_amount' => 0],
        ]);
        $url = route('crud.users.overpayments', $client);
        $this->assertStringContainsString('/admin/finance/', $url);
        $this->actingAs($admin)->get($url)->assertOk()->assertSee('40,00 EUR')->assertSee('última conciliación')
            ->assertDontSee('Settled')->assertDontSee('do not expose')->assertViewHas('report', fn ($report) => count($report['rows']) === 1);
        Http::assertNothingSent();
    }

    public function test_external_data_respects_permission_owner_scope_and_client_denial(): void
    {
        $sales = $this->actor('Ventas');
        $own = User::factory()->create(['owner_id' => $sales->id]);
        $other = User::factory()->create(['owner_id' => $sales->id + 50]);
        $url = route('crud.users.overpayments', $own);
        $this->get($url)->assertRedirect(route('login'));
        $this->actingAs($sales)->get($url)->assertOk()->assertSee('generar la conciliación');
        $this->get(route('crud.users.overpayments', $other))->assertForbidden();
        $own->givePermissionTo('crud.users.index');
        $this->actingAs($own)->get($url)->assertForbidden();
        $own->assignRole('Administrador');
        $this->get($url)->assertForbidden();
    }

    public function test_independent_search_only_finds_accessible_clients(): void
    {
        $sales = $this->actor('Ventas');
        $own = User::factory()->create(['name' => 'Demo Own', 'owner_id' => $sales->id]);
        User::factory()->create(['name' => 'Demo Other', 'owner_id' => $sales->id + 99]);
        $this->actingAs($sales)->get(route('crud.finance.payment-reviews', ['q' => 'Demo']))
            ->assertOk()->assertSee('Demo Own')->assertDontSee('Demo Other');
        $this->get(route('crud.finance.payment-reviews', ['q' => (string) $own->id]))->assertOk()->assertSee('Demo Own');
    }

    public function test_old_report_expires_instead_of_being_presented_as_current(): void
    {
        $client = User::factory()->create();
        $reports = app(CosOverpaymentReport::class);
        $reports->remember($client, []);
        $this->assertNotNull($reports->get($client));
        $this->travel(25)->hours();
        $this->assertNull($reports->get($client));
    }
}
