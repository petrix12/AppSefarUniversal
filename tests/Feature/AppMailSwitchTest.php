<?php

namespace Tests\Feature;

use App\Mail\Transports\AppMailTransport;
use App\Mail\Transports\AppSesTransport;
use App\Models\{AppMailLog, Setting, User};
use App\Services\{AppMailSesFactory, AppMailSettings};
use Aws\{Command, MockHandler, Result};
use Aws\Exception\AwsException;
use Aws\SesV2\SesV2Client;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Mail\{MailManager, Mailable, SendQueuedMailable};
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Facades\{DB, Http, Mail};
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

class AppMailSwitchTest extends TestCase
{
    use \Tests\Concerns\RegistrationSchema;

    private ArrayTransport $sesTransport;

    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array', 'session.driver' => 'array',
            'app_mail.original_mailer' => 'array', 'mail.default' => 'app-mail',
            'mail.from.address' => 'app@original.test', 'mail.from.name' => 'App Sefar',
            'app_mail.ses' => ['key' => 'ses-test-key', 'secret' => 'ses-test-secret', 'region' => 'us-east-1',
                'from_email' => 'app@ses.test', 'from_name' => 'App Sefar SES', 'reply_to' => 'support@original.test',
                'configuration_set' => 'transactional-test']]);
        Http::preventStrayRequests();
        $this->createRegistrationSchema();
        $this->artisan('migrate', ['--path' => [
            'database/migrations/2026_05_07_100000_create_settings_table.php',
            'database/migrations/2026_11_27_190000_create_app_mail_logs_table.php',
        ]])->assertExitCode(0);
        $this->sesTransport = new ArrayTransport;
        $this->mock(AppMailSesFactory::class)->shouldReceive('make')->andReturn($this->sesTransport)->byDefault();
    }

    private function administrator(): User
    {
        \Spatie\Permission\Models\Role::findOrCreate('Administrador');
        return tap(User::factory()->create(), fn ($user) => $user->syncRoles(['Administrador']));
    }

    private function testSes(): void
    {
        $this->actingAs($this->administrator())->post(route('admin.integrations.app-mail.test'), [
            'provider' => 'ses', 'recipient' => 'test@example.test',
        ])->assertRedirect(route('admin.integrations.app-mail.index'))->assertSessionHasNoErrors();
    }

    public function test_the_existing_provider_remains_active_until_an_administrator_switches_it(): void
    {
        $this->assertSame('default', app(AppMailSettings::class)->provider());
        Mail::raw('Secret reset token which must not be logged.', fn ($message) => $message
            ->to('client@example.test')->cc('cc@example.test')->bcc('bcc@example.test')->subject('A notification'));

        $this->assertCount(0, $this->sesTransport->messages());
        $log = AppMailLog::sole();
        $this->assertSame('accepted', $log->status);
        $this->assertSame('app@original.test', $log->from_email);
        $this->assertSame(['client@example.test', 'cc@example.test', 'bcc@example.test'], $log->recipients);
        $this->assertStringNotContainsString('Secret reset token', json_encode($log->toArray()));
    }

    public function test_only_internal_administrators_can_view_change_or_test_the_provider(): void
    {
        $this->getJson(route('admin.integrations.app-mail.index'))->assertUnauthorized();
        $client = User::factory()->create();
        $this->actingAs($client)->get(route('admin.integrations.app-mail.index'))->assertForbidden();
        $this->put(route('admin.integrations.app-mail.update'), ['provider' => 'default'])->assertForbidden();
        $this->post(route('admin.integrations.app-mail.test'), ['provider' => 'ses', 'recipient' => 'test@example.test'])->assertForbidden();
        $admin = $this->administrator();
        $this->actingAs($admin)->get(route('admin.integrations.app-mail.index'))->assertOk()
            ->assertSee('Correo de la app')->assertDontSee('ses-test-secret')->assertDontSee('ses-test-key');
        $admin->assignRole('Cliente');
        $this->actingAs($admin)->get(route('admin.integrations.app-mail.index'))->assertForbidden();
    }

    public function test_ses_requires_a_successful_test_and_confirmation_of_receipt(): void
    {
        $this->actingAs($this->administrator());
        $this->put(route('admin.integrations.app-mail.update'), ['provider' => 'ses', 'received_test' => 1])->assertSessionHasErrors('provider');
        $this->testSes();
        $this->assertSame('default', app(AppMailSettings::class)->provider());
        $this->assertTrue(AppMailLog::sole()->is_test);
        $this->put(route('admin.integrations.app-mail.update'), ['provider' => 'ses'])->assertSessionHasErrors('received_test');
        $this->put(route('admin.integrations.app-mail.update'), ['provider' => 'ses', 'received_test' => 1])
            ->assertSessionHasNoErrors()->assertRedirect(route('admin.integrations.app-mail.index'));
        $this->assertSame('ses', app(AppMailSettings::class)->provider());
        $this->put(route('admin.integrations.app-mail.update'), ['provider' => 'default'])->assertSessionHasNoErrors();
        $this->assertSame('default', app(AppMailSettings::class)->provider());
    }

    public function test_changed_credentials_or_an_expired_test_cannot_activate_ses(): void
    {
        $this->testSes();
        config(['app_mail.ses.secret' => 'a-different-secret']);
        $this->put(route('admin.integrations.app-mail.update'), ['provider' => 'ses', 'received_test' => 1])->assertSessionHasErrors('provider');
        config(['app_mail.ses.secret' => 'ses-test-secret']);
        $this->travel(25)->hours();
        $this->put(route('admin.integrations.app-mail.update'), ['provider' => 'ses', 'received_test' => 1])->assertSessionHasErrors('provider');
        $this->travelBack();
        $this->assertSame('default', app(AppMailSettings::class)->provider());
    }

    public function test_the_same_mailer_and_serialized_queue_jobs_use_the_new_provider_and_can_switch_back(): void
    {
        $manager = app(MailManager::class);
        $mailer = $manager->mailer();
        $job = unserialize(serialize(new SendQueuedMailable((new AppMailFixture)->to('queued@example.test')->mailer('app-mail'))));
        $mailer->raw('First', fn ($message) => $message->to('first@example.test'));
        $this->testSes();
        app(AppMailSettings::class)->activate('ses', 1);
        $job->handle($manager);
        $this->assertCount(2, $this->sesTransport->messages());
        $message = $this->sesTransport->messages()->last()->getOriginalMessage();
        $this->assertSame('app@ses.test', $message->getFrom()[0]->getAddress());
        $this->assertSame('support@original.test', $message->getReplyTo()[0]->getAddress());
        app(AppMailSettings::class)->activate('default', 1);
        $mailer->raw('Last', fn ($message) => $message->to('last@example.test'));
        $this->assertSame('default', AppMailLog::latest('id')->first()->provider);
        $this->assertCount(2, $manager->mailer('array')->getSymfonyTransport()->messages());
        $this->assertCount(2, $this->sesTransport->messages());
    }

    public function test_password_reset_notifications_use_the_switch_without_logging_the_token(): void
    {
        $this->testSes(); app(AppMailSettings::class)->activate('ses', 1);
        $user = User::factory()->create(['email' => 'client@example.test']);
        $user->notify(new ResetPassword('private-reset-token'));
        $this->assertCount(2, $this->sesTransport->messages());
        $this->assertSame(['client@example.test'], AppMailLog::latest('id')->first()->recipients);
        $this->assertStringNotContainsString('private-reset-token', json_encode(AppMailLog::all()->toArray()));
    }

    public function test_failed_ses_sends_are_logged_without_secrets_and_are_not_resent_with_the_other_provider(): void
    {
        $this->testSes(); app(AppMailSettings::class)->activate('ses', 1);
        $this->mock(AppMailSesFactory::class)->shouldReceive('make')->once()->andThrow(
            new AwsException('Raw MIME contains private-reset-token and ses-test-secret', new Command('SendEmail'), ['code' => 'MessageRejected'])
        );
        try {
            Mail::raw('private-reset-token', fn ($message) => $message->to('client@example.test'));
            $this->fail('A failed provider must fail the send.');
        } catch (TransportException $exception) {
            $this->assertStringContainsString('MessageRejected', $exception->getMessage());
            $this->assertStringNotContainsString('private-reset-token', $exception->getMessage());
            $this->assertNull($exception->getPrevious());
        }
        $log = AppMailLog::latest('id')->first();
        $this->assertSame('failed', $log->status); $this->assertSame('MessageRejected', $log->error_code);
        $this->assertCount(0, app(MailManager::class)->mailer('array')->getSymfonyTransport()->messages());
    }

    public function test_the_ses_v2_request_preserves_attachments_and_envelope_recipients_and_records_the_provider_id(): void
    {
        $handler = new MockHandler([new Result(['MessageId' => 'ses-provider-id'])]);
        $transport = new AppSesTransport(new SesV2Client(['version' => 'latest', 'region' => 'us-east-1',
            'credentials' => ['key' => 'key', 'secret' => 'secret'], 'handler' => $handler]), 'app-test');
        $this->mock(AppMailSesFactory::class)->shouldReceive('make')->once()->andReturn($transport);
        Setting::set('app_mail.provider', 'ses');
        Mail::html('<p>Receipt</p>', fn ($message) => $message->to('client@example.test')->bcc('audit@example.test')
            ->replyTo('specific@example.test')->subject('Receipt')->attachData('%PDF synthetic fixture', 'receipt.pdf', ['mime' => 'application/pdf']));
        $payload = $handler->getLastCommand()->toArray();
        $this->assertSame('SendEmail', $handler->getLastCommand()->getName());
        $this->assertStringContainsString('app@ses.test', $payload['FromEmailAddress']);
        $this->assertSame(['client@example.test', 'audit@example.test'], $payload['Destination']['ToAddresses']);
        $this->assertSame('app-test', $payload['ConfigurationSetName']);
        $this->assertStringContainsString('receipt.pdf', $payload['Content']['Raw']['Data']);
        $this->assertStringContainsString('specific@example.test', $payload['Content']['Raw']['Data']);
        $this->assertStringNotContainsString('Bcc:', $payload['Content']['Raw']['Data']);
        $this->assertSame([['Name' => 'app_mail_log_id', 'Value' => (string) AppMailLog::sole()->id]], $payload['EmailTags']);
        $this->assertSame('ses-provider-id', AppMailLog::sole()->provider_message_id);
    }

    public function test_missing_ses_configuration_never_sends_a_test_or_enables_ses(): void
    {
        config(['app_mail.ses.secret' => null]);
        $this->actingAs($this->administrator())->post(route('admin.integrations.app-mail.test'), ['provider' => 'ses', 'recipient' => 'test@example.test'])
            ->assertSessionHasErrors('provider');
        $this->put(route('admin.integrations.app-mail.update'), ['provider' => 'ses', 'received_test' => 1])->assertSessionHasErrors('provider');
        $this->assertDatabaseCount('app_mail_logs', 0); $this->assertCount(0, $this->sesTransport->messages());
    }
}

class AppMailFixture extends Mailable
{
    public function build(): self { return $this->subject('Queued notification')->html('<p>Queue fixture</p>'); }
}
