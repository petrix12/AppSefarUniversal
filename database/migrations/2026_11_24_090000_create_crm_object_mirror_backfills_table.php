<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_object_mirror_backfills', function (Blueprint $table): void {
            $table->id();
            $table->string('provider', 24)->default('hubspot');
            $table->string('object_type', 80);
            $table->boolean('archived')->default(false);
            $table->text('after_cursor')->nullable();
            $table->unsignedBigInteger('records_read')->default(0);
            $table->string('status', 24)->default('queued');
            $table->string('last_error_class', 191)->nullable();
            $table->timestamp('last_page_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'object_type', 'archived'], 'crm_backfill_identity_unique');
            $table->index(['status', 'updated_at'], 'crm_backfill_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_object_mirror_backfills');
    }
};
