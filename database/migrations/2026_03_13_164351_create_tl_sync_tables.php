<?php
// database/migrations/xxxx_create_tl_sync_tables.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ─────────────────────────────────────────
        // CONTACTOS
        // ─────────────────────────────────────────
        Schema::create('tl_contacts', function (Blueprint $table) {
            $table->string('id')->primary();        // UUID de Teamleader
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email')->nullable()->index();
            $table->string('phone')->nullable();
            $table->string('passport')->nullable()->index(); // Custom field
            $table->string('status')->nullable();            // active, deleted...
            $table->json('emails')->nullable();              // Array completo de emails
            $table->json('telephones')->nullable();          // Array completo de teléfonos
            $table->json('addresses')->nullable();
            $table->json('custom_fields')->nullable();       // Todos los custom fields
            $table->json('tags')->nullable();
            $table->json('raw_data');                        // JSON completo de TL
            $table->timestamp('tl_added_at')->nullable();
            $table->timestamp('tl_updated_at')->nullable();
            $table->timestamps();
        });

        // ─────────────────────────────────────────
        // EMPRESAS
        // ─────────────────────────────────────────
        Schema::create('tl_companies', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name')->nullable()->index();
            $table->string('email')->nullable()->index();
            $table->string('phone')->nullable();
            $table->string('vat_number')->nullable();
            $table->string('website')->nullable();
            $table->json('emails')->nullable();
            $table->json('telephones')->nullable();
            $table->json('addresses')->nullable();
            $table->json('custom_fields')->nullable();
            $table->json('tags')->nullable();
            $table->json('raw_data');
            $table->timestamp('tl_added_at')->nullable();
            $table->timestamp('tl_updated_at')->nullable();
            $table->timestamps();
        });

        // ─────────────────────────────────────────
        // DEALS (TRATOS)
        // ─────────────────────────────────────────
        Schema::create('tl_deals', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('title')->nullable();
            $table->string('status')->nullable()->index();   // open, won, lost
            $table->decimal('amount', 15, 2)->nullable();
            $table->string('currency', 10)->nullable();
            $table->string('weighted_revenue')->nullable();
            // Relación polimórfica con contacto o empresa
            $table->string('customer_id')->nullable()->index();
            $table->string('customer_type')->nullable();     // contact | company
            $table->string('responsible_user_id')->nullable();
            $table->date('estimated_closing_date')->nullable();
            $table->string('source')->nullable();
            $table->json('custom_fields')->nullable();
            $table->json('tags')->nullable();
            $table->json('raw_data');
            $table->timestamp('tl_created_at')->nullable();
            $table->timestamp('tl_updated_at')->nullable();
            $table->timestamps();
        });

        // ─────────────────────────────────────────
        // PROYECTOS
        // ─────────────────────────────────────────
        Schema::create('tl_projects', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('title')->nullable();
            $table->string('status')->nullable()->index();   // active, on_hold, done, cancelled
            $table->string('customer_id')->nullable()->index();
            $table->string('customer_type')->nullable();     // contact | company
            $table->string('responsible_user_id')->nullable();
            $table->decimal('budget_amount', 15, 2)->nullable();
            $table->string('budget_currency', 10)->nullable();
            $table->date('starts_on')->nullable();
            $table->date('due_on')->nullable();
            $table->text('description')->nullable();
            $table->json('participants')->nullable();
            $table->json('milestones')->nullable();
            $table->json('custom_fields')->nullable();
            $table->json('tags')->nullable();
            $table->json('raw_data');
            $table->timestamp('tl_created_at')->nullable();
            $table->timestamp('tl_updated_at')->nullable();
            $table->timestamps();
        });

        // ─────────────────────────────────────────
        // DOCUMENTOS / ARCHIVOS
        // ─────────────────────────────────────────
        Schema::create('tl_documents', function (Blueprint $table) {
            $table->string('id')->primary();           // UUID de Teamleader
            $table->string('name')->nullable();
            $table->string('entity_type')->index();    // contact | company | deal | project
            $table->string('entity_id')->index();      // ID de la entidad padre
            $table->string('s3_path')->nullable();     // teamleader/contacts/xxx/archivo.pdf
            $table->string('s3_disk')->default('s3'); // por si cambias de disco
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->string('extension')->nullable();
            $table->boolean('downloaded')->default(false)->index();
            $table->timestamp('downloaded_at')->nullable();
            $table->json('raw_data');
            $table->timestamp('tl_created_at')->nullable();
            $table->timestamp('tl_updated_at')->nullable();
            $table->timestamps();

            // Índice compuesto para buscar docs de una entidad
            $table->index(['entity_type', 'entity_id']);
        });

        // ─────────────────────────────────────────
        // LOG DE SINCRONIZACIÓN
        // ─────────────────────────────────────────
        Schema::create('tl_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->string('entity');           // contacts | companies | deals | projects | documents
            $table->string('status');           // running | completed | failed
            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('processed')->default(0);
            $table->unsignedInteger('failed')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        // Agregar dentro del método up() en tu migración existente
        // O crear una nueva: php artisan make:migration add_tl_invoices_tables

        // ─────────────────────────────────────────────
        // FACTURAS
        // ─────────────────────────────────────────────
        Schema::create('tl_invoices', function (Blueprint $table) {
            $table->string('id')->primary();            // UUID de Teamleader
            $table->string('invoice_number')->nullable()->index(); // Ej: 2024/001
            $table->string('status')->nullable()->index();
            // draft | outstanding | matched | late | paid

            // Cliente
            $table->string('customer_id')->nullable()->index();
            $table->string('customer_type')->nullable();   // contact | company
            $table->string('customer_name')->nullable();   // Snapshot del nombre

            // Montos
            $table->decimal('total_price_excl_tax', 15, 2)->nullable();
            $table->decimal('total_price_incl_tax', 15, 2)->nullable();
            $table->decimal('paid_at_date', 15, 2)->nullable();
            $table->string('currency', 10)->nullable();

            // Fechas
            $table->date('invoice_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->date('paid_date')->nullable();

            // Deal/Proyecto relacionado
            $table->string('deal_id')->nullable()->index();
            $table->string('project_id')->nullable()->index();

            // PDF en S3
            $table->string('pdf_s3_path')->nullable();
            $table->string('pdf_s3_disk')->default('s3');
            $table->boolean('pdf_downloaded')->default(false)->index();
            $table->timestamp('pdf_downloaded_at')->nullable();

            // Líneas de factura y datos completos
            $table->json('invoice_lines')->nullable();  // Array de líneas
            $table->json('payment_terms')->nullable();
            $table->json('custom_fields')->nullable();
            $table->json('raw_data');

            $table->timestamp('tl_created_at')->nullable();
            $table->timestamp('tl_updated_at')->nullable();
            $table->timestamps();
        });

        // ─────────────────────────────────────────────
        // NOTAS DE CRÉDITO
        // ─────────────────────────────────────────────
        Schema::create('tl_credit_notes', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('credit_note_number')->nullable()->index();
            $table->string('status')->nullable()->index();

            // Cliente
            $table->string('customer_id')->nullable()->index();
            $table->string('customer_type')->nullable();
            $table->string('customer_name')->nullable();

            // Montos
            $table->decimal('total_price_excl_tax', 15, 2)->nullable();
            $table->decimal('total_price_incl_tax', 15, 2)->nullable();
            $table->string('currency', 10)->nullable();

            // Factura original
            $table->string('invoice_id')->nullable()->index();

            // Fechas
            $table->date('credit_note_date')->nullable();

            // PDF en S3
            $table->string('pdf_s3_path')->nullable();
            $table->string('pdf_s3_disk')->default('s3');
            $table->boolean('pdf_downloaded')->default(false)->index();
            $table->timestamp('pdf_downloaded_at')->nullable();

            $table->json('invoice_lines')->nullable();
            $table->json('custom_fields')->nullable();
            $table->json('raw_data');

            $table->timestamp('tl_created_at')->nullable();
            $table->timestamp('tl_updated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tl_sync_logs');
        Schema::dropIfExists('tl_documents');
        Schema::dropIfExists('tl_projects');
        Schema::dropIfExists('tl_deals');
        Schema::dropIfExists('tl_companies');
        Schema::dropIfExists('tl_contacts');
        Schema::dropIfExists('tl_invoices');
        Schema::dropIfExists('tl_credit_notes');
    }
};
