<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_sync_conflicts', function (Blueprint $table): void {
            $table->id();
            $table->string('provider', 32)->default('hubspot');
            $table->string('entity_type', 64);
            $table->unsignedBigInteger('entity_id');
            $table->string('external_object_type', 80);
            $table->string('external_id', 100);
            $table->string('field_name', 191);
            $table->text('values'); // Encrypted by CrmSyncConflict model cast.
            $table->timestamp('local_updated_at')->nullable();
            $table->timestamp('remote_updated_at')->nullable();
            $table->char('conflict_key', 64)->unique();
            $table->string('status', 24)->default('pending');
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'provider', 'external_object_type'], 'crm_sync_conflict_queue_index');
            $table->index(['entity_type', 'entity_id'], 'crm_sync_conflict_entity_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_sync_conflicts');
    }
};
