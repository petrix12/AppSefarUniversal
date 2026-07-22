<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banca_online_document_rules', function (Blueprint $table) {
            $table->id();
            $table->string('country_slug', 40)->nullable()->index();
            $table->string('plan_slug', 120)->nullable()->index();
            $table->string('document_name');
            $table->enum('document_type', ['juridico', 'genealogico', 'otro'])->default('otro')->index();
            $table->json('match_keywords')->nullable();
            $table->foreignId('recommended_service_id')->nullable()->constrained('servicios')->nullOnDelete();
            $table->string('recommended_plan_slug', 120)->nullable();
            $table->string('client_label')->nullable();
            $table->text('client_explanation')->nullable();
            $table->text('internal_notes')->nullable();
            $table->boolean('required')->default(true);
            $table->boolean('active')->default(true)->index();
            $table->boolean('client_visible')->default(true);
            $table->unsignedSmallInteger('priority')->default(50);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['country_slug', 'plan_slug', 'active', 'priority'], 'bo_doc_rules_context_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banca_online_document_rules');
    }
};
