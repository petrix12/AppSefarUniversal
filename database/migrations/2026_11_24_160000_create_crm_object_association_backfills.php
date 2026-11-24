<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_object_associations', function (Blueprint $table): void {
            $table->string('association_label', 191)->nullable()->after('association_category');
        });

        Schema::create('crm_object_association_backfills', function (Blueprint $table): void {
            $table->id();
            $table->string('provider', 24)->default('hubspot');
            $table->string('from_object_type', 80);
            $table->string('to_object_type', 80);
            $table->string('source_cursor', 100)->nullable();
            $table->json('association_labels')->nullable();
            $table->unsignedBigInteger('records_read')->default(0);
            $table->unsignedBigInteger('associations_read')->default(0);
            $table->string('status', 24)->default('queued');
            $table->string('last_error_class', 191)->nullable();
            $table->timestamp('last_page_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'from_object_type', 'to_object_type'], 'crm_assoc_backfill_pair_unique');
            $table->index(['status', 'updated_at'], 'crm_assoc_backfill_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_object_association_backfills');
        Schema::table('crm_object_associations', function (Blueprint $table): void {
            $table->dropColumn('association_label');
        });
    }
};
