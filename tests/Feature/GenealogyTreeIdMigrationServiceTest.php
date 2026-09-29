<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserGenealogyTreeLink;
use App\Services\GenealogyTreeIdMigrationService;
use DomainException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GenealogyTreeIdMigrationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('agclientes', function (Blueprint $table) {
            $table->id();
            $table->string('IDCliente');
            $table->unsignedInteger('IDPersona');
            $table->timestamps();
        });

        Role::findOrCreate('Cliente');
    }

    public function test_it_migrates_a_complete_tree_and_its_local_references(): void
    {
        $oldId = 'PASSPORT-OLD';
        $newId = 'PASSPORT-NEW';
        $user = User::factory()->create(['passport' => $oldId]);
        $now = now();

        DB::table('agclientes')->insert([
            ['IDCliente' => $oldId, 'IDPersona' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['IDCliente' => $oldId, 'IDPersona' => 2, 'created_at' => $now, 'updated_at' => $now],
        ]);
        DB::table('files')->insert([
            'file' => 'documento.pdf',
            'location' => 'clientes/documento.pdf',
            'IDCliente' => $oldId,
            'IDPersona' => 1,
            'user_id' => $user->id,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('families')->insert([
            'IDCombinado' => $oldId.'-FAMILIAR-1',
            'IDCliente' => $oldId,
            'IDFamiliar' => 'FAMILIAR-1',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $groupId = DB::table('family_groups')->insertGetId([
            'name' => 'Grupo de prueba',
            'primary_id_cliente' => $oldId,
            'status' => 'calculated',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('family_group_members')->insert([
            'family_group_id' => $groupId,
            'user_id' => $user->id,
            'IDCliente' => $oldId,
            'source' => 'manual',
            'confidence' => 100,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        UserGenealogyTreeLink::create(['user_id' => $user->id, 'tree_id' => $oldId]);

        $service = app(GenealogyTreeIdMigrationService::class);
        $preview = $service->preview($oldId, $newId);

        $this->assertTrue($preview['can_migrate']);
        $this->assertSame(2, $preview['counts']['personas_arbol']);
        $this->assertSame(1, $preview['counts']['archivos']);

        $result = $service->migrate($oldId, $newId);

        $this->assertSame(2, $result['updated']['personas_arbol']);
        $this->assertSame(1, $result['updated']['usuarios']);
        $this->assertDatabaseMissing('agclientes', ['IDCliente' => $oldId]);
        $this->assertDatabaseHas('agclientes', ['IDCliente' => $newId, 'IDPersona' => 1]);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'passport' => $newId]);
        $this->assertDatabaseHas('files', ['IDCliente' => $newId]);
        $this->assertDatabaseHas('families', ['IDCliente' => $newId, 'IDCombinado' => $newId.'-FAMILIAR-1']);
        $this->assertDatabaseHas('family_groups', ['id' => $groupId, 'primary_id_cliente' => $newId]);
        $this->assertDatabaseHas('family_group_members', ['family_group_id' => $groupId, 'IDCliente' => $newId]);
        $this->assertDatabaseHas('user_genealogy_tree_links', ['user_id' => $user->id, 'tree_id' => $newId]);
        $this->assertDatabaseHas('user_change_audits', ['user_id' => $user->id]);
    }

    public function test_it_blocks_a_migration_when_the_destination_already_has_a_tree(): void
    {
        $oldId = 'PASSPORT-OLD';
        $newId = 'PASSPORT-NEW';
        $now = now();

        DB::table('agclientes')->insert([
            ['IDCliente' => $oldId, 'IDPersona' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['IDCliente' => $newId, 'IDPersona' => 1, 'created_at' => $now, 'updated_at' => $now],
        ]);

        $service = app(GenealogyTreeIdMigrationService::class);
        $preview = $service->preview($oldId, $newId);

        $this->assertFalse($preview['can_migrate']);
        $this->assertNotEmpty($preview['conflicts']);

        try {
            $service->migrate($oldId, $newId);
            $this->fail('La migración debió bloquearse por el árbol destino.');
        } catch (DomainException) {
            $this->assertDatabaseHas('agclientes', ['IDCliente' => $oldId, 'IDPersona' => 1]);
            $this->assertDatabaseHas('agclientes', ['IDCliente' => $newId, 'IDPersona' => 1]);
        }
    }
}
