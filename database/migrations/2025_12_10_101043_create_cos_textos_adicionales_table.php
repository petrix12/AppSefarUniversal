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
        Schema::create('cos_textos_adicionales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paso_id')->constrained('cos_pasos')->cascadeOnDelete();
            $table->string('nombre');
            $table->longText('texto');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cos_textos_adicionales');
    }
};
