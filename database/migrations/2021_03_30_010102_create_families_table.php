<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFamiliesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('families', function (Blueprint $table) {
            $table->id();
            $table->string('IDCombinado',175)->unique();
            $table->string('IDCliente',175);
            $table->string('Cliente');
            $table->string('IDFamiliar');
            $table->string('Familiar');
            $table->string('Parentesco');
            $table->string('Lado');
            $table->string('Rama');
            $table->text('Nota');
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
        Schema::dropIfExists('families');
    }
}
