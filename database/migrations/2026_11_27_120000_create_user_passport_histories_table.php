<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_passport_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('passport')->index();
            $table->string('replacement_passport')->nullable();
            $table->foreignId('changed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });

        if (! Schema::hasTable('user_change_audits')) {
            return;
        }

        DB::table('user_change_audits')->orderBy('id')->chunkById(500, function ($audits) {
            $rows = [];
            foreach ($audits as $audit) {
                $old = json_decode($audit->old_values, true);
                $new = json_decode($audit->new_values, true);
                if (! is_array($old) || ! is_array($new) || ! array_key_exists('passport', $new)) {
                    continue;
                }
                $passport = trim((string) ($old['passport'] ?? ''));
                $replacement = trim((string) ($new['passport'] ?? ''));
                if ($passport === '' || strcasecmp($passport, $replacement) === 0) {
                    continue;
                }
                $rows[] = [
                    'user_id' => $audit->user_id,
                    'passport' => $passport,
                    'replacement_passport' => $replacement !== '' ? $replacement : null,
                    'changed_by_user_id' => $audit->changed_by_user_id,
                    'source' => $audit->source,
                    'created_at' => $audit->created_at,
                    'updated_at' => $audit->created_at,
                ];
            }
            if ($rows !== []) {
                DB::table('user_passport_histories')->insert($rows);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_passport_histories');
    }
};
