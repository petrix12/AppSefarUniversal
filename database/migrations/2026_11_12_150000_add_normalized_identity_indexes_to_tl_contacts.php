<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('tl_contacts')) return;

        // Indexed generated values preserve the imported data and stay current
        // even when an import updates contacts without firing Eloquent events.
        foreach (['email', 'passport'] as $field) {
            $column = $field.'_lookup';
            if (! Schema::hasColumn('tl_contacts', $column)) {
                Schema::table('tl_contacts', function (Blueprint $table) use ($column, $field): void {
                    $table->string($column, 255)->nullable()->virtualAs('LOWER(TRIM('.$field.'))');
                });
            }
            if (! Schema::hasIndex('tl_contacts', 'tl_contacts_'.$column.'_index')) {
                Schema::table('tl_contacts', fn (Blueprint $table) => $table->index($column));
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('tl_contacts')) return;
        foreach (['email_lookup', 'passport_lookup'] as $column) {
            if (Schema::hasIndex('tl_contacts', 'tl_contacts_'.$column.'_index')) {
                Schema::table('tl_contacts', fn (Blueprint $table) => $table->dropIndex('tl_contacts_'.$column.'_index'));
            }
            if (Schema::hasColumn('tl_contacts', $column)) {
                Schema::table('tl_contacts', fn (Blueprint $table) => $table->dropColumn($column));
            }
        }
    }
};
