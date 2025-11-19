<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Creamos la tabla 'whatsapp_bot_u_r_l'
        Schema::create('whatsapp_bot_u_r_l', function (Blueprint $table) {
            // Un campo de clave primaria autoincremental
            $table->id();

            // El único campo que necesitas: 'url' de tipo string (VARCHAR)
            // Se puede hacer nullable (opcional) o no, dependiendo de tu necesidad.
            // Lo dejo como nullable por defecto.
            $table->string('url')->nullable()->comment('URL del bot de WhatsApp');

            // Columnas para timestamps (created_at y updated_at)
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Eliminamos la tabla si la migración se revierte
        Schema::dropIfExists('whatsapp_bot_u_r_l');
    }
};
