<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('email_suppressions')) {
            Schema::create('email_suppressions', function (Blueprint $table) {
                $table->id();
                $table->string('email', 254)->unique();
                $table->string('reason', 40);
                $table->string('source_reference')->nullable();
                $table->timestamp('blocked_at');
                $table->timestamp('cleared_at')->nullable();
                $table->unsignedBigInteger('cleared_by')->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('registration_followup_states')) {
            Schema::create('registration_followup_states', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
                $table->string('stop_reason', 40)->nullable();
                $table->timestamp('stopped_at')->nullable();
                $table->timestamp('responded_at')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
            });
        }
        if (Schema::hasTable('registration_followups') && ! Schema::hasColumn('registration_followups', 'status')) {
            Schema::table('registration_followups', function (Blueprint $table) {
                $table->string('status', 20)->default('sent');
            });
        }
        if (Schema::hasTable('registration_followups')) {
            DB::table('registration_followups')->select('user_id')->whereNotNull('sent_at')
                ->groupBy('user_id')->havingRaw('COUNT(*) >= 3')->orderBy('user_id')->chunk(500, function ($users) {
                    foreach ($users as $user) {
                        DB::table('registration_followup_states')->insertOrIgnore([
                            'user_id' => $user->user_id, 'stop_reason' => 'limit_reached', 'stopped_at' => now(),
                            'created_at' => now(), 'updated_at' => now(),
                        ]);
                    }
                });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('registration_followups', 'status')) Schema::table('registration_followups', fn (Blueprint $table) => $table->dropColumn('status'));
        Schema::dropIfExists('registration_followup_states');
        Schema::dropIfExists('email_suppressions');
    }
};
