<?php

namespace Tests\Feature;

use App\Models\{AppMailLog, EmailSuppression, RegistrationFollowup, RegistrationFollowupState, User};
use App\Services\{EmailDeliveryPolicy, RegistrationFollowupService};
use Carbon\Carbon;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\{Mail, Schema};
use Tests\TestCase;

class EmailDeliveryAndFollowupsTest extends TestCase
{
    use \Tests\Concerns\RegistrationSchema;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-28T16:00:00Z'));
        config(['mail.default' => 'app-mail', 'app_mail.original_mailer' => 'array', 'mail.from.address' => 'app@example.test']);
        $this->createRegistrationSchema();
        $columns = Schema::getColumnListing('users');
        $this->artisan('migrate', ['--path' => [
            'database/migrations/2025_12_29_094324_create_registration_followups_table.php',
            'database/migrations/2026_11_27_190000_create_app_mail_logs_table.php',
            'database/migrations/2026_11_27_200000_add_email_suppressions_and_followup_limits.php',
        ]])->assertExitCode(0);
        $this->assertSame($columns, Schema::getColumnListing('users'));
    }

    protected function tearDown(): void { Carbon::setTestNow(); parent::tearDown(); }

    private function client(): User
    {
        return User::factory()->create(['pay' => 0, 'email' => 'person@example.test', 'nombres' => 'Persona', 'apellidos' => 'Prueba']);
    }

    private function bounce(string $type, string $email = 'person@example.test'): array
    {
        return ['eventType' => 'Bounce', 'mail' => ['messageId' => 'ses-id', 'timestamp' => now()->toIso8601String()],
            'bounce' => ['bounceType' => $type, 'timestamp' => now()->toIso8601String(), 'bouncedRecipients' => [['emailAddress' => $email]]]];
    }

    public function test_a_permanent_bounce_blocks_that_address_without_restricting_the_account(): void
    {
        $user = $this->client();
        app(EmailDeliveryPolicy::class)->recordSesEvent($this->bounce('Permanent'), [$user->email]);
        $this->assertDatabaseHas('email_suppressions', ['email' => $user->email, 'reason' => 'hard_bounce']);
        $this->assertFalse($user->isClientAppAccessBlocked());
        $sent = Mail::raw('Must not be sent', fn ($message) => $message->to($user->email));
        $this->assertNull($sent); $this->assertDatabaseCount('app_mail_logs', 0);
        $user->update(['email' => 'corrected@example.test']);
        Mail::raw('Can be sent', fn ($message) => $message->to($user->email));
        $this->assertSame(['corrected@example.test'], AppMailLog::sole()->recipients);
    }

    public function test_a_transient_bounce_does_not_permanently_block_and_unrelated_addresses_are_ignored(): void
    {
        app(EmailDeliveryPolicy::class)->recordSesEvent($this->bounce('Transient'), ['person@example.test']);
        app(EmailDeliveryPolicy::class)->recordSesEvent($this->bounce('Permanent', 'unrelated@example.test'), ['person@example.test']);
        $this->assertDatabaseCount('email_suppressions', 0);
    }

    public function test_a_bounced_address_is_removed_without_stopping_valid_recipients_of_the_same_message(): void
    {
        app(EmailDeliveryPolicy::class)->recordSesEvent($this->bounce('Permanent'), ['person@example.test']);
        Mail::raw('Informe interno', fn ($message) => $message->to('person@example.test')->cc('team@example.test')->bcc('audit@example.test'));
        $this->assertSame(['team@example.test', 'audit@example.test'], AppMailLog::sole()->recipients);
        $message = app(\Illuminate\Mail\MailManager::class)->mailer('array')->getSymfonyTransport()->messages()->sole()->getOriginalMessage();
        $this->assertSame([], $message->getTo());
        $this->assertSame('team@example.test', $message->getCc()[0]->getAddress());
    }

    public function test_a_complaint_blocks_future_mail_and_an_old_event_does_not_undo_a_manual_review(): void
    {
        $event = ['eventType' => 'Complaint', 'mail' => ['messageId' => 'ses-id', 'timestamp' => now()->toIso8601String()],
            'complaint' => ['timestamp' => now()->toIso8601String(), 'complainedRecipients' => [['emailAddress' => 'person@example.test']]]];
        $policy = app(EmailDeliveryPolicy::class); $policy->recordSesEvent($event, ['person@example.test']);
        $this->assertTrue($policy->blocked('PERSON@example.test'));
        Carbon::setTestNow(now()->addHour());
        EmailSuppression::sole()->update(['cleared_at' => now(), 'cleared_by' => 1]);
        $policy->recordSesEvent($event, ['person@example.test']);
        $this->assertFalse($policy->blocked('person@example.test'));
    }

    public function test_only_three_payment_reminders_are_sent_and_password_recovery_stays_available(): void
    {
        $user = $this->client();
        for ($i = 0; $i < 5; $i++) {
            $this->artisan('followups:registration-payment')->assertExitCode(0);
            $this->artisan('followups:registration-payment')->assertExitCode(0);
            Carbon::setTestNow(now()->addDays(15));
        }
        $this->assertDatabaseCount('registration_followups', 3);
        $this->assertSame('limit_reached', RegistrationFollowupState::sole()->stop_reason);
        $this->assertSame([1, 2, 3], RegistrationFollowup::orderBy('id')->pluck('sequence')->all());
        $this->assertDatabaseCount('app_mail_logs', 3);
        $this->assertDatabaseCount('email_suppressions', 0);
        $user->notify(new ResetPassword('private-token'));
        $this->assertDatabaseCount('app_mail_logs', 4);
    }

    public function test_payment_or_a_recorded_response_stops_reminders_immediately(): void
    {
        $user = $this->client();
        $user->forceFill(['pay' => 1])->save();
        $this->artisan('followups:registration-payment')->assertExitCode(0);
        $this->assertDatabaseCount('registration_followups', 0);
        $user->forceFill(['pay' => 0])->save();
        app(RegistrationFollowupService::class)->stopForResponse($user, 42);
        $this->artisan('followups:registration-payment')->assertExitCode(0);
        $this->assertDatabaseCount('registration_followups', 0);
        $this->assertSame(42, (int) RegistrationFollowupState::sole()->updated_by);
    }

    public function test_historical_reminders_are_counted_and_dry_run_does_not_write_or_send(): void
    {
        $user = $this->client();
        $this->artisan('followups:registration-payment', ['--dry-run' => true])->assertExitCode(0);
        $this->assertDatabaseCount('registration_followup_states', 0); $this->assertDatabaseCount('app_mail_logs', 0);
        foreach ([1, 2, 3] as $i) RegistrationFollowup::create(['user_id' => $user->id, 'sequence' => $i,
            'scheduled_for' => now()->subDays($i)->toDateString(), 'sent_at' => now()->subDays($i), 'subject' => 'Historical']);
        (require database_path('migrations/2026_11_27_200000_add_email_suppressions_and_followup_limits.php'))->up();
        $this->artisan('followups:registration-payment')->assertExitCode(0);
        $this->assertDatabaseCount('registration_followups', 3);
        $this->assertSame('limit_reached', RegistrationFollowupState::sole()->stop_reason);
    }

    public function test_a_blocked_recipient_is_not_counted_as_a_successful_reminder(): void
    {
        $user = $this->client();
        app(EmailDeliveryPolicy::class)->recordSesEvent($this->bounce('Permanent'), [$user->email]);
        $this->artisan('followups:registration-payment')->assertExitCode(0);
        $this->assertDatabaseCount('registration_followups', 0);
        $this->assertDatabaseCount('app_mail_logs', 0);
    }

    public function test_a_failed_or_cancelled_send_does_not_consume_the_three_successful_reminders(): void
    {
        $user = $this->client();
        $service = app(RegistrationFollowupService::class);
        Mail::shouldReceive('to')->once()->with($user->email)->andThrow(new \RuntimeException('Provider failed'));
        $this->assertFalse($service->send($user, now()));
        $this->assertSame('failed', RegistrationFollowup::sole()->status);
        $this->assertTrue($service->eligible($user));
        Mail::shouldReceive('to')->once()->with($user->email)->andReturnSelf();
        Mail::shouldReceive('send')->once()->andReturnNull();
        $this->assertFalse($service->send($user, now()->addDays(15)));
        $this->assertSame(['failed', 'skipped'], RegistrationFollowup::orderBy('id')->pluck('status')->all());
        $this->assertTrue($service->eligible($user));
        $this->assertNull(RegistrationFollowupState::sole()->stopped_at);
    }

    public function test_unsent_legacy_rows_do_not_count_but_reserved_sends_prevent_a_fourth_email(): void
    {
        $user = $this->client();
        foreach ([1, 2, 3] as $i) RegistrationFollowup::create(['user_id' => $user->id, 'sequence' => $i,
            'scheduled_for' => now()->subDays($i)->toDateString(), 'subject' => 'Legacy unsent', 'sent_at' => null, 'status' => 'sent']);
        $service = app(RegistrationFollowupService::class);
        $this->assertTrue($service->eligible($user));
        RegistrationFollowup::query()->update(['status' => 'sending']);
        $this->assertFalse($service->eligible($user));
        $this->assertFalse($service->send($user, now()));
        $this->assertDatabaseCount('app_mail_logs', 0);
    }

    public function test_a_response_recorded_in_a_task_stops_the_followup_and_admin_actions_are_protected(): void
    {
        Schema::create('tasks', function (\Illuminate\Database\Schema\Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('contact_id'); $table->boolean('customer_responded')->nullable(); $table->timestamps();
        });
        $user = $this->client();
        $task = \App\Models\Task::create(['contact_id' => $user->id]);
        $task->update(['customer_responded' => true]);
        $this->assertSame('responded', RegistrationFollowupState::sole()->stop_reason);
        $this->assertFalse(app(RegistrationFollowupService::class)->eligible($user));
        app(EmailDeliveryPolicy::class)->recordSesEvent($this->bounce('Permanent'), [$user->email]);
        $suppression = EmailSuppression::sole();
        $this->actingAs($user)->post(route('admin.integrations.app-mail.suppressions.clear', $suppression))->assertForbidden();
        $this->post(route('admin.integrations.app-mail.response', $user))->assertForbidden();
        \Spatie\Permission\Models\Role::findOrCreate('Administrador');
        $admin = tap(User::factory()->create(['email' => 'admin@example.test']), fn ($admin) => $admin->syncRoles(['Administrador']));
        $this->actingAs($admin)->post(route('admin.integrations.app-mail.suppressions.clear', $suppression))->assertRedirect();
        $this->assertFalse(app(EmailDeliveryPolicy::class)->blocked($user->email));
        $this->assertSame($admin->id, $suppression->fresh()->cleared_by);
    }
}
