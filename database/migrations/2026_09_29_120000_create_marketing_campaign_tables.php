<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_contacts', function (Blueprint $table) {
            $table->id();
            $table->string('email', 191)->unique();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('phone')->nullable();
            $table->json('attributes')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->timestamp('suppressed_at')->nullable();
            $table->string('suppression_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('marketing_lists', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('source_type');
            $table->json('source_config')->nullable();
            $table->timestamp('last_imported_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('marketing_contact_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketing_contact_id')->constrained()->cascadeOnDelete();
            $table->string('source_type');
            $table->string('source_id')->nullable();
            $table->json('source_data')->nullable();
            $table->timestamps();
            $table->unique(['marketing_contact_id', 'source_type', 'source_id'], 'marketing_contact_source_unique');
        });

        Schema::create('marketing_list_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketing_list_id')->constrained('marketing_lists')->cascadeOnDelete();
            $table->foreignId('marketing_contact_id')->constrained()->cascadeOnDelete();
            $table->json('source_data')->nullable();
            $table->timestamps();
            $table->unique(['marketing_list_id', 'marketing_contact_id'], 'mkt_list_member_contact_unique');
        });

        Schema::create('marketing_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('subject')->nullable();
            $table->longText('body_html');
            $table->text('body_text')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('marketing_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('subject');
            $table->string('from_email');
            $table->string('from_name')->nullable();
            $table->string('reply_to')->nullable();
            $table->longText('body_html');
            $table->text('body_text')->nullable();
            $table->string('status')->default('draft')->index();
            $table->timestamp('scheduled_at')->nullable()->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->foreignId('marketing_list_id')->nullable()->constrained('marketing_lists')->nullOnDelete();
            $table->foreignId('marketing_template_id')->nullable()->constrained('marketing_templates')->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('marketing_campaign_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketing_campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('marketing_contact_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email', 191)->index();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->json('attributes')->nullable();
            $table->uuid('tracking_token')->unique();
            $table->string('ses_message_id')->nullable()->unique();
            $table->string('status')->default('pending')->index();
            $table->text('failure_reason')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->timestamp('bounced_at')->nullable();
            $table->timestamp('complained_at')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->timestamps();
            $table->unique(['marketing_campaign_id', 'email'], 'mkt_campaign_recipient_email_unique');
        });

        Schema::create('marketing_campaign_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketing_campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('marketing_campaign_recipient_id')->nullable();
            $table->foreign('marketing_campaign_recipient_id', 'mkt_event_recipient_fk')
                ->references('id')->on('marketing_campaign_recipients')->nullOnDelete();
            $table->string('event_type')->index();
            $table->timestamp('occurred_at')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('marketing_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider')->default('ses_sns');
            $table->string('provider_message_id')->nullable()->unique();
            $table->string('event_type')->nullable();
            $table->json('payload');
            $table->timestamp('received_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_webhook_events');
        Schema::dropIfExists('marketing_campaign_events');
        Schema::dropIfExists('marketing_campaign_recipients');
        Schema::dropIfExists('marketing_campaigns');
        Schema::dropIfExists('marketing_templates');
        Schema::dropIfExists('marketing_list_members');
        Schema::dropIfExists('marketing_contact_sources');
        Schema::dropIfExists('marketing_lists');
        Schema::dropIfExists('marketing_contacts');
    }
};
