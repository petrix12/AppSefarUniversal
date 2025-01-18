<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMondayFieldMappingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('monday_field_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('local_field_key');     // p.ej. texto_largo88, estado54, etc.
            $table->string('board_id');            // ID del board en Monday, en tu caso string o bigInteger
            $table->string('monday_column_id');    // ID de la columna en Monday
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('monday_field_mappings');
    }
}
