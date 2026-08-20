<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            ! Schema::hasTable('strategic_suggestion_attachments')
            || ! Schema::hasTable('strategic_suggestion_replies')
            || $this->hasConstraint('strategic_suggestion_attachments_reply_id_foreign')
        ) {
            return;
        }

        Schema::table('strategic_suggestion_attachments', function (Blueprint $table) {
            $table->foreign('reply_id')
                ->references('id')
                ->on('strategic_suggestion_replies')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        if (
            Schema::hasTable('strategic_suggestion_attachments')
            && $this->hasConstraint('strategic_suggestion_attachments_reply_id_foreign')
        ) {
            Schema::table('strategic_suggestion_attachments', function (Blueprint $table) {
                $table->dropForeign('strategic_suggestion_attachments_reply_id_foreign');
            });
        }
    }

    private function hasConstraint(string $constraint): bool
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver !== 'mysql') {
            return false;
        }

        return DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', DB::raw('DATABASE()'))
            ->where('TABLE_NAME', 'strategic_suggestion_attachments')
            ->where('CONSTRAINT_NAME', $constraint)
            ->exists();
    }
};
