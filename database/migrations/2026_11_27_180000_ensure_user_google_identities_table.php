<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Also runs on installations which already applied the original
        // migration and therefore will not run its corrected version again.
        if (! Schema::hasTable('user_google_identities')) {
            Schema::create('user_google_identities', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
                $subject = $table->string('google_id', 255)->unique();
                if (in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
                    $subject->charset('ascii')->collation('ascii_bin');
                }
                $table->timestamps();
            });
        }

        if (! Schema::hasColumn('users', 'google_id')) {
            return;
        }

        DB::table('users')->select(['id', 'google_id'])->whereNotNull('google_id')->where('google_id', '!=', '')
            ->orderBy('id')->chunkById(100, function ($users): void {
                foreach ($users as $user) {
                    $existing = DB::table('user_google_identities')->where('user_id', $user->id)->first();
                    if ($existing) {
                        if (! hash_equals($existing->google_id, $user->google_id)) {
                            throw new RuntimeException('La vinculación de Google del usuario '.$user->id.' necesita revisión; no se sobrescribió.');
                        }
                        continue;
                    }

                    // Unique constraints reject conflicting legacy identities.
                    // Do not silently reassign an identity to a different user.
                    DB::table('user_google_identities')->insert([
                        'user_id' => $user->id,
                        'google_id' => $user->google_id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });

        // Preserve the legacy column for recovery. The application uses only
        // the new table, so no ALTER of users is needed in either environment.
    }

    public function down(): void
    {
        // The original migration owns the table. Keep copied identities when
        // rolling back only this compatibility migration.
    }
};
