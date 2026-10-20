<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_blocked')->default(false)->index();
            $table->string('blocked_reason', 64)->nullable();
            $table->text('blocked_reason_other')->nullable();
            $table->timestamp('blocked_at')->nullable();
            $table->foreignId('blocked_by_user_id')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['blocked_by_user_id']);
            $table->dropIndex(['is_blocked']);
            $table->dropColumn([
                'is_blocked',
                'blocked_reason',
                'blocked_reason_other',
                'blocked_at',
                'blocked_by_user_id',
            ]);
        });
    }
};
