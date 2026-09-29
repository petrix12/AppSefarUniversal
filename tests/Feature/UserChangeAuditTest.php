<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserChangeAudit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserChangeAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('Cliente');
        Role::findOrCreate('Administrador');
    }

    public function test_it_records_the_editor_and_only_the_fields_that_changed(): void
    {
        $editor = User::factory()->create();
        $editor->assignRole('Administrador');
        $client = User::factory()->create(['nombres' => 'Nombre anterior']);

        $this->actingAs($editor);
        $client->update(['nombres' => 'Nombre actualizado']);

        $audit = UserChangeAudit::query()->sole();

        $this->assertSame($client->id, $audit->user_id);
        $this->assertSame($editor->id, $audit->changed_by_user_id);
        $this->assertSame(['nombres' => 'Nombre anterior'], $audit->old_values);
        $this->assertSame(['nombres' => 'Nombre actualizado'], $audit->new_values);
    }

    public function test_it_redacts_sensitive_user_fields(): void
    {
        $client = User::factory()->create();

        $client->update(['password_md5' => 'not-exposed']);

        $audit = UserChangeAudit::query()->sole();

        $this->assertSame('[oculto]', $audit->old_values['password_md5']);
        $this->assertSame('[oculto]', $audit->new_values['password_md5']);
    }
}
