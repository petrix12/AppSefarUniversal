<?php

namespace Tests\Feature;

use App\Models\User;
use App\Providers\EventServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use JeroenNoten\LaravelAdminLte\Events\DarkModeWasToggled;
use JeroenNoten\LaravelAdminLte\Events\ReadingDarkModePreference;
use JeroenNoten\LaravelAdminLte\Http\Controllers\DarkModeController;
use Mockery;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DealAccessAndDarkModeTest extends TestCase
{
    use RefreshDatabase;

    public function test_anonymous_visitors_are_redirected_before_viewing_or_updating_a_deal(): void
    {
        $this->get(route('deals.edit', 5192))->assertRedirect(route('login'));
        $this->post(route('deals.update', 5192))->assertRedirect(route('login'));
    }

    public function test_a_client_cannot_edit_a_deal_even_with_the_user_management_permission(): void
    {
        Role::findOrCreate('Cliente', 'web');
        Permission::findOrCreate('crud.users.index', 'web');

        $client = User::factory()->create();
        $client->assignRole('Cliente');
        $client->givePermissionTo('crud.users.index');

        $this->actingAs($client);

        $this->get(route('deals.edit', 5192))->assertForbidden();
        $this->post(route('deals.update', 5192))->assertForbidden();
        $this->post(route('deals.teamleader.suggestions', 5192))->assertForbidden();
    }

    public function test_anonymous_dark_mode_events_do_not_access_a_user_or_change_the_preference(): void
    {
        Auth::shouldReceive('user')->twice()->andReturnNull();

        $darkMode = Mockery::mock(DarkModeController::class);
        $darkMode->shouldNotReceive('enable', 'disable', 'isEnabled');

        $provider = new EventServiceProvider($this->app);
        $provider->handleReadingDarkModeEvt(new ReadingDarkModePreference($darkMode));
        $provider->handleDarkModeWasToggledEvt(new DarkModeWasToggled($darkMode));
    }

    public function test_authenticated_dark_mode_preference_is_still_read_and_saved(): void
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->dark_mode = 1;
        $user->shouldReceive('update')->once()->with(['dark_mode' => 0]);
        Auth::shouldReceive('user')->twice()->andReturn($user);

        $darkMode = Mockery::mock(DarkModeController::class);
        $darkMode->shouldReceive('enable')->once();
        $darkMode->shouldReceive('isEnabled')->once()->andReturnFalse();

        $provider = new EventServiceProvider($this->app);
        $provider->handleReadingDarkModeEvt(new ReadingDarkModePreference($darkMode));
        $provider->handleDarkModeWasToggledEvt(new DarkModeWasToggled($darkMode));
    }
}
