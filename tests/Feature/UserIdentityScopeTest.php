<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserIdentityScopeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->timestamps();
        });

        require_once database_path('migrations/2021_03_08_173429_create_permission_tables.php');
        (new \CreatePermissionTables())->up();
        Role::create(['name' => 'Cliente', 'guard_name' => 'web']);
        Role::create(['name' => 'Ventas', 'guard_name' => 'web']);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('model_has_roles');
        Schema::dropIfExists('role_has_permissions');
        Schema::dropIfExists('model_has_permissions');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('users');
        parent::tearDown();
    }

    public function test_client_and_internal_scopes_are_disjoint(): void
    {
        $clientId = DB::table('users')->insertGetId(['name' => 'Cliente', 'email' => 'client@example.test']);
        $internalId = DB::table('users')->insertGetId(['name' => 'Ventas', 'email' => 'sales@example.test']);

        User::find($clientId)->assignRole('Cliente');
        User::find($internalId)->assignRole('Ventas');

        $this->assertSame([$clientId], User::clients()->pluck('id')->all());
        $this->assertSame([$internalId], User::internalUsers()->pluck('id')->all());
    }
}
