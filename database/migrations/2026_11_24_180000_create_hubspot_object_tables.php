<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
            }

            $this->copyExistingSnapshots($objectType, $tableName);
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->objectTypes) as $objectType) {
            Schema::dropIfExists('crm_hubspot_'.$objectType);
        }
    }

    private function copyExistingSnapshots(string $objectType, string $tableName): void
    {
        if (! Schema::hasTable('crm_object_mirrors')) {
            return;
        }

        DB::table('crm_object_mirrors')
            ->where('object_type', $objectType)
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($tableName): void {
                $records = $rows->map(fn ($row) => [
                    'provider' => $row->provider,
                    'external_id' => $row->external_id,
                    'properties' => $row->properties,
                    'archived' => (bool) $row->archived,
                    'remote_created_at' => $row->remote_created_at,
                    'remote_updated_at' => $row->remote_updated_at,
                    'remote_archived_at' => $row->remote_archived_at,
                    'content_hash' => $row->content_hash,
                    'synced_at' => $row->synced_at,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ])->all();

                if ($records !== []) {
                    DB::table($tableName)->upsert($records, ['provider', 'external_id'], [
                        'properties', 'archived', 'remote_created_at', 'remote_updated_at',
                        'remote_archived_at', 'content_hash', 'synced_at', 'updated_at',
                    ]);
                }
            });
    }
};
