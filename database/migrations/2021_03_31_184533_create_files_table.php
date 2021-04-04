<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFilesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('files', function (Blueprint $table) {
            $table->id();
            $table->string('file');                     // Nombre del archivo
            $table->string('location');                 // Ubicación del archivo
            $table->string('tipo')->nullable();         // Tipo de documento
            $table->string('propietario')->nullable();  // Nombre del propietario del documento
            $table->string('IDCliente')->nullable();    // IDCliente del propietario del documento
            $table->string('notas')->nullable();        // Notas
            $table->integer('IDPersona');               // ID de persona
            $table->unsignedBigInteger('user_id');      // Relación con los usuarios
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
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
        Schema::dropIfExists('files');
    }
}
