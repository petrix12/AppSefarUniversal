<?php

namespace Tests\Feature;

use App\Mail\CargaCliente;
use App\Mail\CargaSefar;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ClientTreeCompletionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $clientRole = Role::findOrCreate('Cliente');
        Permission::findOrCreate('cliente')->syncRoles($clientRole);
    }

    public function test_client_can_finalize_the_tree_without_being_logged_out_or_emailed(): void
    {
        Mail::fake();
        $client = User::factory()->create(['passport' => 'V12345678']);
        $client->assignRole('Cliente');

        $response = $this->actingAs($client)
            ->post(route('clientes.finalizar-carga'));

        $response->assertRedirect(route('clientes.tree'));
        $this->assertAuthenticatedAs($client);
        Mail::assertSent(CargaSefar::class);
        Mail::assertNotSent(CargaCliente::class);
    }

    public function test_tree_completion_endpoint_rejects_get_requests(): void
    {
        $this->get('/finalizar-carga')->assertStatus(405);
    }
}
