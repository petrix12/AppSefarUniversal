<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('google_review_completed_at')->nullable();
        });

        // The campaign starts with this deployment. Existing accounts are not
        // asked for a review; only registrations from the latest month remain
        // pending and can become eligible after completing their tree.
        DB::table('users')
            ->where('created_at', '<', now()->subMonth())
            ->update(['google_review_completed_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('google_review_completed_at');
        });
    }
};
