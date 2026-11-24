<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_identity_reviews', function (Blueprint $table): void {
            $table->id();
            $table->string('provider', 24)->default('hubspot');
            $table->string('object_type', 80)->default('contacts');
            $table->string('external_id', 100);
            $table->string('reason_code', 64);
            $table->json('candidate_user_ids')->nullable();
            $table->json('candidate_lead_ids')->nullable();
            $table->json('matched_fields')->nullable();
            $table->string('status', 24)->default('pending');
            $table->string('resolution', 24)->nullable();
            $table->unsignedBigInteger('local_entity_id')->nullable();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'object_type', 'external_id'], 'crm_identity_review_remote_unique');
            $table->index(['status', 'reason_code'], 'crm_identity_review_queue_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_identity_reviews');
    }
};
