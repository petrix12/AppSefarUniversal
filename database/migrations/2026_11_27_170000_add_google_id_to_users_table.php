<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Keep this migration name so installations where ALTER users failed
        // can retry it without adding to the already-wide users row.
        if (Schema::hasTable('user_google_identities')) {
            return;
        }

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

    public function down(): void
    {
        Schema::dropIfExists('user_google_identities');
    }
};
