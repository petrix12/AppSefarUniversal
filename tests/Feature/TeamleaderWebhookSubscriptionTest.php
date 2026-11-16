<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\TeamleaderService;
use App\Services\TeamleaderWebhookSubscriptionService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Mockery;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TeamleaderWebhookSubscriptionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array', 'app.url' => 'https://app.example.test',
            'crm_sync.teamleader.enabled' => true, 'crm_sync.teamleader.secret' => str_repeat('x', 40),
            'crm_sync.teamleader.account_id' => 'account-test']);
        URL::forceRootUrl('https://app.example.test');
        URL::forceScheme('https');
        Http::preventStrayRequests();
        $this->artisan('migrate', ['--path' => [
            'database/migrations/2014_10_12_000000_create_users_table.php',
            'database/migrations/2021_03_08_173429_create_permission_tables.php',
            'database/migrations/2026_11_16_120000_create_crm_webhook_mirrors.php',
        ]])->assertExitCode(0);
        foreach (['Administrador', 'Cliente', 'Ventas'] as $role) Role::findOrCreate($role, 'web');
    }

    public function test_registration_preserves_own_existing_types_and_other_destinations_and_verifies_result(): void
    {
        $url = route('api.crm.webhook', ['provider' => 'teamleader', 'token' => str_repeat('x', 40)]);
        $types = array_values(array_unique(array_merge(['invoice.sent', 'contact.updated'], TeamleaderWebhookSubscriptionService::TYPES)));
        $teamleader = $this->mock(TeamleaderService::class);
        $teamleader->shouldReceive('crmWebhooks')->with('list')->twice()->andReturn(
            ['data' => [['url' => 'https://other.example.test/hook', 'types' => ['deal.won']], ['url' => $url, 'types' => ['invoice.sent', 'contact.updated']]]],
            ['data' => [['url' => $url, 'types' => $types]]],
        );
        $teamleader->shouldReceive('crmWebhooks')->once()->with('register', ['url' => $url, 'types' => $types])->andReturn([]);
        $result = app(TeamleaderWebhookSubscriptionService::class)->register(true);
        $this->assertTrue($result['verified']);
        $this->assertSame(0, $result['missing']);
        Http::assertNothingSent();
    }

    public function test_existing_registration_is_not_written_again(): void
    {
        $url = route('api.crm.webhook', ['provider' => 'teamleader', 'token' => str_repeat('x', 40)]);
        $this->mock(TeamleaderService::class)->shouldReceive('crmWebhooks')->once()->with('list')
            ->andReturn(['data' => [['url' => $url, 'types' => TeamleaderWebhookSubscriptionService::TYPES]]]);
        $this->assertTrue(app(TeamleaderWebhookSubscriptionService::class)->register(true)['verified']);
    }

    public function test_unverified_registration_does_not_report_success(): void
    {
        $teamleader = $this->mock(TeamleaderService::class);
        $teamleader->shouldReceive('crmWebhooks')->with('list')->twice()->andReturn(['data' => []]);
        $teamleader->shouldReceive('crmWebhooks')->with('register', Mockery::type('array'))->once()->andReturn([]);
        $this->expectException(\RuntimeException::class);
        app(TeamleaderWebhookSubscriptionService::class)->register(true);
    }

    public function test_missing_configuration_does_not_call_the_provider(): void
    {
        config(['crm_sync.teamleader.secret' => 'short']);
        $this->mock(TeamleaderService::class)->shouldNotReceive('crmWebhooks');
        $service = app(TeamleaderWebhookSubscriptionService::class);
        $this->assertContains('CRM_TEAMLEADER_WEBHOOK_SECRET', $service->missingConfiguration());
        $this->expectException(\LogicException::class);
        $service->register(true);
    }

    public function test_callback_uses_configured_app_url_instead_of_the_current_request_host(): void
    {
        URL::forceRootUrl('https://untrusted.example.test');
        $expected = 'https://app.example.test/api/webhooks/teamleader?token='.str_repeat('x', 40);
        $this->mock(TeamleaderService::class)->shouldReceive('crmWebhooks')->once()->with('list')
            ->andReturn(['data' => [['url' => $expected, 'types' => TeamleaderWebhookSubscriptionService::TYPES]]]);
        $this->assertTrue(app(TeamleaderWebhookSubscriptionService::class)->register(false)['verified']);
    }

    public function test_only_an_internal_administrator_can_register_subscriptions(): void
    {
        $url = route('admin.integrations.crm-sync.teamleader.register');
        $this->get(route('admin.integrations.crm-sync'))->assertRedirect();
        $this->post($url)->assertRedirect();
        $client = User::factory()->create()->syncRoles(['Cliente']);
        $this->actingAs($client)->post($url)->assertForbidden();
        $sales = User::factory()->create()->syncRoles(['Ventas']);
        $this->actingAs($sales)->post($url)->assertForbidden();
        $hybrid = User::factory()->create()->syncRoles(['Administrador', 'Cliente']);
        $this->actingAs($hybrid)->post($url)->assertForbidden();
        $admin = User::factory()->create()->syncRoles(['Administrador']);
        $this->mock(TeamleaderWebhookSubscriptionService::class)->shouldReceive('missingConfiguration')->once()->andReturn([])
            ->shouldReceive('register')->once()->with(true)->andReturn(['verified' => true]);
        $this->actingAs($admin)->post($url)->assertRedirect()->assertSessionHas('crm_success');
    }
}
