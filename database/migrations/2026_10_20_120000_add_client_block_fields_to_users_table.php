<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('user_access_blocks')) {
            Schema::create('user_access_blocks', function (Blueprint $table) {
                $table->foreignId('user_id')->primary()->constrained('users')->cascadeOnDelete();
                $table->string('reason', 64);
                $table->text('reason_other')->nullable();
                $table->timestamp('blocked_at')->nullable();
                $table->foreignId('blocked_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            });
        }

        // A previous attempt may have added this column before hitting MySQL's row-size limit.
        if (Schema::hasColumn('users', 'is_blocked')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('is_blocked');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_access_blocks');
    }
};
