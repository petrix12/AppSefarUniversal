<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GoogleAccountStorageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->timestamps();
        });
    }

    private function originalMigration(): \Illuminate\Database\Migrations\Migration
    {
        return require database_path('migrations/2026_11_27_170000_add_google_id_to_users_table.php');
    }

    private function compatibilityMigration(): \Illuminate\Database\Migrations\Migration
    {
        return require database_path('migrations/2026_11_27_180000_ensure_user_google_identities_table.php');
    }

    public function test_retrying_the_failed_migration_leaves_users_unchanged_and_keeps_existing_links(): void
    {
        $columns = Schema::getColumnListing('users');
        DB::table('users')->insert(['id' => 1, 'email' => 'client@example.test']);

        $this->originalMigration()->up();
        DB::table('user_google_identities')->insert(['user_id' => 1, 'google_id' => '123456789']);
        $this->originalMigration()->up();
        $this->compatibilityMigration()->up();

        $this->assertSame($columns, Schema::getColumnListing('users'));
        $this->assertDatabaseHas('users', ['id' => 1, 'email' => 'client@example.test']);
        $this->assertDatabaseHas('user_google_identities', ['user_id' => 1, 'google_id' => '123456789']);
        $this->assertDatabaseCount('user_google_identities', 1);
    }

    public function test_an_already_applied_legacy_migration_is_copied_without_erasing_its_data_and_can_be_retried(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->string('google_id')->nullable()->unique());
        DB::table('users')->insert([
            ['id' => 1, 'email' => 'client@example.test', 'google_id' => str_repeat('9', 255)],
            ['id' => 2, 'email' => 'unlinked@example.test', 'google_id' => null],
        ]);

        $this->compatibilityMigration()->up();
        $this->compatibilityMigration()->up();

        $this->assertDatabaseHas('user_google_identities', ['user_id' => 1, 'google_id' => str_repeat('9', 255)]);
        $this->assertDatabaseCount('user_google_identities', 1);
        $this->assertDatabaseHas('users', ['id' => 1, 'google_id' => str_repeat('9', 255)]);
        $this->assertDatabaseCount('users', 2);
        $this->compatibilityMigration()->down();
        $this->assertDatabaseCount('user_google_identities', 1);
    }

    public function test_conflicting_legacy_data_does_not_overwrite_the_current_identity(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->string('google_id')->nullable());
        DB::table('users')->insert(['id' => 1, 'email' => 'client@example.test', 'google_id' => 'old-identity']);
        $this->originalMigration()->up();
        DB::table('user_google_identities')->insert(['user_id' => 1, 'google_id' => 'current-identity']);

        try {
            $this->compatibilityMigration()->up();
            $this->fail('Conflicting identities must be reviewed instead of overwritten.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('no se sobrescribió', $exception->getMessage());
        }

        $this->assertDatabaseHas('user_google_identities', ['user_id' => 1, 'google_id' => 'current-identity']);
        $this->assertDatabaseHas('users', ['id' => 1, 'google_id' => 'old-identity']);
    }

    #[DataProvider('duplicateIdentities')]
    public function test_each_user_and_google_identity_can_have_only_one_link(array $duplicate): void
    {
        DB::table('users')->insert([
            ['id' => 1, 'email' => 'first@example.test'],
            ['id' => 2, 'email' => 'second@example.test'],
        ]);
        $this->originalMigration()->up();
        DB::table('user_google_identities')->insert(['user_id' => 1, 'google_id' => 'identity-a']);

        $this->expectException(QueryException::class);
        DB::table('user_google_identities')->insert($duplicate);
    }

    public static function duplicateIdentities(): array
    {
        return [
            'same user' => [['user_id' => 1, 'google_id' => 'identity-b']],
            'same Google account' => [['user_id' => 2, 'google_id' => 'identity-a']],
        ];
    }

    public function test_deleting_a_user_removes_only_its_google_link(): void
    {
        DB::table('users')->insert([
            ['id' => 1, 'email' => 'first@example.test'],
            ['id' => 2, 'email' => 'second@example.test'],
        ]);
        $this->originalMigration()->up();
        DB::table('user_google_identities')->insert([
            ['user_id' => 1, 'google_id' => 'identity-a'],
            ['user_id' => 2, 'google_id' => 'identity-b'],
        ]);

        DB::table('users')->where('id', 1)->delete();

        $this->assertDatabaseCount('user_google_identities', 1);
        $this->assertDatabaseHas('user_google_identities', ['user_id' => 2, 'google_id' => 'identity-b']);
    }
}
