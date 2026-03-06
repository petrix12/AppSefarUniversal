<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('hubspot_owner_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            $table->string('hubspot_owner_id')->nullable()->index();
            $table->string('hubspot_owner_name')->nullable(); // snapshot para UI

            $table->timestamps();

            // Si quieres integridad referencial al catálogo:
            $table->foreign('hubspot_owner_id')
                ->references('id')
                ->on('hubspot_owners')
                ->nullOnDelete(); // si se borra owner del catálogo, deja null
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hubspot_owner_user');
    }
};
