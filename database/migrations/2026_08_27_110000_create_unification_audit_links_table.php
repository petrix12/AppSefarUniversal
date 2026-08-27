<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * This is an audit/design log. It has no foreign key to an active mapping
     * and no job reads it, so saving a decision here cannot move or sync data.
     */
    public function up(): void
    {
        Schema::create('unification_audit_links', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type', 64)->default('client');
            $table->string('app_field_key', 191);
            $table->string('app_field_label');
            $table->string('provider', 32);
            $table->string('external_entity_type', 64)->default('contact');
            $table->string('scope_key', 191)->default('*');
            $table->string('external_field_key', 191);
            $table->string('external_field_label')->nullable();
            $table->string('match_method', 32)->default('manual');
            $table->unsignedTinyInteger('confidence')->nullable();
            $table->string('status', 32)->default('proposed');
            $table->text('notes')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['entity_type', 'app_field_key'], 'unification_audit_app_field_index');
            $table->index(['provider', 'scope_key', 'external_field_key'], 'unification_audit_external_field_index');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unification_audit_links');
    }
};
