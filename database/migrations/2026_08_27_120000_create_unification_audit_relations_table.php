<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Generic design edges are kept separate from active integration mappings.
     * They support App↔HubSpot, HubSpot↔Teamleader, Monday↔App, etc., without
     * moving values or altering any current process.
     */
    public function up(): void
    {
        Schema::create('unification_audit_relations', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type', 64)->default('client');
            $table->string('left_provider', 32);
            $table->string('left_entity_type', 64)->default('contact');
            $table->string('left_scope_key', 191)->default('*');
            $table->string('left_field_key', 191);
            $table->string('left_field_label')->nullable();
            $table->string('right_provider', 32);
            $table->string('right_entity_type', 64)->default('contact');
            $table->string('right_scope_key', 191)->default('*');
            $table->string('right_field_key', 191);
            $table->string('right_field_label')->nullable();
            $table->string('match_method', 32)->default('manual');
            $table->unsignedTinyInteger('confidence')->nullable();
            $table->string('status', 32)->default('proposed');
            $table->text('notes')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['left_provider', 'left_scope_key', 'left_field_key'], 'unification_relations_left_field_index');
            $table->index(['right_provider', 'right_scope_key', 'right_field_key'], 'unification_relations_right_field_index');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unification_audit_relations');
    }
};
