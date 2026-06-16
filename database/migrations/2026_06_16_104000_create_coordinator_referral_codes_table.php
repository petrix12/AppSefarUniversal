<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('coordinator_referral_codes')) {
            Schema::create('coordinator_referral_codes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('coordinator_user_id')->index();
                $table->string('code')->unique();
                $table->boolean('active')->default(true)->index();
                $table->timestamp('last_sent_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('coordinator_referral_codes');
    }
};
