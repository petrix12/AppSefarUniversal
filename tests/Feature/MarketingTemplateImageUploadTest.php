<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MarketingTemplateImageUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('Cliente');
    }

    public function test_an_administrator_can_upload_a_template_image_to_s3(): void
    {
        Storage::fake('s3');
        $admin = User::factory()->create();
        $admin->givePermissionTo(Permission::findOrCreate('administrador'));

        $response = $this->actingAs($admin)->postJson(route('marketing.templates.images.store'), [
            'image' => UploadedFile::fake()->image('banner.png', 1200, 600)->size(240),
        ]);

        $response->assertOk()->assertJsonStructure(['path', 'url']);
        $path = $response->json('path');

        $this->assertStringStartsWith('marketing/templates/', $path);
        Storage::disk('s3')->assertExists($path);
    }

    public function test_the_image_upload_rejects_non_image_files(): void
    {
        Storage::fake('s3');
        $admin = User::factory()->create();
        $admin->givePermissionTo(Permission::findOrCreate('administrador'));

        $this->actingAs($admin)
            ->postJson(route('marketing.templates.images.store'), [
                'image' => UploadedFile::fake()->create('archivo.pdf', 100, 'application/pdf'),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('image');
    }
}
