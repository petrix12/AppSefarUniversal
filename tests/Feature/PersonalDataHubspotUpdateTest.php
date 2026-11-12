<?php

namespace Tests\Feature;

use App\Http\Middleware\AuditRequests;
use App\Models\User;
use App\Models\UserGenealogyTreeLink;
use App\Services\HubspotService;
use App\Services\TeamleaderService;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Request;
use HubSpot\Factory;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PersonalDataHubspotUpdateTest extends TestCase
{
    private array $hubspotRequests = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate', ['--path' => [
            'database/migrations/2014_10_12_000000_create_users_table.php',
            'database/migrations/2021_03_08_173429_create_permission_tables.php',
            'database/migrations/2026_09_01_000000_add_contrato_to_users_table.php',
            'database/migrations/2026_09_28_120100_ensure_user_genealogy_tree_links_table.php',
            'database/migrations/2026_09_29_090000_create_user_change_audits_table.php',
        ]])->assertExitCode(0);
        Schema::create('agclientes', function (Blueprint $table): void {
            $table->id();
            $table->string('IDCliente');
            $table->integer('IDPersona');
        });
        Role::findOrCreate('Cliente', 'web');
        Role::findOrCreate('Administrador', 'web');
        Gate::define('genealogista', fn () => true);
        $this->withoutMiddleware(AuditRequests::class);
        Http::preventStrayRequests();
        Mail::fake();
        $this->instance(TeamleaderService::class, Mockery::mock(TeamleaderService::class));
        $this->hubspotResponds(200, ['id' => 'hs-original', 'properties' => []]);
    }

    public function test_a_duplicate_hubspot_email_returns_validation_and_rolls_back_all_local_changes(): void
    {
        $client = $this->client();
        UserGenealogyTreeLink::create(['user_id' => $client->id, 'tree_id' => 'OLD-TREE']);
        Schema::getConnection()->table('agclientes')->insert(['IDCliente' => 'NEW-TREE', 'IDPersona' => 1]);
        $this->hubspotResponds(400, [
            'status' => 'error',
            'message' => 'Cannot set PropertyValueCoordinates{portalId=1, propertyName=email, value=new@example.test} on hs-original. hs-other already has that value.',
            'category' => 'VALIDATION_ERROR',
        ]);

        $this->actingAs($this->administrator())->postJson(route('saveuserdata'), $this->payload($client) + [
            'genealogy_tree_id' => 'NEW-TREE',
        ])->assertUnprocessable()->assertJsonValidationErrors('email')
            ->assertJsonPath('errors.email.0', 'Este correo ya pertenece a otro contacto en HubSpot. Revisa el correo y la vinculación del cliente antes de guardar. No se guardaron cambios.');

        $this->assertOriginalClient($client);
        $this->assertSame('OLD-TREE', $client->fresh()->genealogy_tree_id);
        $this->assertDatabaseCount('user_change_audits', 0);
        $this->assertCount(1, $this->hubspotRequests);
        $this->assertStringEndsWith('/crm/v3/objects/contacts/hs-original', (string) $this->hubspotRequests[0]['request']->getUri());
    }

    #[DataProvider('hubspotFailures')]
    public function test_other_hubspot_failures_are_controlled_and_do_not_save_local_data(int $status, string $body): void
    {
        $client = $this->client();
        $this->hubspotResponds($status, $body);

        $this->actingAs($this->administrator())->postJson(route('saveuserdata'), $this->payload($client))
            ->assertStatus(502)->assertJsonPath('message', 'HubSpot no pudo actualizar el contacto. No se guardaron cambios. Inténtalo de nuevo; si persiste, solicita revisar la integración.');

        $this->assertOriginalClient($client);
        $this->assertDatabaseCount('user_change_audits', 0);
        $this->assertCount(1, $this->hubspotRequests);
    }

    public static function hubspotFailures(): array
    {
        return [
            'other validation error' => [400, '{"category":"VALIDATION_ERROR","message":"Property does not exist"}'],
            'rate limit' => [429, '{"category":"RATE_LIMITS"}'],
            'unavailable' => [503, 'Service unavailable'],
        ];
    }

    public function test_success_saves_locally_and_updates_only_the_linked_contact_with_the_expected_mapping(): void
    {
        $client = $this->client();
        $this->actingAs($this->administrator())->postJson(route('saveuserdata'), $this->payload($client))
            ->assertOk()->assertJsonPath('message', 'Datos actualizados correctamente.');

        $this->assertDatabaseHas('users', [
            'id' => $client->id, 'email' => 'new@example.test', 'nombres' => 'Nuevo',
            'pay' => 2, 'contrato' => 1, 'hs_id' => 'hs-original',
        ]);
        $this->assertDatabaseCount('user_change_audits', 1);
        $this->assertCount(1, $this->hubspotRequests);
        $properties = json_decode((string) $this->hubspotRequests[0]['request']->getBody(), true)['properties'];
        $this->assertSame('new@example.test', $properties['email']);
        $this->assertSame('Nuevo', $properties['firstname']);
        $this->assertSame('P-123', $properties['numero_de_pasaporte']);
        $this->assertArrayNotHasKey('pay', $properties);
        $this->assertArrayNotHasKey('contrato', $properties);
    }

    public function test_missing_hubspot_link_is_reported_before_any_local_change(): void
    {
        $client = $this->client(['hs_id' => null]);
        $this->actingAs($this->administrator())->postJson(route('saveuserdata'), $this->payload($client))
            ->assertUnprocessable()->assertJsonValidationErrors('hubspot');

        $this->assertOriginalClient($client, null);
        $this->assertCount(0, $this->hubspotRequests);
    }

    public function test_a_network_failure_is_controlled_and_rolls_back_local_changes(): void
    {
        $client = $this->client();
        $this->hubspotResponds(0, new ConnectException('Connection timed out', new Request('PATCH', 'https://api.hubapi.com')));

        $this->actingAs($this->administrator())->postJson(route('saveuserdata'), $this->payload($client))
            ->assertStatus(502);

        $this->assertOriginalClient($client);
        $this->assertDatabaseCount('user_change_audits', 0);
        $this->assertCount(1, $this->hubspotRequests);
    }

    public function test_local_validation_and_client_permissions_still_prevent_outbound_updates(): void
    {
        $client = $this->client();
        $this->actingAs($this->administrator())->postJson(route('saveuserdata'), array_replace($this->payload($client), [
            'email' => 'invalid',
        ]))->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->actingAs($client)->postJson(route('saveuserdata'), $this->payload($client))->assertForbidden();
        $this->assertOriginalClient($client);
        $this->assertCount(0, $this->hubspotRequests);
    }

    private function hubspotResponds(int $status, array|string|ConnectException $body): void
    {
        $this->hubspotRequests = [];
        $handler = HandlerStack::create(new MockHandler([
            $body instanceof ConnectException ? $body
                : new Response($status, ['Content-Type' => 'application/json'], is_array($body) ? json_encode($body) : $body),
        ]));
        $handler->push(Middleware::history($this->hubspotRequests));
        $service = new class(new Client(['handler' => $handler])) extends HubspotService {
            public function __construct(Client $client)
            {
                $this->hubspot = Factory::createWithAccessToken('test-token', $client);
            }
        };
        $this->instance(HubspotService::class, $service);
    }

    private function client(array $attributes = []): User
    {
        return User::factory()->create(array_replace([
            'email' => 'original@example.test', 'nombres' => 'Original', 'apellidos' => 'Apellido',
            'passport' => 'P-123', 'phone' => '+580000000', 'hs_id' => 'hs-original', 'pay' => 1, 'contrato' => 0,
        ], $attributes));
    }

    private function administrator(): User
    {
        return User::factory()->create()->syncRoles(['Administrador']);
    }

    private function payload(User $client): array
    {
        return [
            'id' => $client->id, 'email' => 'new@example.test', 'nombres' => 'Nuevo', 'apellidos' => 'Apellido',
            'passport' => 'P-123', 'phone' => '+580000000', 'pay' => 2, 'contrato' => 1,
        ];
    }

    private function assertOriginalClient(User $client, ?string $hubspotId = 'hs-original'): void
    {
        $this->assertDatabaseHas('users', [
            'id' => $client->id, 'email' => 'original@example.test', 'nombres' => 'Original',
            'pay' => 1, 'contrato' => 0, 'hs_id' => $hubspotId,
        ]);
    }
}
