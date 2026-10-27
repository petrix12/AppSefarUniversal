<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monday_boards', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name')->nullable()->index();
            $table->string('state')->nullable()->index();
            $table->string('kind')->nullable();
            $table->string('permissions')->nullable();
            $table->string('workspace_id')->nullable()->index();
            $table->string('workspace_name')->nullable();
            $table->unsignedBigInteger('items_count')->nullable();
            $table->json('raw_data')->nullable();
            $table->uuid('last_seen_sync_token')->nullable()->index();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('last_synced_at')->nullable()->index();
            $table->timestamp('remote_deleted_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('monday_groups', function (Blueprint $table) {
            $table->id();
            $table->string('board_id')->index();
            $table->string('monday_id');
            $table->string('title')->nullable();
            $table->string('color')->nullable();
            $table->string('position')->nullable();
            $table->json('raw_data')->nullable();
            $table->uuid('last_seen_sync_token')->nullable()->index();
            $table->timestamp('last_synced_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['board_id', 'monday_id']);
            $table->foreign('board_id')->references('id')->on('monday_boards')->cascadeOnDelete();
        });

        Schema::create('monday_columns', function (Blueprint $table) {
            $table->id();
            $table->string('board_id')->index();
            $table->string('monday_id');
            $table->string('title')->nullable()->index();
            $table->string('type')->nullable()->index();
            $table->text('description')->nullable();
            $table->json('settings')->nullable();
            $table->json('raw_data')->nullable();
            $table->uuid('last_seen_sync_token')->nullable()->index();
            $table->timestamp('last_synced_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['board_id', 'monday_id']);
            $table->foreign('board_id')->references('id')->on('monday_boards')->cascadeOnDelete();
        });

        Schema::create('monday_items', function (Blueprint $table) {
            $table->string('id')->primary();
            // board_id is the visible/root board being mirrored. Subitems can
            // belong to an internal Monday board, retained in source_board_id.
            $table->string('board_id')->index();
            $table->string('source_board_id')->nullable()->index();
            $table->string('group_id')->nullable()->index();
            $table->string('parent_item_id')->nullable()->index();
            $table->string('name')->nullable()->index();
            $table->string('state')->nullable()->index();
            $table->string('creator_id')->nullable();
            $table->text('relative_link')->nullable();
            $table->timestamp('monday_created_at')->nullable();
            $table->timestamp('monday_updated_at')->nullable()->index();
            $table->json('raw_data')->nullable();
            $table->uuid('last_seen_sync_token')->nullable()->index();
            $table->timestamp('last_synced_at')->nullable()->index();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('board_id')->references('id')->on('monday_boards')->cascadeOnDelete();
        });

        Schema::create('monday_item_values', function (Blueprint $table) {
            $table->id();
            $table->string('item_id')->index();
            $table->string('board_id')->index();
            $table->string('monday_column_id')->index();
            $table->string('type')->nullable()->index();
            $table->text('text')->nullable();
            $table->json('value')->nullable();
            $table->json('raw_data')->nullable();
            $table->uuid('last_seen_sync_token')->nullable()->index();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['item_id', 'monday_column_id']);
            $table->foreign('item_id')->references('id')->on('monday_items')->cascadeOnDelete();
            $table->foreign('board_id')->references('id')->on('monday_boards')->cascadeOnDelete();
        });

        Schema::create('monday_item_movements', function (Blueprint $table) {
            $table->id();
            $table->string('item_id')->index();
            $table->string('from_board_id')->nullable()->index();
            $table->string('to_board_id')->nullable()->index();
            $table->string('from_group_id')->nullable();
            $table->string('to_group_id')->nullable();
            $table->string('from_parent_item_id')->nullable();
            $table->string('to_parent_item_id')->nullable();
            $table->string('source')->index();
            $table->timestamp('moved_at')->index();
            $table->timestamps();

            $table->foreign('item_id')->references('id')->on('monday_items')->cascadeOnDelete();
        });

        Schema::create('monday_sync_runs', function (Blueprint $table) {
            $table->id();
            $table->uuid('token')->unique();
            $table->string('scope')->index();
            $table->string('board_id')->nullable()->index();
            $table->string('status')->index();
            $table->unsignedInteger('boards')->default(0);
            $table->unsignedInteger('items')->default(0);
            $table->unsignedInteger('subitems')->default(0);
            $table->unsignedInteger('values')->default(0);
            $table->unsignedInteger('requests')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('monday_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_key')->unique();
            $table->string('event_type')->nullable()->index();
            $table->string('board_id')->nullable()->index();
            $table->string('item_id')->nullable()->index();
            $table->json('payload');
            $table->string('status')->default('pending')->index();
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monday_webhook_events');
        Schema::dropIfExists('monday_sync_runs');
        Schema::dropIfExists('monday_item_movements');
        Schema::dropIfExists('monday_item_values');
        Schema::dropIfExists('monday_items');
        Schema::dropIfExists('monday_columns');
        Schema::dropIfExists('monday_groups');
        Schema::dropIfExists('monday_boards');
    }
};
