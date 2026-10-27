<?php

namespace Tests\Feature;

use App\Mail\CargaSefar;
use App\Models\Agcliente;
use App\Models\Compras;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

// The legacy tree builder declares a named function inside storeNotCliente().
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class TreeUpdateMailTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Only these tables are needed; the full migration set also connects to Onidex.
        $this->artisan('migrate', ['--path' => [
            'database/migrations/2014_10_12_000000_create_users_table.php',
            'database/migrations/2021_03_08_173429_create_permission_tables.php',
            'database/migrations/2021_04_01_143943_create_t_files_table.php',
            'database/migrations/2023_05_03_215249_create_compras_table.php',
        ]])->assertExitCode(0);

        Schema::create('agclientes', function (Blueprint $table) {
            $table->id();
            foreach ([
                'IDCliente', 'Nombres', 'Apellidos', 'NPasaporte', 'PaisPasaporte',
                'NDocIdent', 'PaisDocIdent', 'Sexo', 'LugarNac', 'PaisNac',
                'LugarBtzo', 'PaisBtzo', 'LugarMatr', 'PaisMatr', 'LugarDef',
                'PaisDef', 'Familiaridad', 'NombresF', 'ApellidosF', 'ParentescoF',
                'NPasaporteF', 'FRegistro', 'Observaciones', 'Enlace', 'referido',
                'PNacimiento', 'LNacimiento', 'Familiares', 'FUpdate', 'Usuario',
            ] as $column) {
                $table->string($column)->nullable();
            }
            foreach ([
                'IDPersona', 'IDPadre', 'IDMadre', 'Generacion', 'idPadreNew',
                'idMadreNew', 'PersonaIDNew', 'migradoNuevoID', 'AnhoNac',
                'MesNac', 'DiaNac', 'AnhoBtzo', 'MesBtzo', 'DiaBtzo',
                'AnhoMatr', 'MesMatr', 'DiaMatr', 'AnhoDef', 'MesDef', 'DiaDef',
            ] as $column) {
                $table->integer($column)->nullable();
            }
            $table->timestamps();
        });

        Role::findOrCreate('Cliente', 'web');
        Role::findOrCreate('Genealogista', 'web');
        Permission::findOrCreate('crud.agclientes.index', 'web');
        Http::preventStrayRequests();
        Mail::fake();
    }

    #[DataProvider('treeAdditionCases')]
    public function test_tree_additions_only_send_mail_when_the_actor_is_a_client(
        string $route,
        bool $clientActor,
        string $lmdSource
    ): void {
        $client = User::factory()->create([
            'passport' => 'TREE-MAIL-001',
            'servicio' => $lmdSource === 'profile' ? 'Española LMD' : null,
        ]);
        $client->assignRole('Cliente');
        if ($lmdSource === 'purchase') {
            Compras::create([
                'id_user' => $client->id,
                'servicio_hs_id' => 'Española LMD',
                'descripcion' => 'Española LMD',
                'pagado' => 1,
                'monto' => 100,
            ]);
        }

        $actor = $clientActor ? $client : User::factory()->create()->syncRoles(['Genealogista']);
        $actor->givePermissionTo('crud.agclientes.index');
        $root = Agcliente::create([
            'IDCliente' => $client->passport,
            'IDPersona' => 1,
            'Nombres' => 'Cliente',
            'Apellidos' => 'Prueba',
            'PersonaIDNew' => 0,
        ]);

        $this->actingAs($actor)->from('/tree/'.$client->passport)
            ->post(route($route), $this->personInput($client, $root))
            ->assertRedirect('/tree/'.$client->passport);

        $this->assertDatabaseHas('agclientes', [
            'IDCliente' => $client->passport,
            'IDPersona' => 2,
            'Nombres' => 'Padre',
        ]);
        if ($route === 'agclientesnew.store') {
            $parent = Agcliente::where('IDPersona', 2)->sole();
            $this->assertSame($parent->id, $root->fresh()->idPadreNew);
        }

        if (! $clientActor) {
            Mail::assertNothingOutgoing();

            return;
        }

        Mail::assertSent(CargaSefar::class, function (CargaSefar $mail) use ($client, $route, $lmdSource): bool {
            return $mail->user->is($client)
                && $mail->hasTo('pedro.bazo@sefarvzla.com')
                && $mail->hasTo('20053496@bcc.hubspot.com')
                && $mail->hasTo('lguzmanposso@sefarvzla.com') === ($route === 'agclientesnew.store' && $lmdSource !== 'none');
        });
        Mail::assertSentCount(1);
    }

    public static function treeAdditionCases(): array
    {
        return [
            'internal user adding to a client tree' => ['agclientesnew.store', false, 'none'],
            'internal user adding to an LMD client tree' => ['agclientesnew.store', false, 'profile'],
            'client adding to own tree' => ['agclientesnew.store', true, 'none'],
            'LMD client from profile' => ['agclientesnew.store', true, 'profile'],
            'LMD client from purchase' => ['agclientesnew.store', true, 'purchase'],
            'internal user using the legacy form' => ['crud.agclientes.store', false, 'none'],
            'client using the legacy form' => ['crud.agclientes.store', true, 'none'],
        ];
    }

    public function test_an_internal_user_can_edit_a_tree_person_without_sending_mail(): void
    {
        $client = User::factory()->create(['passport' => 'TREE-MAIL-002'])->assignRole('Cliente');
        $actor = User::factory()->create()->syncRoles(['Genealogista']);
        $person = Agcliente::create([
            'IDCliente' => $client->passport,
            'IDPersona' => 2,
            'Nombres' => 'Anterior',
        ]);

        $this->actingAs($actor)->from('/tree/'.$client->passport)
            ->post(route('agclientesnew.update'), [
                ...$this->personInput($client, $person),
                'id' => $person->id,
            ])
            ->assertRedirect('/tree/'.$client->passport);

        $this->assertSame('Padre', $person->fresh()->Nombres);
        Mail::assertNothingOutgoing();
    }

    private function personInput(User $client, Agcliente $child): array
    {
        return [
            'IDCliente' => $client->passport,
            'IDPersona' => 2,
            'id_hijo' => $child->id,
            'Nombres' => 'Padre',
            'Apellidos' => 'Prueba',
            'Sexo' => 'M',
            'Origen' => 'arbol',
            'PaisNac' => 'Venezuela',
            'LugarNac' => 'Caracas',
            'Familiaridad' => 'Padre',
            'NPasaporte' => 'PARENT-001',
            'NDocIdent' => '123456',
            'LugarBtzo' => 'Caracas',
            'LugarMatr' => 'Caracas',
            'LugarDef' => 'Caracas',
            'NombresF' => 'Familiar',
            'ApellidosF' => 'Prueba',
            'ParentescoF' => 'Hijo',
            'NPasaporteF' => $client->passport,
        ];
    }
}
