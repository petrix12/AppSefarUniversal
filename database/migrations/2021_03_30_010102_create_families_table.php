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
            $table->string('Cliente')->nullable();
            $table->string('IDFamiliar');
            $table->string('Familiar')->nullable();
            $table->string('Parentesco')->nullable();
            $table->string('Lado')->nullable();
            $table->string('Rama')->nullable();
            $table->text('Nota')->nullable();
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
