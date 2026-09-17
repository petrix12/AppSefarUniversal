<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GenealogyTreeTimingMigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.connections.genealogy_timing_migration_test', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        config()->set('database.default', 'genealogy_timing_migration_test');
        DB::purge('genealogy_timing_migration_test');

        Schema::create('servicios', function (Blueprint $table) {
            $table->id();
            $table->string('id_hubspot');
            $table->string('nombre');
            $table->string('monday_registration_timing')->default('after_payment');
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('servicios');
        DB::disconnect('genealogy_timing_migration_test');

        parent::tearDown();
    }

    public function test_it_moves_only_genealogy_tree_processes_to_after_getinfo(): void
    {
        $cartaId = DB::table('servicios')->insertGetId([
            'id_hubspot' => 'Española - Carta de Naturaleza',
            'nombre' => 'Nacionalidad Española por Carta de Naturaleza',
            'monday_registration_timing' => 'after_payment',
        ]);
        $documentalId = DB::table('servicios')->insertGetId([
            'id_hubspot' => 'Gestión Documental',
            'nombre' => 'Gestión Documental',
            'monday_registration_timing' => 'after_payment',
        ]);

        $migration = require database_path('migrations/2026_09_17_130000_set_genealogy_tree_services_to_sync_after_getinfo.php');
        $migration->up();

        $this->assertSame('after_getinfo', DB::table('servicios')->where('id', $cartaId)->value('monday_registration_timing'));
        $this->assertSame('after_payment', DB::table('servicios')->where('id', $documentalId)->value('monday_registration_timing'));
    }
}
