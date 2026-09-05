<?php

namespace Tests\Feature;

use App\Models\TlContact;
use App\Models\TlInvoice;
use App\Models\TlProject;
use App\Models\User;
use App\Services\TeamleaderClientHistoryService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TeamleaderClientHistoryServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('passport')->nullable();
            $table->timestamps();
        });

        Schema::create('tl_contacts', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('passport')->nullable();
            $table->string('status')->nullable();
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

        Schema::create('tl_deals', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('title')->nullable();
            $table->string('status')->nullable();
            $table->decimal('amount', 15, 2)->nullable();
            $table->string('currency')->nullable();
            $table->string('weighted_revenue')->nullable();
            $table->string('customer_id')->nullable();
            $table->string('customer_type')->nullable();
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

        Schema::create('tl_projects', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('title')->nullable();
            $table->string('status')->nullable();
            $table->string('customer_id')->nullable();
            $table->string('customer_type')->nullable();
            $table->string('responsible_user_id')->nullable();
            $table->decimal('budget_amount', 15, 2)->nullable();
            $table->string('budget_currency')->nullable();
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

        Schema::create('tl_invoices', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('invoice_number')->nullable();
            $table->string('status')->nullable();
            $table->string('customer_id')->nullable();
            $table->string('customer_type')->nullable();
            $table->string('customer_name')->nullable();
            $table->decimal('total_price_excl_tax', 15, 2)->nullable();
            $table->decimal('total_price_incl_tax', 15, 2)->nullable();
            $table->decimal('paid_at_date', 15, 2)->nullable();
            $table->string('currency')->nullable();
            $table->date('invoice_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->date('paid_date')->nullable();
            $table->string('deal_id')->nullable();
            $table->string('project_id')->nullable();
            $table->string('pdf_s3_path')->nullable();
            $table->string('pdf_s3_disk')->nullable();
            $table->boolean('pdf_downloaded')->default(false);
            $table->timestamp('pdf_downloaded_at')->nullable();
            $table->json('invoice_lines')->nullable();
            $table->json('payment_terms')->nullable();
            $table->json('custom_fields')->nullable();
            $table->json('raw_data');
            $table->timestamp('tl_created_at')->nullable();
            $table->timestamp('tl_updated_at')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('tl_invoices');
        Schema::dropIfExists('tl_projects');
        Schema::dropIfExists('tl_deals');
        Schema::dropIfExists('tl_contacts');
        Schema::dropIfExists('users');

        parent::tearDown();
    }

    public function test_it_shows_only_the_history_of_the_best_matching_contact(): void
    {
        $client = new User([
            'email' => 'marcela@example.com',
            'passport' => ' P-123 ',
        ]);

        TlContact::create([
            'id' => 'contact-marcela',
            'first_name' => 'Marcela',
            'email' => 'someone-else@example.com',
            'passport' => 'p-123',
            'raw_data' => [],
        ]);
        TlContact::create([
            'id' => 'contact-email-only',
            'first_name' => 'Otra persona',
            'email' => 'marcela@example.com',
            'passport' => 'P-999',
            'raw_data' => [],
        ]);

        TlProject::create([
            'id' => 'project-marcela',
            'title' => 'Proceso portugués',
            'customer_id' => 'contact-marcela',
            'customer_type' => 'contact',
            'custom_fields' => [
                [
                    'definition' => ['id' => 'a1b50c58-8175-0d13-9856-f661e783dc08'],
                    'value' => 'Abono 1.500 EUR',
                ],
            ],
            'raw_data' => [],
        ]);

        TlInvoice::create([
            'id' => 'invoice-marcela',
            'invoice_number' => '2023/001',
            'status' => 'matched',
            'customer_id' => 'contact-marcela',
            'customer_type' => 'contact',
            'total_price_incl_tax' => 1500,
            'currency' => 'EUR',
            'invoice_date' => '2023-03-10',
            'paid_date' => '2023-03-12',
            'raw_data' => [],
        ]);
        TlInvoice::create([
            'id' => 'invoice-other-contact',
            'invoice_number' => '2024/001',
            'status' => 'matched',
            'customer_id' => 'contact-email-only',
            'customer_type' => 'contact',
            'total_price_incl_tax' => 900,
            'currency' => 'EUR',
            'invoice_date' => '2024-01-20',
            'raw_data' => [],
        ]);

        $history = app(TeamleaderClientHistoryService::class)->for($client);

        $this->assertSame('contact-marcela', $history['contact']->id);
        $this->assertSame(['Pasaporte'], $history['match_labels']);
        $this->assertCount(1, $history['invoices']);
        $this->assertSame('invoice-marcela', $history['invoices']->first()->id);
        $this->assertSame(1500.0, $history['summary']['paid_amounts'][0]['amount']);
        $this->assertSame('12/03/2023', $history['summary']['first_activity_at']->format('d/m/Y'));
    }

    public function test_it_hides_history_when_the_primary_match_is_ambiguous(): void
    {
        $client = new User([
            'email' => 'ana@example.com',
            'passport' => 'P-DUPLICADO',
        ]);

        foreach (['contact-a', 'contact-b'] as $id) {
            TlContact::create([
                'id' => $id,
                'email' => $id . '@example.com',
                'passport' => 'P-DUPLICADO',
                'raw_data' => [],
            ]);
        }

        $history = app(TeamleaderClientHistoryService::class)->for($client);

        $this->assertNull($history['contact']);
        $this->assertCount(0, $history['invoices']);
    }
}
