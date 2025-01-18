<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEtiquetasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('etiquetas_genealogia', function (Blueprint $table) {
            $table->id();
            // Relación con tabla users (opcional)
            $table->unsignedBigInteger('user_id')->nullable();
            // $table->foreign('user_id')->references('id')->on('users');

            // Textos
            $table->text('texto_largo88')->nullable();
            $table->text('texto30')->nullable();
            $table->text('texto64')->nullable();
            $table->text('texto51')->nullable();
            $table->text('text43')->nullable();
            $table->text('text06')->nullable();
            $table->text('texto6')->nullable();
            $table->text('texto_largo58')->nullable();
            $table->text('texto_largo')->nullable();
            $table->text('texto_largo0')->nullable();
            $table->text('cliente_solicitud')->nullable();
            $table->text('ubicaci_n4')->nullable();
            $table->text('ubicaci_n')->nullable();
            $table->text('texto37')->nullable();
            $table->text('texto2')->nullable();
            $table->text('long_text')->nullable();
            $table->text('long_text6')->nullable();
            $table->text('long_text2')->nullable();


            // Opciones (guardadas como string para simplificar)
            $table->string('estado54')->nullable();
            $table->string('color3')->nullable();
            $table->string('color9')->nullable();
            $table->string('men__desplegable')->nullable();
            $table->string('men__desplegable2')->nullable();
            $table->string('status')->nullable();
            $table->string('estado05')->nullable();
            $table->string('personas2')->nullable();
            $table->string('dup__of_etiquetador')->nullable();
            $table->string('estado46')->nullable();
            $table->string('estado5')->nullable();
            $table->string('estado8')->nullable();
            $table->string('personas_1')->nullable();
            $table->string('person')->nullable();
            $table->string('estado7')->nullable();
            $table->string('estado90')->nullable();

            // Fechas
            $table->date('fecha87')->nullable();
            $table->date('fecha5')->nullable();
            $table->date('fecha3')->nullable();
            $table->date('fecha88')->nullable();
            $table->date('fecha89')->nullable();
            $table->date('fecha0')->nullable();
            $table->date('fecha06')->nullable();
            $table->date('fecha7')->nullable();
            $table->date('fecha71')->nullable();
            $table->date('fecha9')->nullable();
            $table->date('fecha32')->nullable();
            $table->date('fecha86')->nullable();

            // Campos de timestamps de Laravel
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
        Schema::dropIfExists('etiquetas_genealogia');
    }
}
