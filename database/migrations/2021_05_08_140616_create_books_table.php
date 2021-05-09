<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBooksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('books', function (Blueprint $table) {
            $table->id();
            $table->string('id_bd',4)->nullable();      // id correspondiente en la tabla bd
            $table->string('titulo');
            $table->string('subtitulo')->nullable();
            $table->string('autor')->nullable();
            $table->string('editorial')->nullable();    // Ciudad / Editorial
            $table->string('coleccion')->nullable();    // Colección, Serie, Número
            $table->date('fecha')->nullable();          // Fecha de edición
            $table->string('edicion')->nullable();      // Número de edición
            $table->string('paginacion')->nullable();
            $table->string('isbn')->nullable();
            $table->text('notas')->nullable();
            $table->string('enlace');                   // Enlace o url del documento
            $table->text('claves')->nullable();         // Palabras claves
            $table->string('catalogador')->nullable();  // Nombre o email del usuario que creo el documento
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
        Schema::dropIfExists('books');
    }
}
