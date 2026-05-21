<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFamilyGroupsTables extends Migration
{
    public function up()
    {
        Schema::create('family_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('primary_id_cliente')->nullable()->index();
            $table->string('match_key')->nullable()->index();
            $table->string('status')->default('calculated')->index();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('family_group_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_group_id')->constrained('family_groups')->cascadeOnDelete();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('anchor_agcliente_id')->nullable();
            $table->string('IDCliente')->index();
            $table->string('display_name')->nullable();
            $table->string('source')->default('manual')->index();
            $table->unsignedTinyInteger('confidence')->default(0);
            $table->string('match_type')->nullable();
            $table->json('match_reasons')->nullable();
            $table->json('evidence')->nullable();
            $table->unsignedBigInteger('added_by')->nullable();
            $table->timestamps();

            $table->unique(['family_group_id', 'IDCliente'], 'family_group_client_unique');
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('added_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down()
    {
        Schema::dropIfExists('family_group_members');
        Schema::dropIfExists('family_groups');
    }
}
