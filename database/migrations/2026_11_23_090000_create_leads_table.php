<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('hubspot_id', 64)->nullable()->unique();
            $table->string('nombre');
            $table->string('email')->nullable();
            $table->string('telefono')->nullable();
            $table->string('passport')->nullable();
            $table->string('origen')->nullable();
            $table->string('etapa')->default('nuevo');
            $table->string('estado')->default('abierto');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('converted_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('converted_at')->nullable();
            $table->timestamps();

            $table->index(['estado', 'etapa']);
            $table->index('email');
            $table->index('passport');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
