<?php

namespace Tests\Feature;

use App\Http\Controllers\UserController;
use App\Models\TlContact;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TeamleaderIdentityLookupTest extends TestCase
{
    private object $lookupMigration;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--path' => [
            'database/migrations/2014_10_12_000000_create_users_table.php',
            'database/migrations/2021_03_08_173429_create_permission_tables.php',
            'database/migrations/2026_03_13_164351_create_tl_sync_tables.php',
        ]])->assertExitCode(0);
        if (! Schema::hasColumn('users', 'tl_id')) {
            Schema::table('users', fn (Blueprint $table) => $table->string('tl_id')->nullable());
        }
        foreach (['Administrador', 'Cliente', 'Ventas'] as $role) Role::findOrCreate($role, 'web');
        $this->lookupMigration = require database_path('migrations/2026_11_12_150000_add_normalized_identity_indexes_to_tl_contacts.php');
        $this->lookupMigration->up();
        Http::preventStrayRequests();
    }

    public function test_existing_and_new_contacts_match_by_case_and_spaces_without_changing_original_identity(): void
    {
        $this->contact('first', ' 00AbC ', ' Mixed@Example.TEST ');
        $this->contact('duplicate', '00abc', 'mixed@example.test');
        $this->contact('different', '0abc', 'other@example.test');
        $this->assertEqualsCanonicalizing(['first', 'duplicate'], TlContact::matchingIdentity('passport', [' 00ABC '])->pluck('id')->all());
        $this->assertEqualsCanonicalizing(['first', 'duplicate'], TlContact::matchingIdentity('email', ['MIXED@example.test', 'mixed@example.test'])->pluck('id')->all());
        $this->assertSame(' 00AbC ', TlContact::findOrFail('first')->passport);
        $this->assertSame(' Mixed@Example.TEST ', TlContact::findOrFail('first')->email);
        $this->assertArrayNotHasKey('passport_lookup', TlContact::findOrFail('first')->toArray());
    }

    public function test_index_values_follow_raw_import_updates_and_deletions(): void
    {
        $this->contact('updated', 'OLD', 'old@example.test');
        DB::table('tl_contacts')->where('id', 'updated')->update(['passport' => ' New ', 'email' => ' NEW@example.test ']);
        $this->assertSame(0, TlContact::matchingIdentity('passport', ['old'])->count());
        $this->assertSame('updated', TlContact::matchingIdentity('passport', ['new'])->sole()->id);
        $this->assertSame('updated', TlContact::matchingIdentity('email', ['new@example.test'])->sole()->id);
        DB::table('tl_contacts')->where('id', 'updated')->delete();
        $this->assertSame(0, TlContact::matchingIdentity('email', ['new@example.test'])->count());
    }

    public function test_migration_indexes_existing_data_and_rollback_preserves_matching_behavior(): void
    {
        $this->lookupMigration->down();
        $this->contact('legacy', ' ABC ', ' Legacy@Example.test ');
        $this->assertSame('legacy', TlContact::matchingIdentity('email', ['legacy@example.test'])->sole()->id);
        $this->lookupMigration->up();
        $this->lookupMigration->up(); // Safe if a deployment retries after partial DDL.
        $this->assertSame('legacy', TlContact::matchingIdentity('email', ['legacy@example.test'])->sole()->id);
        $this->assertTrue(Schema::hasIndex('tl_contacts', 'tl_contacts_email_lookup_index'));
        $this->assertTrue(Schema::hasIndex('tl_contacts', 'tl_contacts_passport_lookup_index'));
        $this->lookupMigration->down();
        $this->assertSame('legacy', TlContact::matchingIdentity('passport', ['abc'])->sole()->id);
        $this->assertSame(' Legacy@Example.test ', TlContact::findOrFail('legacy')->email);
    }

    public function test_the_database_query_plan_uses_each_identity_index(): void
    {
        $this->contact('indexed', 'P123', 'indexed@example.test');
        foreach (['email' => 'indexed@example.test', 'passport' => 'p123'] as $field => $value) {
            $query = TlContact::matchingIdentity($field, [$value]);
            $plan = DB::select('EXPLAIN QUERY PLAN '.$query->toSql(), $query->getBindings());
            $details = implode(' ', array_column($plan, 'detail'));
            $this->assertStringContainsString('SEARCH tl_contacts USING INDEX tl_contacts_'.$field.'_lookup_index', $details);
        }
    }

    public function test_blank_identities_do_not_match_blank_contacts_and_zero_passports_remain_valid(): void
    {
        $this->contact('blank', ' ', '');
        $this->contact('zero', '0', null);
        $this->assertSame(0, TlContact::matchingIdentity('email', ['', null, ' '])->count());
        $this->assertSame(0, TlContact::matchingIdentity('passport', [null, ' '])->count());
        $this->assertSame('zero', TlContact::matchingIdentity('passport', ['0'])->sole()->id);
    }

    public function test_cos_keeps_explicit_id_priority_and_all_duplicate_candidates(): void
    {
        $this->contact('explicit', '00ABC', 'person@example.test');
        $this->contact('same-passport', ' 00abc ', 'other@example.test');
        $this->contact('same-email', 'different', ' PERSON@example.test ');
        $user = User::factory()->create(['passport' => '00ABC', 'email' => 'person@example.test']);
        $user->forceFill(['tl_id' => 'explicit'])->save();
        $controller = (new \ReflectionClass(UserController::class))->newInstanceWithoutConstructor();
        $data = (new \ReflectionMethod(UserController::class, 'getTeamleaderMigrationData'))->invoke($controller, $user);
        $this->assertSame('explicit', $data['contact']->id);
        $this->assertContains('ID Teamleader guardado en el usuario', $data['match_labels']);
        $this->assertContains('Pasaporte', $data['match_labels']);
        $this->assertContains('Correo', $data['match_labels']);
        $this->assertEqualsCanonicalizing(['same-passport', 'same-email'], $data['other_contacts']->pluck('id')->all());
        Http::assertNothingSent();
    }

    public function test_indexed_lookup_does_not_bypass_restrictions_for_ordinary_staff(): void
    {
        Schema::create('user_access_blocks', fn (Blueprint $table) => $table->unsignedBigInteger('user_id')->primary());
        $this->contact('held', 'P123', 'held@example.test');
        $client = User::factory()->create();
        $client->forceFill(['tl_id' => 'held'])->save();
        DB::table('user_access_blocks')->insert(['user_id' => $client->id]);
        $staff = User::factory()->create()->syncRoles(['Ventas']);
        $this->actingAs($staff);
        $this->assertSame(0, TlContact::matchingIdentity('passport', ['p123'])->count());
        $admin = User::factory()->create()->syncRoles(['Administrador']);
        $this->actingAs($admin);
        $this->assertSame('held', TlContact::matchingIdentity('passport', ['p123'])->sole()->id);
    }

    private function contact(string $id, ?string $passport, ?string $email): void
    {
        TlContact::create(['id' => $id, 'passport' => $passport, 'email' => $email, 'raw_data' => []]);
    }
}
