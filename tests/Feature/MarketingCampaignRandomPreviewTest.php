<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MarketingCampaignRandomPreviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('Cliente');
    }

    public function test_an_administrator_can_preview_all_standard_variables_with_a_random_app_user(): void
    {
        $admin = User::factory()->create();
        $admin->givePermissionTo(Permission::findOrCreate('administrador'));

        User::factory()->create([
            'nombres' => 'María',
            'apellidos' => 'García López',
            'email' => 'maria.garcia@example.test',
        ]);

        $response = $this->actingAs($admin)->postJson(route('marketing.campaigns.preview-random-recipient'), [
            'subject' => 'Hola, {{full_name}}',
            'body_html' => '<p>{{first_name}} {{last_name}} · {{full_name}} · {{email}}</p><script>alert(1)</script>',
            'body_text' => 'Para {{first_name}}: {{email}}',
        ]);

        $response->assertOk()
            ->assertJsonPath('subject', 'Hola, María García López')
            ->assertJsonPath('text', 'Para María: maria.garcia@example.test')
            ->assertJsonPath('recipient.first_name', 'María')
            ->assertJsonPath('recipient.last_name', 'García López')
            ->assertJsonPath('recipient.full_name', 'María García López')
            ->assertJsonPath('recipient.email', 'maria.garcia@example.test');

        $this->assertStringContainsString('María García López', $response->json('html'));
        $this->assertStringContainsString('maria.garcia@example.test', $response->json('html'));
        $this->assertStringNotContainsString('<script>', $response->json('html'));
    }
}
