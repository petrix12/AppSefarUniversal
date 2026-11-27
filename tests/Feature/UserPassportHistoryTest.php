<?php

namespace Tests\Feature;

use App\Livewire\Crud\UsersTable;
use App\Models\User;
use App\Models\UserChangeAudit;
use App\Models\UserPassportHistory;
use App\Services\GenealogyTreeResolver;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Cache, DB, Http, Mail, Schema};
use Laravel\Jetstream\Http\Livewire\UpdateProfileInformationForm;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserPassportHistoryTest extends TestCase
{
    use \Tests\Concerns\RegistrationSchema;

    private const MIGRATION = 'database/migrations/2026_11_27_120000_create_user_passport_histories_table.php';

    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array', 'session.driver' => 'array']);
        Http::preventStrayRequests();
        Mail::fake();
        $this->createRegistrationSchema();
        Role::findOrCreate('Administrador');
        $this->artisan('migrate', ['--path' => [
            'database/migrations/2026_09_28_120000_add_genealogy_tree_id_to_users_table.php',
            'database/migrations/2026_09_29_090000_create_user_change_audits_table.php',
            self::MIGRATION,
        ]])->assertSuccessful();
    }

    public function test_a_client_can_update_their_passport_and_the_previous_number_remains_searchable_data(): void
    {
        $client = User::factory()->create(['passport' => 'OLD-123456']);
        $this->actingAs($client);

        Livewire::test(UpdateProfileInformationForm::class)
            ->set('state.passport', ' NEW-123456 ')
            ->call('updateProfileInformation')->assertHasNoErrors();

        $this->assertSame('NEW-123456', $client->fresh()->passport);
        $history = $client->passportHistories()->sole();
        $this->assertSame('OLD-123456', $history->passport);
        $this->assertSame('NEW-123456', $history->replacement_passport);
        $this->assertSame($client->id, $history->changed_by_user_id);
        $client->update(['name' => 'Nombre actualizado']);
        $this->assertDatabaseCount('user_passport_histories', 1);
        Http::assertNothingSent();
    }

    public function test_invalid_or_duplicate_passports_do_not_change_the_profile_or_create_history(): void
    {
        $client = User::factory()->create(['passport' => 'OWN-123456']);
        $other = User::factory()->create(['passport' => 'TAKEN-123456']);
        $this->actingAs($client);

        foreach (['TAKEN-123456', '', ['invalid']] as $passport) {
            Livewire::test(UpdateProfileInformationForm::class)
                ->set('state.passport', $passport)
                ->call('updateProfileInformation')->assertHasErrors('passport');
        }
        $this->assertSame('OWN-123456', $client->fresh()->passport);
        $this->assertSame('TAKEN-123456', $other->fresh()->passport);
        $this->assertDatabaseCount('user_passport_histories', 0);
    }

    public function test_repeated_changes_and_stale_models_preserve_each_number_actually_replaced(): void
    {
        $client = User::factory()->create(['passport' => 'FIRST-123']);
        $stale = $client->fresh();
        $client->update(['passport' => 'SECOND-123']);
        $stale->update(['passport' => 'THIRD-123']);
        $client->refresh()->update(['passport' => 'FIRST-123']);

        $this->assertSame(['THIRD-123', 'SECOND-123', 'FIRST-123'], $client->passportHistories()->pluck('passport')->all());
        $this->assertSame('FIRST-123', $client->fresh()->passport);
    }

    public function test_initial_assignment_and_formatting_changes_do_not_create_false_history(): void
    {
        $client = User::factory()->create(['passport' => null]);
        $client->update(['passport' => 'TEST-123']);
        $client->update(['passport' => ' test-123 ']);
        $client->update(['name' => 'Otro nombre']);
        $this->assertDatabaseCount('user_passport_histories', 0);
    }

    public function test_a_history_failure_rolls_back_the_passport_update(): void
    {
        $client = User::factory()->create(['passport' => 'BEFORE-123']);
        UserPassportHistory::creating(fn () => throw new \RuntimeException('History unavailable'));
        try {
            $client->update(['passport' => 'AFTER-123']);
            $this->fail('Expected history persistence to fail.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('History unavailable', $exception->getMessage());
        } finally {
            UserPassportHistory::flushEventListeners();
        }
        $this->assertSame('BEFORE-123', $client->fresh()->passport);
        $this->assertDatabaseCount('user_passport_histories', 0);
        $this->assertDatabaseCount('user_change_audits', 0);
    }

    public function test_a_quiet_update_still_preserves_the_previous_passport(): void
    {
        $client = User::factory()->create(['passport' => 'BEFORE-123']);
        $client->forceFill(['passport' => 'AFTER-123'])->saveQuietly();
        $this->assertSame('BEFORE-123', $client->passportHistories()->sole()->passport);
    }

    public function test_the_migration_recovers_known_passports_from_existing_audits(): void
    {
        Schema::drop('user_passport_histories');
        $client = User::factory()->create(['passport' => 'CURRENT-123']);
        UserChangeAudit::forceCreate([
            'user_id' => $client->id, 'changed_by_user_id' => $client->id,
            'old_values' => ['passport' => 'ARCHIVED-123'], 'new_values' => ['passport' => 'CURRENT-123'],
            'source' => 'profile.update', 'created_at' => '2026-01-10 12:00:00',
        ]);
        UserChangeAudit::create([
            'user_id' => $client->id, 'old_values' => ['name' => 'Anterior'], 'new_values' => ['name' => 'Actual'],
        ]);
        (require base_path(self::MIGRATION))->up();
        $history = $client->passportHistories()->sole();
        $this->assertSame('ARCHIVED-123', $history->passport);
        $this->assertSame('CURRENT-123', $history->replacement_passport);
        $this->assertSame('2026-01-10 12:00:00', $history->created_at->format('Y-m-d H:i:s'));
    }

    public function test_updating_a_passport_preserves_access_to_the_existing_genealogy_tree(): void
    {
        $client = User::factory()->create(['passport' => 'TREE-123']);
        DB::table('agclientes')->insert(['IDCliente' => 'TREE-123', 'IDPersona' => 1]);
        $client->update(['passport' => 'RENEWED-123']);
        $this->assertSame('TREE-123', app(GenealogyTreeResolver::class)->resolveFor($client->fresh()));
    }

    public function test_the_users_table_finds_previous_passports_combined_with_names_without_including_internal_accounts(): void
    {
        $this->directorySchema();
        $admin = User::factory()->create()->syncRoles(['Administrador']);
        $client = User::factory()->create(['name' => 'María Ejemplo', 'passport' => 'ARCHIVE-123']);
        $client->update(['passport' => 'CURRENT-123']);
        $internal = User::factory()->create(['name' => 'María Interna', 'passport' => 'ARCHIVE-456'])->syncRoles(['Administrador']);
        $internal->update(['passport' => 'CURRENT-456']);
        User::factory()->create(['name' => 'Cliente sin coincidencia', 'passport' => 'OTHER-123']);

        Livewire::actingAs($admin)->test(UsersTable::class)
            ->set('search', 'María ARCHIVE-')
            ->assertViewHas('users', fn ($users) => $users->pluck('id')->all() === [$client->id])
            ->assertSee('ARCHIVE-123')
            ->set('search', 'María CURRENT-123')
            ->assertViewHas('users', fn ($users) => $users->pluck('id')->all() === [$client->id]);
    }

    private function directorySchema(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach (['is_superadmin', 'owner_id', 'estado_vendedor', 'fecha_activacion_proveedor', 'email_2',
                'pais_de_residencia', 'city', 'address', 'metodo_pago_preferido', 'motivo_coordinador', 'tiene_contactos_sociales'] as $column) {
                if (! Schema::hasColumn('users', $column)) {
                    $table->string($column)->nullable();
                }
            }
        });
        Schema::create('client_chat_messages', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('client_id'); $table->text('message'); $table->timestamps();
        });
        Cache::put('cos.directory.status-options.v1', [], 60);
    }
}
