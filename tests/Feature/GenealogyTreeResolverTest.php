<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\GenealogyTreeResolver;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GenealogyTreeResolverTest extends TestCase
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

    public function test_it_prefers_the_passport_tree_when_both_identifiers_exist(): void
    {
        $user = User::factory()->create([
            'passport' => 'PASSPORT-001',
            'genealogy_tree_id' => 'SECONDARY-001',
        ]);
        $this->insertTree('PASSPORT-001');
        $this->insertTree('SECONDARY-001');

        $this->assertSame('PASSPORT-001', app(GenealogyTreeResolver::class)->resolveFor($user));
    }

    public function test_it_uses_the_secondary_identifier_when_the_passport_tree_is_missing(): void
    {
        $user = User::factory()->create([
            'passport' => 'PASSPORT-ABSENT',
            'genealogy_tree_id' => 'SECONDARY-002',
        ]);
        $this->insertTree('SECONDARY-002');

        $this->assertSame('SECONDARY-002', app(GenealogyTreeResolver::class)->resolveFor($user));
    }

    public function test_it_does_not_treat_an_incomplete_tree_as_a_valid_association(): void
    {
        $user = User::factory()->create([
            'passport' => 'PASSPORT-ABSENT',
            'genealogy_tree_id' => 'INCOMPLETE-001',
        ]);

        DB::table('agclientes')->insert([
            'IDCliente' => 'INCOMPLETE-001',
            'IDPersona' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertNull(app(GenealogyTreeResolver::class)->resolveFor($user));
    }

    private function insertTree(string $treeId): void
    {
        DB::table('agclientes')->insert([
            'IDCliente' => $treeId,
            'IDPersona' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
