<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Jetstream\Http\Livewire\UpdateProfileInformationForm;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProfileInformationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array']);
        Role::firstOrCreate(['name' => 'Cliente', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Administrador', 'guard_name' => 'web']);
    }

    public function test_current_profile_information_is_available()
    {
        $this->actingAs($user = User::factory()->create());

        $component = Livewire::test(UpdateProfileInformationForm::class);

        $this->assertEquals($user->name, $component->state['name']);
        $this->assertEquals($user->email, $component->state['email']);
    }

    public function test_profile_information_can_be_updated()
    {
        $this->actingAs($user = User::factory()->create());

        Livewire::test(UpdateProfileInformationForm::class)
                ->set('state', ['name' => 'Test Name', 'email' => 'test@example.com'])
                ->call('updateProfileInformation');

        $this->assertEquals('Test Name', $user->fresh()->name);
        $this->assertEquals('test@example.com', $user->fresh()->email);
    }

    public function test_profile_page_loads_one_document_and_uses_livewires_alpine_runtime(): void
    {
        Storage::fake('s3');
        $user = User::factory()->create(['profile_photo_path' => 'profile-photos/current.jpg']);
        $user->syncRoles(['Administrador']);
        $this->actingAs($user);

        $response = $this->get(route('profile.show'))->assertOk();
        $html = $response->getContent();

        $this->assertSame(1, preg_match_all('/<!doctype html>/i', $html));
        $this->assertSame(1, preg_match_all('/<script[^>]+src="[^"]*\/livewire(?:\.min)?\.js\?/', $html));
        $this->assertDoesNotMatchRegularExpression('/<script[^>]+src="[^"]*\/js\/app\.js/', $html);
        $response->assertSee('wire:click="deleteProfilePhoto"', false);
    }

    public function test_a_new_profile_photo_is_saved_and_replaces_the_previous_file(): void
    {
        Storage::fake('s3');
        Storage::disk('s3')->put('profile-photos/old.jpg', 'previous photo');
        $this->actingAs($user = User::factory()->create(['profile_photo_path' => 'profile-photos/old.jpg']));
        $photo = UploadedFile::fake()->image('profile.jpg');

        Livewire::test(UpdateProfileInformationForm::class)
            ->set('photo', $photo)
            ->call('updateProfileInformation')
            ->assertHasNoErrors()
            ->assertRedirect(route('profile.show'));

        $path = $user->fresh()->profile_photo_path;
        $this->assertStringStartsWith('profile-photos/', $path);
        $this->assertNotSame('profile-photos/old.jpg', $path);
        Storage::disk('s3')->assertExists($path);
        Storage::disk('s3')->assertMissing('profile-photos/old.jpg');
    }

    public function test_removing_the_profile_photo_deletes_the_file_and_restores_the_default_avatar(): void
    {
        Storage::fake('s3');
        Storage::disk('s3')->put('profile-photos/current.jpg', 'current photo');
        $this->actingAs($user = User::factory()->create(['profile_photo_path' => 'profile-photos/current.jpg']));
        $previousName = $user->name;
        $previousEmail = $user->email;

        Livewire::test(UpdateProfileInformationForm::class)
            ->call('deleteProfilePhoto')
            ->assertHasNoErrors();

        $user->refresh();
        $this->assertNull($user->profile_photo_path);
        $this->assertStringStartsWith('https://ui-avatars.com/api/', $user->profile_photo_url);
        $this->assertSame($previousName, $user->name);
        $this->assertSame($previousEmail, $user->email);
        Storage::disk('s3')->assertMissing('profile-photos/current.jpg');
    }

    public function test_an_invalid_photo_does_not_replace_the_existing_photo(): void
    {
        Storage::fake('s3');
        Storage::disk('s3')->put('profile-photos/current.jpg', 'current photo');
        $this->actingAs($user = User::factory()->create(['profile_photo_path' => 'profile-photos/current.jpg']));

        Livewire::test(UpdateProfileInformationForm::class)
            ->set('photo', UploadedFile::fake()->create('document.pdf', 20, 'application/pdf'))
            ->call('updateProfileInformation')
            ->assertHasErrors(['photo' => 'mimes']);

        $this->assertSame('profile-photos/current.jpg', $user->fresh()->profile_photo_path);
        Storage::disk('s3')->assertExists('profile-photos/current.jpg');
    }
}
