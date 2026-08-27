<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('entity_type', 64)->default('client');
            // event: reacts to an app event; schedule: cron; date_field: relative to a client date.
            $table->string('trigger_type', 32);
            $table->string('trigger_event', 100)->nullable();
            $table->string('cron_expression', 100)->nullable();
            $table->string('timezone', 64)->default('America/Caracas');
            $table->json('trigger_config')->nullable();
            $table->json('conditions')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_scheduled_at')->nullable();
            $table->foreignId('created_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();

            $table->index(['is_active', 'trigger_type'], 'automation_rules_active_trigger_index');
            $table->index(['trigger_event', 'entity_type'], 'automation_rules_event_entity_index');
        });

        Schema::create('automation_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('automation_rule_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->string('action_type', 64);
            $table->unsignedInteger('delay_minutes')->default(0);
            $table->json('config')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Actions are versioned when a rule is edited so historical runs
            // keep pointing to the exact action that produced them.
            $table->index(['automation_rule_id', 'position'], 'automation_actions_rule_position_index');
        });

        Schema::create('automation_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('automation_rule_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('automation_action_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->string('entity_type', 64)->default('client');
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('trigger_event', 100);
            $table->string('event_key', 191);
            $table->timestamp('scheduled_for');
            $table->string('status', 32)->default('pending'); // pending, running, completed, failed, skipped, cancelled
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->json('context')->nullable();
            $table->json('result')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique(['automation_action_id', 'event_key'], 'automation_runs_action_event_unique');
            $table->index(['status', 'scheduled_for'], 'automation_runs_dispatch_index');
            $table->index(['entity_type', 'entity_id', 'created_at'], 'automation_runs_entity_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_runs');
        Schema::dropIfExists('automation_actions');
        Schema::dropIfExists('automation_rules');
    }
};
