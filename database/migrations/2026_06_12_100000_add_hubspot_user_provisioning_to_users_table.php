<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'hubspot_user_id')) {
                $table->string('hubspot_user_id')
                    ->nullable()
                    ->after('hubspot_owner_id')
                    ->index();
            }

            if (! Schema::hasColumn('users', 'hubspot_user_provisioned_at')) {
                $table->timestamp('hubspot_user_provisioned_at')
                    ->nullable()
                    ->after('hubspot_user_id');
            }

            if (! Schema::hasColumn('users', 'hubspot_user_provisioning_error')) {
                $table->text('hubspot_user_provisioning_error')
                    ->nullable()
                    ->after('hubspot_user_provisioned_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'hubspot_user_id')) {
                $table->dropIndex(['hubspot_user_id']);
            }

            $columns = array_filter([
                Schema::hasColumn('users', 'hubspot_user_id') ? 'hubspot_user_id' : null,
                Schema::hasColumn('users', 'hubspot_user_provisioned_at') ? 'hubspot_user_provisioned_at' : null,
                Schema::hasColumn('users', 'hubspot_user_provisioning_error') ? 'hubspot_user_provisioning_error' : null,
            ]);

            if (! empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
