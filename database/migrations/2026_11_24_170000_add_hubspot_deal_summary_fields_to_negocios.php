<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $columns = [
            'amount' => fn (Blueprint $table) => $table->decimal('amount', 18, 2)->nullable(),
            'deal_currency_code' => fn (Blueprint $table) => $table->string('deal_currency_code', 12)->nullable(),
            'pipeline' => fn (Blueprint $table) => $table->string('pipeline', 100)->nullable(),
            'dealstage' => fn (Blueprint $table) => $table->string('dealstage', 100)->nullable(),
            'hubspot_owner_id' => fn (Blueprint $table) => $table->string('hubspot_owner_id', 64)->nullable()->index(),
        ];

        foreach ($columns as $name => $addColumn) {
            if (! Schema::hasColumn('negocios', $name)) {
                Schema::table('negocios', $addColumn);
            }
        }
    }

    public function down(): void
    {
        $columns = array_filter(
            ['amount', 'deal_currency_code', 'pipeline', 'dealstage', 'hubspot_owner_id'],
            fn (string $column): bool => Schema::hasColumn('negocios', $column),
        );

        if ($columns !== []) {
            Schema::table('negocios', function (Blueprint $table) use ($columns): void {
                if (in_array('hubspot_owner_id', $columns, true)) {
                    $table->dropIndex(['hubspot_owner_id']);
                }
                $table->dropColumn($columns);
            });
        }
    }
};
