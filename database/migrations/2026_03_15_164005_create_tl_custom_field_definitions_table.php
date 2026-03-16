<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tl_custom_field_definitions', function (Blueprint $table) {
            $table->string('id')->primary();       // UUID de Teamleader
            $table->string('label');               // Nombre legible
            $table->string('type');                // text, date, money, single_select, etc.
            $table->string('context');             // contact, company, deal, project...
            $table->boolean('required')->default(false);
            $table->json('configuration')->nullable(); // opciones de select, etc.
            $table->json('raw_data')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tl_custom_field_definitions');
    }
};
