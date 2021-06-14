<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMiscelaneosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('miscelaneos', function (Blueprint $table) {
            $table->id();
            $table->string('id_bd',4)->nullable();      // id correspondiente en la tabla bd
            $table->string('titulo');                   // Título
            $table->string('autor')->nullable();        // Autor(es)
            $table->string('publicado')->nullable();    // Publicado en
            $table->string('editorial')->nullable();    // Ciudad / Editorial
            $table->string('volumen')->nullable();      // Año / Número / Volumen
            $table->string('paginacion')->nullable();   // Paginación
            $table->string('isbn')->nullable();         // ISBN / ISSN
            $table->text('claves')->nullable();         // Palabras claves
            $table->string('enlace');                   // Enlace o url del documento
            $table->text('notas')->nullable();          // Notas
            $table->string('material')->nullable();     // Tipo de material:    - Artículo de publicación periódica
                                                        //                      - Capítulo de libro
                                                        //                      - Material genealógico
                                                        //                      - Informes de Sefar
                                                        //                      - Otros
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
        Schema::dropIfExists('miscelaneos');
    }
}
