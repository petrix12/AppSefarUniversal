<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('files')) {
            Schema::table('files', function (Blueprint $table) {
                if (! Schema::hasColumn('files', 'IDPersonaNew')) {
                    $table->unsignedBigInteger('IDPersonaNew')->nullable()->index();
                }
                if (! Schema::hasColumn('files', 'migradoNuevoID')) {
                    $table->boolean('migradoNuevoID')->default(false);
                }
                if (! Schema::hasColumn('files', 'source')) {
                    // Existing files are deliberately not published to the client.
                    $table->string('source', 32)->default('legacy')->index();
                }
                if (! Schema::hasColumn('files', 'client_visible')) {
                    $table->boolean('client_visible')->default(false)->index();
                }
                if (! Schema::hasColumn('files', 'document_kind')) {
                    $table->string('document_kind', 32)->nullable()->index();
                }
                if (! Schema::hasColumn('files', 'mime_type')) {
                    $table->string('mime_type', 127)->nullable();
                }
                if (! Schema::hasColumn('files', 'size_bytes')) {
                    $table->unsignedBigInteger('size_bytes')->nullable();
                }
                if (! Schema::hasColumn('files', 'source_reference')) {
                    $table->string('source_reference', 191)->nullable();
                }
                if (! Schema::hasColumn('files', 'document_request_id')) {
                    $table->unsignedBigInteger('document_request_id')->nullable()->index();
                }
            });
        }

        if (Schema::hasTable('document_requests')) {
            Schema::table('document_requests', function (Blueprint $table) {
                if (! Schema::hasColumn('document_requests', 'person_id')) {
                    $table->unsignedBigInteger('person_id')->nullable()->index();
                }
                if (! Schema::hasColumn('document_requests', 'genealogy_union_id')) {
                    $table->unsignedBigInteger('genealogy_union_id')->nullable()->index();
                }
                if (! Schema::hasColumn('document_requests', 'document_kind')) {
                    $table->string('document_kind', 32)->nullable()->index();
                }
            });
        }

        Schema::create('genealogy_unions', function (Blueprint $table) {
            $table->id();
            $table->string('IDCliente', 175)->index();
            $table->unsignedBigInteger('spouse_one_id')->index();
            $table->unsignedBigInteger('spouse_two_id')->index();
            $table->string('marriage_date', 50)->nullable();
            $table->string('marriage_place', 255)->nullable();
            $table->string('marriage_country', 100)->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('genealogy_document_person', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('file_id')->index();
            $table->unsignedBigInteger('person_id')->index();
            $table->string('relationship', 20)->default('subject');
            $table->timestamps();
            $table->unique(['file_id', 'person_id'], 'genealogy_document_person_unique');
        });

        Schema::create('genealogy_document_union', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('file_id')->index();
            $table->unsignedBigInteger('genealogy_union_id')->index();
            $table->timestamps();
            $table->unique(['file_id', 'genealogy_union_id'], 'genealogy_document_union_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('genealogy_document_union');
        Schema::dropIfExists('genealogy_document_person');
        Schema::dropIfExists('genealogy_unions');

        if (Schema::hasTable('document_requests')) {
            Schema::table('document_requests', function (Blueprint $table) {
                foreach (['person_id', 'genealogy_union_id', 'document_kind'] as $column) {
                    if (Schema::hasColumn('document_requests', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('files')) {
            Schema::table('files', function (Blueprint $table) {
                foreach ([
                    // These two columns existed in some installations before this
                    // migration. Keep them on rollback to avoid breaking the tree.
                    'source', 'client_visible',
                    'document_kind', 'mime_type', 'size_bytes', 'source_reference',
                    'document_request_id',
                ] as $column) {
                    if (Schema::hasColumn('files', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
