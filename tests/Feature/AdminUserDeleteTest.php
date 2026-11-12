<?php

namespace Tests\Feature;

use App\Http\Middleware\AuditRequests;
use App\Models\User;
use App\Services\HubspotService;
use App\Services\TeamleaderService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminUserDeleteTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--path' => [
            'database/migrations/2014_10_12_000000_create_users_table.php',
            'database/migrations/2021_03_08_173429_create_permission_tables.php',
        ]])->assertExitCode(0);
        foreach (['Administrador', 'Cliente', 'Ventas', 'Proveedor'] as $role) Role::findOrCreate($role, 'web');
        $this->withoutMiddleware(AuditRequests::class);
        Gate::define('crud.users.index', fn () => true);
        $this->mock(HubspotService::class);
        $this->mock(TeamleaderService::class);
        Http::preventStrayRequests();
        Mail::fake();
    }

    public function test_a_guest_cannot_delete_a_user(): void
    {
        $target = User::factory()->create();
        $this->delete(route('crud.users.destroy', $target))->assertRedirect(route('login'));
        $this->assertDatabaseHas('users', ['id' => $target->id]);
    }

    #[DataProvider('restrictedRoles')]
    public function test_non_admins_do_not_see_the_button_and_cannot_delete_even_with_a_permissive_gate(array $roles): void
    {
        $target = User::factory()->create();
        $actor = User::factory()->create()->syncRoles($roles);
        $this->actingAs($actor);
        Gate::before(fn () => true);
        foreach ([true, false] as $mobile) {
            $html = view('livewire.crud.partials.user-delete-button', ['user' => $target, 'mobile' => $mobile])->render();
            $this->assertStringNotContainsString('data-user-delete', $html);
        }
        $this->deleteJson(route('crud.users.destroy', $target))->assertForbidden();
        $this->assertDatabaseHas('users', ['id' => $target->id]);
    }

    public static function restrictedRoles(): array
    {
        return [[['Ventas']], [['Cliente']], [['Administrador', 'Cliente']]];
    }

    #[DataProvider('targetRoles')]
    public function test_an_administrator_can_delete_a_test_user_regardless_of_client_or_provider_role(string $role): void
    {
        $actor = User::factory()->create()->syncRoles(['Administrador']);
        $target = User::factory()->create(['name' => "Usuario de prueba O'Connor"])->syncRoles([$role]);
        $this->actingAs($actor);
        foreach ([true, false] as $mobile) {
            $html = view('livewire.crud.partials.user-delete-button', ['user' => $target, 'mobile' => $mobile])->render();
            $this->assertStringContainsString('data-user-delete="'.$target->id.'"', $html);
            $this->assertStringContainsString('name="_method" value="DELETE"', $html);
            $this->assertStringContainsString('name="_token"', $html);
            $this->assertStringContainsString('return confirm(', $html);
            $this->assertStringContainsString('btn btn-danger', $html);
        }
        $this->delete(route('crud.users.destroy', $target))->assertRedirect(route('crud.users.index'));
        $this->assertDatabaseMissing('users', ['id' => $target->id]);
        $this->assertDatabaseHas('users', ['id' => $actor->id]);
        Http::assertNothingSent();
        Mail::assertNothingOutgoing();
    }

    public static function targetRoles(): array
    {
        return [['Cliente'], ['Proveedor']];
    }
}
