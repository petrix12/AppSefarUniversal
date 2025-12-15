<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cos_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('paso_id')->nullable()->constrained('cos_pasos')->nullOnDelete();
            $table->foreignId('subfase_id')->nullable()->constrained('cos_subfases')->nullOnDelete();

            $table->enum('tipo', ['cta', 'subitem'])->default('cta');

            $table->string('texto');
            $table->string('url')->nullable();

            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cos_items');
    }
};
