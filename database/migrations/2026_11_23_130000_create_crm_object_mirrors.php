<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_object_mirrors', function (Blueprint $table): void {
            $table->id();
            $table->string('provider', 24);
            $table->string('object_type', 80);
            $table->string('external_id', 100);
            $table->json('properties');
            $table->boolean('archived')->default(false)->index();
            $table->timestamp('remote_created_at')->nullable();
            $table->timestamp('remote_updated_at')->nullable();
            $table->timestamp('remote_archived_at')->nullable();
            $table->string('content_hash', 64);
            $table->timestamp('synced_at')->index();
            $table->timestamps();
            $table->unique(['provider', 'object_type', 'external_id'], 'crm_object_mirror_identity_unique');
        });

        Schema::create('crm_object_associations', function (Blueprint $table): void {
            $table->id();
            $table->string('provider', 24);
            $table->string('from_object_type', 80);
            $table->string('from_external_id', 100);
            $table->string('to_object_type', 80);
            $table->string('to_external_id', 100);
            $table->string('association_category', 80)->nullable();
            $table->string('association_type_id', 100)->default('');
            $table->boolean('active')->default(true)->index();
            $table->timestamp('last_event_at')->nullable();
            $table->timestamps();
            $table->unique(
                ['provider', 'from_object_type', 'from_external_id', 'to_object_type', 'to_external_id', 'association_type_id'],
                'crm_object_association_identity_unique'
            );
            $table->index(['provider', 'from_object_type', 'from_external_id'], 'crm_object_association_from_index');
            $table->index(['provider', 'to_object_type', 'to_external_id'], 'crm_object_association_to_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_object_associations');
        Schema::dropIfExists('crm_object_mirrors');
    }
};
