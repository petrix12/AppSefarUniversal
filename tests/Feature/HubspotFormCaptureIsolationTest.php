<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\HtmlString;
use Mockery;
use Tests\TestCase;

class HubspotFormCaptureIsolationTest extends TestCase
{
    public function test_login_does_not_load_automatic_hubspot_form_capture(): void
    {
        $this->get('/login')->assertOk()
            ->assertSee('name="email"', false)
            ->assertDontSee('js.hs-scripts.com', false);
    }

    public function test_admin_shell_does_not_expose_other_clients_forms_to_hubspot_tracking(): void
    {
        $this->signInForLayout(false);
        view()->startSection('body', '<form><input name="email" value="another-client@example.test"></form>');

        $html = view('adminlte::master')->render();

        $this->assertStringContainsString('another-client@example.test', $html);
        $this->assertStringNotContainsString('js.hs-scripts.com', $html);
    }

    public function test_account_layout_does_not_load_automatic_hubspot_form_capture(): void
    {
        $this->signInForLayout(true);

        $html = view('layouts.app', [
            'slot' => new HtmlString('<form><input name="email" value="client@example.test"></form>'),
        ])->render();

        $this->assertStringContainsString('client@example.test', $html);
        $this->assertStringNotContainsString('js.hs-scripts.com', $html);
    }

    private function signInForLayout(bool $isClient): void
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->id = 1;
        $user->shouldReceive('hasRole')->andReturn($isClient);
        $this->actingAs($user);
    }
}
