<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLibrariesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('libraries', function (Blueprint $table) {
            $table->id();
            $table->string('documento')->unique();              // Nombre del documento
            $table->string('formato',12)->nullable();           // Formato del documento
            $table->string('tipo',45)->nullable();              // Tipo del documento
            $table->string('fuente')->nullable();               // Fuente del documento
            $table->string('origen')->nullable();               // Origen del documento
            $table->string('ubicacion')->nullable();            // Ubicación actual del documento
            $table->string('ubicacion_ant')->nullable();        // Ubicación anterior del documento
            $table->text('busqueda')->nullable();               // Palabras que faciliten la búsqueda del documento
            $table->text('notas')->nullable();                  // Notas para el documento
            $table->string('enlace')->nullable();               // Enlace o url del documento
            $table->string('anho_ini',11)->nullable();          // Año inicial al que hacer referencia el documento
            $table->string('anho_fin',11)->nullable();          // Año final al que hacer referencia el documento
            $table->string('pais')->nullable();                 // País al que hacer referencia el documento
            $table->string('ciudad',150)->nullable();           // Ciudad al que hacer referencia el documento
            $table->dateTime('FIncorporacion')->nullable();     // Fecha de incorporación
            $table->string('responsabilidad',150)->nullable();  // Mención de responsabilidad
            $table->string('edicion',150)->nullable();          // Edición del documento
            $table->string('editorial',150)->nullable();        // Editorial, ciudad
            $table->integer('anho_publicacion')->nullable();    // Año de publicación
            $table->string('no_vol',50)->nullable();            // Número y volumen
            $table->string('coleccion',100)->nullable();        // Colección
            $table->string('colacion',50)->nullable();          // Colación
            $table->string('isbn',50)->nullable();              // ISBN o ISSN
            $table->string('serie',50)->nullable();             // Serie
            $table->string('no_clasificacion',50)->nullable();  // Número de clasificación
            $table->string('titulo_revista')->nullable();       // Título de la revista
            $table->text('resumen')->nullable();                // Resumen del documento
            $table->string('caratula_url')->nullable();         // Ubicación de la caratula
            $table->string('usuario')->nullable();              // Nombre o email del usuario que creo el documento
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
        Schema::dropIfExists('libraries');
    }
}
