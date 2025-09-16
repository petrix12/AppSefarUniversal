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
        Schema::create('cos_visitas', function (Blueprint $table) {
            $table->id();

            // Usuario que registra la visita
            $table->foreignId('user_id')
                  ->constrained('users')
                  ->onDelete('cascade');

            // Cliente relacionado (también de la tabla users)
            $table->foreignId('cliente_id')
                  ->nullable()
                  ->constrained('users')
                  ->onDelete('cascade');

            // Fecha de la visita
            $table->timestamp('fecha_visita')->useCurrent();

            // Timestamps Laravel (created_at, updated_at)
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cos_visitas');
    }
};
