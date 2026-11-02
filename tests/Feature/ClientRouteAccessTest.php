<?php

namespace Tests\Feature;

use App\Http\Controllers\ClienteController;
use App\Http\Controllers\RegisterV2Controller;
use App\Http\Middleware\AuditRequests;
use App\Http\Middleware\RestrictClientRoutes;
use App\Models\Agcliente;
use App\Models\File;
use App\Models\User;
use App\Services\GenealogyService;
use App\Services\GenealogyTreeResolver;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Livewire\Component;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ClientRouteAccessTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array', 'session.driver' => 'array',
        ]);
        DB::purge('sqlite');
        Cache::flush();
        Http::preventStrayRequests();
        Mail::fake();
        $this->withoutMiddleware(AuditRequests::class);

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('passport')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->boolean('contrato')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });
        require_once database_path('migrations/2021_03_08_173429_create_permission_tables.php');
        (new \CreatePermissionTables())->up();
        (new RoleSeeder())->run();

        Schema::create('agclientes', function (Blueprint $table): void {
            $table->id();
            $table->string('IDCliente');
            $table->integer('IDPersona')->nullable();
            foreach (['Nombres', 'Apellidos', 'NPasaporte', 'PaisPasaporte', 'NDocIdent',
                'PaisDocIdent', 'Observaciones'] as $field) {
                $table->string($field)->nullable();
            }
            foreach (['Nac', 'Btzo', 'Matr', 'Def'] as $suffix) {
                foreach (['Anho', 'Mes', 'Dia', 'Lugar', 'Pais'] as $prefix) {
                    $table->string($prefix.$suffix)->nullable();
                }
            }
            $table->timestamps();
        });
        Schema::create('files', function (Blueprint $table): void {
            $table->id();
            $table->string('IDCliente');
            $table->unsignedBigInteger('IDPersonaNew')->nullable();
            $table->integer('IDPersona')->nullable();
            $table->boolean('MigradoNuevoID')->default(false);
            $table->unsignedBigInteger('user_id')->nullable();
            foreach (['file', 'location', 'tipo', 'notas', 'propietario'] as $field) {
                $table->string($field)->nullable();
            }
            $table->timestamps();
        });
        Schema::create('t_files', function (Blueprint $table): void {
            $table->id();
            $table->string('tipo');
        });
        $this->mock(GenealogyTreeResolver::class)->shouldReceive('resolveFor')->andReturn('OWN-TREE');
    }

    public function test_all_internal_routes_deny_clients_even_with_every_permission(): void
    {
        $client = $this->actor();
        $client->assignRole('Administrador');
        $this->actingAs($client);
        Artisan::shouldReceive('call')->never();
        // A permissive/custom Gate must not defeat the explicit client boundary.
        Gate::before(fn () => true);

        $denied = 0;
        foreach (Route::getRoutes() as $route) {
            if (! in_array(RestrictClientRoutes::class, $route->gatherMiddleware(), true)) {
                continue;
            }
            if (in_array($route->getName(), RestrictClientRoutes::ALLOWED_ROUTES, true)) {
                continue;
            }

            Cache::flush();
            $uri = '/'.preg_replace('/\{[^}]+\}/', '999999', $route->uri());
            $response = $this->json($route->methods()[0], $uri);
            $this->assertSame(403, $response->status(), $route->methods()[0].' '.$uri);
            $denied++;
        }

        $this->assertGreaterThan(350, $denied);
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    #[DataProvider('internalRoles')]
    public function test_the_new_restriction_does_not_change_other_roles(string $role): void
    {
        Route::middleware(['web', 'auth', RestrictClientRoutes::class])
            ->get('/_test/internal-route', fn () => response()->noContent());
        $this->actingAs($this->actor($role))->get('/_test/internal-route')->assertNoContent();
    }

    public static function internalRoles(): array
    {
        return array_map(fn ($role) => [$role], [
            'Administrador', 'Admin', 'Genealogista', 'Documentalista', 'Produccion', 'Ventas',
            'Analista', 'ATC', 'Coord. de Nacionalidad y Genealogía', 'Proveedor',
            'Traviesoevans', 'Vargassequera', 'BadellLaw', 'P&V-Abogados', 'Mujica-Coto',
            'German-Fleitas', 'Soma-Consultores', 'MG-Tours',
        ]);
    }

    #[DataProvider('portalScreens')]
    public function test_client_portal_screens_still_reach_their_controller(string $uri, string $action): void
    {
        $this->partialMock(ClienteController::class)->shouldReceive($action)->once()
            ->andReturn(response()->noContent());
        $this->actingAs($this->actor())->get($uri)->assertNoContent();
    }

    public static function portalScreens(): array
    {
        return [
            ['/tree', 'tree'], ['/status', 'status'], ['/pay', 'pay'], ['/getinfo', 'getinfo'],
            ['/pagospendientes', 'pagospendientes'], ['/contrato', 'contrato'],
            ['/vinculaciones', 'vinculaciones'], ['/hermanoscliente', 'hermanoscliente'],
        ];
    }

    public function test_register_v2_stays_public_for_visitors(): void
    {
        $controller = $this->partialMock(RegisterV2Controller::class);
        $controller->shouldReceive('index')->once()->andReturn(response()->noContent());
        $controller->shouldReceive('store')->once()->andReturn(response()->noContent());
        $this->get('/registerv2')->assertNoContent();
        $this->post('/registerv2')->assertNoContent();
    }

    public function test_a_client_with_an_extra_admin_role_still_gets_the_client_home(): void
    {
        $client = $this->actor();
        $client->assignRole('Administrador');
        $this->partialMock(\App\Http\Controllers\Controller::class)
            ->shouldAllowMockingProtectedMethods()
            ->shouldReceive('clientOnboardingRoute')->once()->andReturn('clientes.pay');

        $this->actingAs($client)->get('/')->assertRedirect(route('clientes.pay'));
    }

    public function test_internal_tree_file_requests_keep_their_existing_permissions(): void
    {
        $this->partialMock(\App\Http\Controllers\AgClienteNewController::class)
            ->shouldReceive('getClientFiles')->once()->andReturn(response()->noContent());
        $this->actingAs($this->actor('Genealogista'))->postJson('/getclientfiles')->assertNoContent();
        $this->actingAs($this->actor('Documentalista'))->postJson('/getclientfiles')->assertForbidden();
    }

    public function test_internal_livewire_snapshots_cannot_bypass_client_route_restrictions(): void
    {
        Livewire::component('client-route-probe', ClientRouteProbe::class);
        ClientRouteProbe::$actionRan = false;
        Route::middleware(['web', 'auth', RestrictClientRoutes::class])
            ->get('/_test/internal-component', fn () => Blade::render('<livewire:client-route-probe />'));

        $html = $this->actingAs($this->actor('Administrador'))->get('/_test/internal-component')
            ->assertOk()->getContent();
        $snapshot = $this->snapshotFrom($html);

        $this->actingAs($this->actor())->postJson('/livewire/update', [
            'components' => [['snapshot' => $snapshot, 'updates' => [],
                'calls' => [['path' => '', 'method' => 'touch', 'params' => []]]]],
        ], ['X-Livewire' => 'true'])->assertForbidden();
        $this->assertFalse(ClientRouteProbe::$actionRan);
    }

    public function test_livewire_on_an_allowed_client_screen_keeps_working(): void
    {
        Livewire::component('client-route-probe', ClientRouteProbe::class);
        ClientRouteProbe::$actionRan = false;
        $this->partialMock(ClienteController::class)->shouldReceive('tree')->once()
            ->andReturnUsing(fn () => response(Blade::render('<livewire:client-route-probe />')));
        $html = $this->actingAs($this->actor())->get('/tree')->assertOk()->getContent();

        $this->postJson('/livewire/update', [
            'components' => [['snapshot' => $this->snapshotFrom($html), 'updates' => [],
                'calls' => [['path' => '', 'method' => 'touch', 'params' => []]]]],
        ], ['X-Livewire' => 'true'])->assertOk();
        $this->assertTrue(ClientRouteProbe::$actionRan);
    }

    public function test_client_tree_requests_cannot_read_or_change_another_tree(): void
    {
        $client = $this->actor();
        $own = $this->person('OWN-TREE');
        $other = $this->person('OTHER-TREE');
        $file = $this->document($other);
        Storage::shouldReceive('disk')->never();
        $this->actingAs($client);

        foreach ([
            ['/agclientesnew', ['IDCliente' => 'OTHER-TREE', 'id_hijo' => $own->id]],
            ['/agclientesnew', ['IDCliente' => 'OWN-TREE', 'id_hijo' => $other->id]],
            ['/agclientesupdate', ['id' => $other->id, 'Nombres' => 'Changed']],
            ['/getclientfiles', ['clienteid' => 'OTHER-TREE', 'familiarid' => $other->id]],
            ['/getclientfiles', ['clienteid' => 'OWN-TREE', 'familiarid' => $other->id]],
            ['/updatefiletype', ['id' => $file->id, 'tipo' => 'Changed']],
            ['/storefile', ['IDCliente' => 'OTHER-TREE', 'IDPersonaNew' => $other->id]],
            ['/storefile', ['IDCliente' => 'OWN-TREE', 'IDPersonaNew' => $other->id]],
            ['/openfile', ['path' => $file->location.'/'.$file->file]],
            ['/openfile', ['path' => 'private/secrets.pdf']],
        ] as [$uri, $body]) {
            $this->postJson($uri, $body)->assertForbidden();
        }
        $this->assertSame('Original', $other->fresh()->Nombres);
        $this->assertSame('Original', $file->fresh()->tipo);
        $this->assertSame(2, Agcliente::count());
        Mail::assertNothingSent();
    }

    public function test_a_client_can_read_and_classify_only_documents_from_their_tree(): void
    {
        $this->actingAs($this->actor());
        $own = $this->person('OWN-TREE');
        $file = $this->document($own);
        // A mismatched imported association must not expose a different tree's file.
        $foreign = $this->document($own, 'OTHER-TREE');

        $this->postJson('/getclientfiles', ['clienteid' => 'OWN-TREE', 'familiarid' => $own->id])
            ->assertOk()->assertJsonCount(1, 'archivos')->assertJsonPath('archivos.0.id', $file->id);
        $this->postJson('/updatefiletype', ['id' => $file->id, 'tipo' => 'Pasaporte'])->assertOk();
        $this->assertSame('Pasaporte', $file->fresh()->tipo);
        $this->assertSame('Original', $foreign->fresh()->tipo);
    }

    public function test_a_client_can_sign_a_url_for_their_own_registered_file(): void
    {
        $this->actingAs($this->actor());
        $file = $this->document($this->person('OWN-TREE'));
        $path = $file->location.'/'.$file->file;
        Storage::shouldReceive('disk')->once()->with('s3')->andReturnSelf();
        Storage::shouldReceive('temporaryUrl')->once()->with($path, \Mockery::any())
            ->andReturn('https://example.test/signed-document');
        $this->postJson('/openfile', ['path' => $path])->assertOk()
            ->assertJsonPath('url', 'https://example.test/signed-document');
    }

    public function test_a_client_can_upload_a_document_to_their_own_tree(): void
    {
        $client = $this->actor();
        $own = $this->person('OWN-TREE');
        Storage::fake('s3');
        $this->actingAs($client)->post('/storefile', [
            'IDCliente' => 'OWN-TREE', 'IDPersonaNew' => $own->id, 'tipo' => 'Pasaporte',
            'archivo' => UploadedFile::fake()->create('passport.pdf', 5, 'application/pdf'),
        ])->assertRedirect();
        Storage::disk('s3')->assertExists('public/doc/POWN-TREE/passport.pdf');
        $this->assertSame($client->id, File::firstOrFail()->user_id);
    }

    public function test_a_client_can_update_a_person_in_their_own_tree(): void
    {
        $own = $this->person('OWN-TREE');
        $this->mock(GenealogyService::class)->shouldReceive('forgetProcessedTree')->once()->with('OWN-TREE');
        $this->actingAs($this->actor())->post('/agclientesupdate', [
            'id' => $own->id, 'Nombres' => 'Updated', 'FechaNac' => '1885',
        ])->assertRedirect();
        $this->assertSame('Updated', $own->fresh()->Nombres);
        $this->assertSame('1885', $own->fresh()->AnhoNac);
    }

    private function actor(string $role = 'Cliente'): User
    {
        Role::findOrCreate($role, 'web');
        $user = User::withoutEvents(fn () => User::create([
            'name' => $role, 'email' => uniqid().'@example.test', 'password' => 'test-password',
            'passport' => 'OWN-TREE', 'email_verified_at' => now(), 'contrato' => true,
        ]));
        $user->assignRole($role);

        return $user;
    }

    private function person(string $tree): Agcliente
    {
        return Agcliente::create(['IDCliente' => $tree, 'IDPersona' => 1, 'Nombres' => 'Original']);
    }

    private function document(Agcliente $person, ?string $tree = null): File
    {
        $tree ??= $person->IDCliente;
        return File::create([
            'IDCliente' => $tree, 'IDPersonaNew' => $person->id, 'IDPersona' => 1,
            'file' => 'document.pdf', 'location' => 'public/doc/P'.$tree, 'tipo' => 'Original',
        ]);
    }

    private function snapshotFrom(string $html): string
    {
        $this->assertSame(1, preg_match('/wire:snapshot="([^"]+)"/', $html, $matches));
        return html_entity_decode($matches[1], ENT_QUOTES);
    }
}

class ClientRouteProbe extends Component
{
    public static bool $actionRan = false;

    public function touch(): void
    {
        self::$actionRan = true;
    }

    public function render()
    {
        return '<div>Route access probe</div>';
    }
}
