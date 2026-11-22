<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('platform_records', function (Blueprint $t) {
            $t->id(); $t->string('provider', 20); $t->string('account', 20); $t->string('mode', 12); $t->string('resource', 40); $t->string('remote_id', 190);
            $t->string('name')->nullable(); $t->string('email')->nullable(); $t->string('status', 80)->nullable();
            $t->longText('data'); $t->timestamp('checked_at')->nullable(); $t->timestamps();
            $t->unique(['provider','account','mode','resource','remote_id'], 'platform_record_identity');
            $t->index(['provider','account','resource','status'], 'platform_record_filter');
        });
        Schema::create('platform_events', function (Blueprint $t) {
            $t->id(); $t->string('provider', 20); $t->string('account', 20); $t->string('mode', 12); $t->string('event_id', 190);
            $t->string('event_type', 100); $t->string('resource', 40); $t->string('remote_id', 190);
            $t->string('status', 20)->default('pending'); $t->unsignedSmallInteger('attempts')->default(0);
            $t->timestamp('next_attempt_at')->nullable(); $t->uuid('lease')->nullable(); $t->timestamp('leased_until')->nullable();
            $t->string('error_code', 80)->nullable(); $t->timestamps();
            $t->unique(['provider','account','mode','event_id'], 'platform_event_identity'); $t->index(['status','next_attempt_at']);
        });
        Schema::create('platform_operations', function (Blueprint $t) {
            $t->id(); $t->uuid('uuid')->unique(); $t->foreignId('actor_id')->constrained('users');
            $t->foreignId('record_id')->constrained('platform_records'); $t->string('action', 40);
            $t->longText('payload'); $t->string('status', 20); $t->string('remote_id', 190)->nullable();
            $t->string('error_code', 80)->nullable(); $t->uuid('lease')->nullable(); $t->timestamp('leased_until')->nullable();
            $t->timestamps(); $t->index(['record_id','status']);
        });
        Schema::create('platform_sync_states', function (Blueprint $t) {
            $t->id(); $t->string('provider', 20); $t->string('account', 20); $t->string('mode', 12); $t->string('resource', 40);
            $t->string('cursor', 190)->nullable(); $t->unsignedInteger('page')->default(1);
            $t->boolean('requested')->default(false); $t->boolean('has_more')->default(false);
            $t->timestamp('checked_at')->nullable(); $t->string('error_code', 80)->nullable();
            $t->uuid('lease')->nullable(); $t->timestamp('leased_until')->nullable(); $t->timestamps();
            $t->unique(['provider','account','mode','resource'], 'platform_sync_identity');
        });
    }
    public function down(): void
    {
        foreach (['platform_sync_states','platform_operations','platform_events','platform_records'] as $name) Schema::dropIfExists($name);
    }
};
