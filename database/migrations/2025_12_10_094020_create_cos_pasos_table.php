<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cos_pasos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fase_id')->constrained('cos_fases')->cascadeOnDelete();
            $table->integer('numero');

            $table->string('titulo')->nullable();        // nombre_largo
            $table->string('nombre_corto')->nullable();
            $table->longText('promesa')->nullable();
            $table->string('main_cta_texto')->nullable();
            $table->string('main_cta_url')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cos_pasos');
    }
};
