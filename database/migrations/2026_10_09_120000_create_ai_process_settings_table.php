<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_process_settings', function (Blueprint $table) {
            $table->id();
            $table->string('process', 80)->unique();
            $table->string('model', 180);
            $table->json('fallback_models')->nullable();
            $table->unsignedSmallInteger('timeout_seconds')->default(60);
            $table->unsignedInteger('max_tokens')->default(700);
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_process_settings');
    }
};
