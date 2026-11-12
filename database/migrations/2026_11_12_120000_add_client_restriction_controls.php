<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('user_access_blocks', function (Blueprint $table): void {
            $table->string('source', 40)->default('manual');
            $table->string('source_reference')->nullable();
            $table->text('internal_note')->nullable();
        });

        Schema::create('client_restriction_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 40);
            $table->string('source', 40);
            $table->json('details');
            $table->timestamps();
        });

        Schema::create('client_restriction_syncs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('platform', 24);
            $table->uuid('version');
            $table->boolean('blocked');
            $table->string('status', 40)->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamp('retry_at')->nullable()->index();
            $table->timestamps();
            $table->unique(['user_id', 'platform']);
        });

        Schema::create('client_restriction_scans', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('actor_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('partial')->default(false);
            $table->json('candidates');
            $table->timestamp('expires_at');
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();
        });
        \Illuminate\Support\Facades\DB::table('user_access_blocks')->orderBy('user_id')->chunkById(200, function ($blocks): void {
            foreach ($blocks as $block) {
                foreach (['hubspot', 'monday', 'teamleader'] as $platform) {
                    \Illuminate\Support\Facades\DB::table('client_restriction_syncs')->insert([
                        'user_id' => $block->user_id, 'platform' => $platform, 'version' => (string) \Illuminate\Support\Str::uuid(),
                        'blocked' => true, 'status' => 'pending', 'retry_at' => now(), 'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }
        }, 'user_id');
        if (Schema::hasTable('permissions')) {
            \Spatie\Permission\Models\Permission::findOrCreate('clients.blocks.review', 'web');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('client_restriction_scans');
        Schema::dropIfExists('client_restriction_syncs');
        Schema::dropIfExists('client_restriction_events');
        Schema::table('user_access_blocks', fn (Blueprint $table) => $table->dropColumn(['source', 'source_reference', 'internal_note']));
    }
};
