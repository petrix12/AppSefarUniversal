<?php

namespace Tests\Feature;

use App\Http\Middleware\AuditRequests;
use App\Models\ClientNote;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SuperadminNotesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array']);
        $this->artisan('migrate', ['--path' => [
            'database/migrations/2014_10_12_000000_create_users_table.php',
            'database/migrations/2021_03_08_173429_create_permission_tables.php',
            'database/migrations/2026_09_29_090000_create_user_change_audits_table.php',
            'database/migrations/2026_10_21_120000_create_client_notes_table.php',
            'database/migrations/2026_11_19_120000_add_superadmin_and_note_deletion_audit.php',
        ]])->assertExitCode(0);
        Role::findOrCreate('Cliente');
        Role::findOrCreate('Administrador');
        Permission::findOrCreate('crud.users.index');
        $this->withoutMiddleware(AuditRequests::class);
        Http::preventStrayRequests();
    }

    public function test_only_a_superadmin_sees_the_note_delete_button_and_can_delete(): void
    {
        $client = User::factory()->create();
        $note = $client->notes()->create(['note' => 'Nota interna de prueba']);
        $admin = $this->admin();
        $url = route('crud.users.notes.destroy', [$client, $note]);
        $this->actingAs($admin)->get(route('crud.users.notes.index', $client))->assertOk()->assertDontSee($url, false);
        $this->delete($url)->assertForbidden();
        $super = $this->admin(true);
        $this->actingAs($super)->get(route('crud.users.notes.index', $client))->assertOk()->assertSee($url, false);
        $this->delete($url)->assertRedirect(route('crud.users.notes.index', $client));
        $this->assertSoftDeleted('client_notes', ['id' => $note->id, 'deleted_by' => $super->id]);
        $this->assertSame('Nota interna de prueba', ClientNote::withTrashed()->findOrFail($note->id)->note);
        $this->assertSame(0, $client->notes()->count());
        $this->delete($url)->assertNotFound();
    }

    public function test_a_note_from_another_client_cannot_be_deleted_through_the_wrong_client(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $note = $a->notes()->create(['note' => 'No cambiar']);
        $this->actingAs($this->admin(true))->delete(route('crud.users.notes.destroy', [$b, $note]))->assertNotFound();
        $this->assertNotSoftDeleted('client_notes', ['id' => $note->id]);
    }

    public function test_an_admin_cannot_grant_the_flag_or_update_a_superadmin_account(): void
    {
        $admin = $this->admin();
        $super = $this->admin(true);
        $this->actingAs($admin)->patch(route('admin.superadmins.update', $admin), ['is_superadmin' => true])->assertForbidden();
        $this->put(route('crud.users.update', $super), ['email' => 'takeover@example.test'])->assertForbidden();
        $admin->fill(['is_superadmin' => true])->save();
        $this->assertFalse($admin->fresh()->isSuperadmin());
        $this->assertFalse($super->fresh()->email === 'takeover@example.test');
    }

    public function test_the_flag_is_managed_separately_and_the_last_superadmin_cannot_be_revoked(): void
    {
        $super = $this->admin(true);
        $admin = $this->admin();
        $this->actingAs($super)->patch(route('admin.superadmins.update', $super), ['is_superadmin' => false])
            ->assertSessionHasErrors('is_superadmin');
        $this->assertTrue($super->fresh()->isSuperadmin());
        $this->patch(route('admin.superadmins.update', $admin), ['is_superadmin' => true])->assertRedirect();
        $this->assertTrue($admin->fresh()->isSuperadmin());
        $this->assertSame(['Administrador'], $admin->fresh()->getRoleNames()->all());
        $this->assertDatabaseHas('user_change_audits', ['user_id' => $admin->id, 'changed_by_user_id' => $super->id]);
    }

    public function test_client_accounts_cannot_receive_or_use_the_flag(): void
    {
        $client = User::factory()->create();
        $super = $this->admin(true);
        $this->actingAs($super)->patch(route('admin.superadmins.update', $client), ['is_superadmin' => true])->assertStatus(422);
        $client->forceFill(['is_superadmin' => true])->save();
        $this->assertFalse($client->isSuperadmin());
        $this->actingAs($client)->get(route('admin.superadmins.index'))->assertForbidden();
    }

    public function test_bootstrap_marks_only_the_existing_authorized_internal_account(): void
    {
        $root = $this->admin();
        $root->email = 'sistemasccs@sefarvzla.com';
        $root->save();
        $other = $this->admin();
        $migration = require database_path('migrations/2026_11_19_120000_add_superadmin_and_note_deletion_audit.php');
        $migration->down();
        $migration->up();
        $this->assertTrue($root->fresh()->isSuperadmin());
        $this->assertFalse($other->fresh()->isSuperadmin());
        $this->assertSame(['Administrador'], $root->fresh()->getRoleNames()->all());
    }

    private function admin(bool $super = false): User
    {
        $user = User::factory()->create();
        $user->syncRoles(['Administrador']);
        $user->givePermissionTo('crud.users.index');
        if ($super) $user->forceFill(['is_superadmin' => true])->save();
        return $user;
    }
}
