<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tl_document_user_links', function (Blueprint $table) {
            $table->id();
            $table->string('tl_document_id')->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('tl_contact_id')->nullable()->index();
            $table->string('entity_type')->index();
            $table->string('entity_id')->index();
            $table->string('matched_by')->nullable();
            $table->string('status')->default('suggested')->index();
            $table->timestamps();

            $table->unique(['tl_document_id', 'user_id'], 'tl_doc_user_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tl_document_user_links');
    }
};
