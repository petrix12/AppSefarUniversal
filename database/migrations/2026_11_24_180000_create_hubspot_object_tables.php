<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $objectTypes = [
        'contacts', 'deals', 'tickets', 'line_items', 'quotes', 'invoices',
        'commerce_payments', 'subscriptions', 'orders', 'calls', 'emails',
        'meetings', 'notes', 'tasks',
    ];

    public function up(): void
    {
        foreach ($this->objectTypes as $objectType) {
            $tableName = 'crm_hubspot_'.$objectType;
            if (! Schema::hasTable($tableName)) {
                Schema::create($tableName, function (Blueprint $table): void {
                    $table->id();
                    $table->string('provider', 32)->default('hubspot');
                    $table->string('external_id', 100);
                    $table->json('properties')->nullable();
                    $table->boolean('archived')->default(false);
                    $table->timestamp('remote_created_at')->nullable();
                    $table->timestamp('remote_updated_at')->nullable();
                    $table->timestamp('remote_archived_at')->nullable();
                    $table->char('content_hash', 64)->nullable();
                    $table->timestamp('synced_at')->nullable();
                    $table->timestamps();
                    $table->unique(['provider', 'external_id']);
                    $table->index(['archived', 'remote_updated_at']);
                });
                continue;
            }

            // MySQL can leave the table in place if a separate index statement fails.
            if (! Schema::hasIndex($tableName, ['provider', 'external_id'], 'unique')) {
                Schema::table($tableName, function (Blueprint $table): void {
                    $table->unique(['provider', 'external_id']);
                });
            }
            if (! Schema::hasIndex($tableName, ['archived', 'remote_updated_at'])) {
                Schema::table($tableName, function (Blueprint $table): void {
                    $table->index(['archived', 'remote_updated_at']);
                });
            }
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->objectTypes) as $objectType) {
            Schema::dropIfExists('crm_hubspot_'.$objectType);
        }
    }
};
