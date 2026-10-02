<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('negocios', function (Blueprint $table) {
            if (! Schema::hasColumn('negocios', 'teamleader_comparisons')) {
                $table->json('teamleader_comparisons')->nullable();
            }
            if (! Schema::hasColumn('negocios', 'teamleader_match_confidence')) {
                $table->unsignedTinyInteger('teamleader_match_confidence')->nullable();
            }
            if (! Schema::hasColumn('negocios', 'teamleader_match_reason')) {
                $table->string('teamleader_match_reason', 500)->nullable();
            }
            if (! Schema::hasColumn('negocios', 'teamleader_matched_at')) {
                $table->timestamp('teamleader_matched_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('negocios', function (Blueprint $table) {
            foreach (['teamleader_comparisons', 'teamleader_match_confidence', 'teamleader_match_reason', 'teamleader_matched_at'] as $column) {
                if (Schema::hasColumn('negocios', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
