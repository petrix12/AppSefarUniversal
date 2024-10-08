<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSolicitudesCuponesTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('solicitudes_cupones', function (Blueprint $table) {
            $table->increments('id');
            $table->string('jotform_submission_id')->unique();
            $table->string('form_id');
            $table->string('ip');
            $table->string('nombre_solicitante');
            $table->string('apellidos_solicitante');
            $table->string('correo_solicitante');
            $table->string('nombre_cliente');
            $table->string('apellidos_cliente');
            $table->string('correo_cliente');
            $table->string('pasaporte_cliente');
            $table->text('motivo_solicitud');
            $table->string('tipo_cupon');
            $table->integer('porcentaje_descuento')->nullable();
            $table->text('comprobante_pago')->nullable();
            $table->integer('id_cupon')->nullable();
            $table->boolean('aprobado')->nullable();
            $table->boolean('estatus_cupon')->nullable()->default(0);
            $table->timestamps(); // Incluye 'created_at' y 'updated_at'
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('solicitudes_cupones');
    }
}
