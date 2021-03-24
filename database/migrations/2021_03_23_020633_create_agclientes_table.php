<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAgclientesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Por ahora no crearé la migración, ya que es una tabla que se encuentra en producción
        // Cuando el proyecto esté lo suficientemente maduro, realizaré la migración, 
        // por ahora no para evitar conflictos en producción
        /* Schema::create('agclientes', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
        }); */
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        /* Schema::dropIfExists('agclientes'); */
    }
}
