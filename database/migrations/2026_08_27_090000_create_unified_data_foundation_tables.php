<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The canonical data layer intentionally does not add further columns to
     * users. Existing user columns remain supported while new and external
     * properties live in the definitions/value tables below.
     */
    public function up(): void
    {
        Schema::create('custom_field_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type', 64)->default('client');
            $table->string('key', 191);
            $table->string('label');
            $table->string('data_type', 32)->default('text');
            $table->string('group', 100)->nullable();
            $table->json('options')->nullable();
            $table->json('validation_rules')->nullable();
            $table->boolean('is_required')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['entity_type', 'key'], 'custom_fields_entity_key_unique');
            $table->index(['entity_type', 'is_active'], 'custom_fields_entity_active_index');
        });

        Schema::create('custom_field_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('custom_field_definition_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->string('entity_type', 64)->default('client');
            $table->unsignedBigInteger('entity_id');
            $table->json('value')->nullable();
            // Allows simple exports/searches without relying on JSON syntax.
            $table->text('value_text')->nullable();
            $table->string('source', 32)->default('app');
            $table->timestamp('source_updated_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['custom_field_definition_id', 'entity_type', 'entity_id'],
                'custom_field_values_definition_entity_unique'
            );
            $table->index(['entity_type', 'entity_id'], 'custom_field_values_entity_index');
            $table->index(['source', 'source_updated_at'], 'custom_field_values_source_index');
        });

        Schema::create('external_entity_links', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type', 64)->default('client');
            $table->unsignedBigInteger('entity_id');
            $table->string('provider', 32); // hubspot, monday, teamleader
            $table->string('external_entity_type', 64); // contact, deal, item, project...
            $table->string('external_id', 191);
            $table->json('metadata')->nullable();
            $table->timestamp('external_updated_at')->nullable();
            $table->timestamp('last_pulled_at')->nullable();
            $table->timestamp('last_pushed_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['provider', 'external_entity_type', 'external_id'],
                'external_entity_links_provider_remote_unique'
            );
            $table->index(['entity_type', 'entity_id'], 'external_entity_links_entity_index');
            $table->index(['provider', 'entity_type', 'entity_id'], 'external_entity_links_provider_entity_index');
        });

        Schema::create('integration_field_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 32);
            $table->string('external_entity_type', 64);
            // Board id, pipeline id or * when a mapping applies to every source context.
            $table->string('scope_key', 191)->default('*');
            $table->string('external_field_key', 191);
            $table->string('entity_type', 64)->default('client');
            // Use this only for stable, first-class attributes already present in users.
            $table->string('local_attribute', 191)->nullable();
            $table->foreignId('custom_field_definition_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->string('direction', 16)->default('pull'); // pull, push, bidirectional
            $table->string('conflict_policy', 32)->default('manual'); // local_wins, remote_wins, manual
            $table->json('transform')->nullable();
            // A mapping remains inert until an explicit audit/promotion step.
            $table->boolean('is_active')->default(false);
            $table->timestamps();

            $table->unique(
                ['provider', 'external_entity_type', 'scope_key', 'external_field_key'],
                'integration_field_mappings_remote_field_unique'
            );
            $table->index(['entity_type', 'is_active'], 'integration_field_mappings_entity_active_index');
        });

        Schema::create('integration_sync_runs', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 32);
            $table->string('direction', 16); // pull, push, reconcile
            $table->string('entity_type', 64)->nullable();
            $table->string('status', 32)->default('running');
            $table->unsignedInteger('read_count')->default(0);
            $table->unsignedInteger('created_count')->default(0);
            $table->unsignedInteger('updated_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->json('context')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['provider', 'status', 'started_at'], 'integration_sync_runs_status_index');
        });

        Schema::create('integration_outbox', function (Blueprint $table) {
            $table->id();
            $table->foreignId('external_entity_link_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->string('provider', 32);
            $table->string('entity_type', 64)->default('client');
            $table->unsignedBigInteger('entity_id');
            $table->string('operation', 64); // update_fields, create_item, transfer_workflow...
            $table->string('dedupe_key', 191);
            $table->json('payload');
            $table->string('status', 32)->default('pending'); // pending, processing, sent, failed, cancelled
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('available_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique('dedupe_key');
            $table->index(['provider', 'status', 'available_at'], 'integration_outbox_dispatch_index');
            $table->index(['entity_type', 'entity_id'], 'integration_outbox_entity_index');
        });

        Schema::create('workflow_boards', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 32)->default('monday');
            $table->string('external_board_id', 191);
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'external_board_id'], 'workflow_boards_provider_board_unique');
        });

        Schema::create('workflow_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_board_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->string('external_stage_id', 191);
            $table->string('name');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_terminal')->default(false);
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['workflow_board_id', 'external_stage_id'], 'workflow_stages_board_stage_unique');
        });

        Schema::create('workflow_memberships', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type', 64)->default('client');
            $table->unsignedBigInteger('entity_id');
            $table->foreignId('workflow_board_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('workflow_stage_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->string('external_item_id', 191)->nullable();
            $table->string('status', 32)->default('active'); // active, moved, completed, archived
            $table->string('source', 32)->default('app');
            $table->timestamp('entered_at')->nullable();
            $table->timestamp('left_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['entity_type', 'entity_id', 'workflow_board_id'], 'workflow_memberships_entity_board_unique');
            $table->index(['workflow_board_id', 'workflow_stage_id', 'status'], 'workflow_memberships_board_stage_index');
        });

        Schema::create('workflow_transitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_membership_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->string('entity_type', 64)->default('client');
            $table->unsignedBigInteger('entity_id');
            $table->foreignId('from_workflow_board_id')
                ->nullable()
                ->constrained('workflow_boards')
                ->nullOnDelete();
            $table->foreignId('from_workflow_stage_id')
                ->nullable()
                ->constrained('workflow_stages')
                ->nullOnDelete();
            $table->foreignId('to_workflow_board_id')
                ->nullable()
                ->constrained('workflow_boards')
                ->nullOnDelete();
            $table->foreignId('to_workflow_stage_id')
                ->nullable()
                ->constrained('workflow_stages')
                ->nullOnDelete();
            $table->foreignId('actor_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('source', 32)->default('app');
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['entity_type', 'entity_id', 'created_at'], 'workflow_transitions_entity_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_transitions');
        Schema::dropIfExists('workflow_memberships');
        Schema::dropIfExists('workflow_stages');
        Schema::dropIfExists('workflow_boards');
        Schema::dropIfExists('integration_outbox');
        Schema::dropIfExists('integration_sync_runs');
        Schema::dropIfExists('integration_field_mappings');
        Schema::dropIfExists('external_entity_links');
        Schema::dropIfExists('custom_field_values');
        Schema::dropIfExists('custom_field_definitions');
    }
};
