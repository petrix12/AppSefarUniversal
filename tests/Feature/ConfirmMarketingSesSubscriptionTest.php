<?php

namespace Tests\Feature;

use App\Models\MarketingWebhookEvent;
use App\Services\SnsMessageVerifier;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\ConnectionException;
use Tests\TestCase;

class ConfirmMarketingSesSubscriptionTest extends TestCase
{
    private string $topic = 'arn:aws:sns:us-east-1:123456789012:marketing';
    private array $payload;

    protected function setUp(): void
    {
        parent::setUp();
        $this->payload = ['Type' => 'SubscriptionConfirmation', 'MessageId' => 'event-123', 'TopicArn' => $this->topic,
            'Token' => 'secret-token', 'SubscribeURL' => 'https://sns.us-east-1.amazonaws.com/?Action=ConfirmSubscription&TopicArn='.rawurlencode($this->topic).'&Token=secret-token'];
        config(['marketing.ses_sns_topic_arns' => [$this->topic]]);
        Http::preventStrayRequests();
        $this->artisan('migrate', ['--path' => ['database/migrations/2026_09_29_120000_create_marketing_campaign_tables.php']])->assertExitCode(0);
    }

    private function event(?array $payload = null): MarketingWebhookEvent
    {
        return MarketingWebhookEvent::create(['provider' => 'ses_sns', 'provider_message_id' => 'event-123',
            'event_type' => 'SubscriptionConfirmation', 'payload' => $payload ?? $this->payload, 'received_at' => now()]);
    }

    private function verifier(bool $result = true): void
    {
        $this->mock(SnsMessageVerifier::class)->shouldReceive('verify')->once()->andReturn($result);
    }

    public function test_dry_run_verifies_event_without_sending_request(): void
    {
        $event = $this->event(); $this->verifier();
        $this->artisan('marketing:confirm-sns')->expectsOutput("dry-run topic={$this->topic} event={$event->id}")->assertExitCode(0);
        Http::assertNothingSent();
    }

    public function test_missing_event_or_allowlist_is_read_only(): void
    {
        $this->artisan('marketing:confirm-sns')->expectsOutput('Sin evento pendiente.')->assertExitCode(0);
        config(['marketing.ses_sns_topic_arns' => []]);
        $this->artisan('marketing:confirm-sns')->assertExitCode(1);
        Http::assertNothingSent();
    }

    public function test_rejects_unallowed_topic_and_mismatched_url(): void
    {
        $this->artisan('marketing:confirm-sns', ['--topic' => 'arn:aws:sns:us-east-1:123456789012:other'])->assertExitCode(1);
        $payload = $this->payload; $payload['SubscribeURL'] = 'https://evil.example/?Action=ConfirmSubscription';
        $this->event($payload); $this->verifier();
        $this->artisan('marketing:confirm-sns')->assertExitCode(1);
        Http::assertNothingSent();
    }

    public function test_rejects_query_token_mismatch_and_non_string_payload_fields(): void
    {
        $payload = $this->payload; $payload['SubscribeURL'] = str_replace('Token=secret-token', 'Token=other-token', $payload['SubscribeURL']);
        $this->event($payload); $this->verifier();
        $this->artisan('marketing:confirm-sns')->assertExitCode(1);
        Http::assertNothingSent();

        MarketingWebhookEvent::query()->delete();
        $payload['Token'] = ['not-a-string']; $this->event($payload);
        $this->mock(SnsMessageVerifier::class)->shouldNotReceive('verify');
        $this->artisan('marketing:confirm-sns')->assertExitCode(1);
    }

    public function test_rejects_invalid_signature_without_request(): void
    {
        $this->event(); $this->verifier(false);
        $this->artisan('marketing:confirm-sns')->expectsOutput('Firma SNS inválida.')->assertExitCode(1);
        Http::assertNothingSent();
    }

    public function test_handles_verifier_exception_without_leaking_details(): void
    {
        $this->event();
        $this->mock(SnsMessageVerifier::class)->shouldReceive('verify')->once()->andThrow(new \RuntimeException('secret-token'));
        $this->artisan('marketing:confirm-sns')->expectsOutput('No se pudo verificar la firma SNS.')->assertExitCode(1);
        Http::assertNothingSent();
    }

    public function test_rejects_redirect(): void
    {
        $this->event(); $this->verifier();
        Http::fake(['sns.us-east-1.amazonaws.com/*' => Http::response('', 302, ['Location' => 'https://evil.example'])]);
        $this->artisan('marketing:confirm-sns', ['--write' => true])->assertExitCode(1);
        Http::assertSentCount(1);
    }

    public function test_accepts_successful_confirmation_response(): void
    {
        $event = $this->event();
        $arn = $this->topic.':123e4567-e89b-12d3-a456-426614174000';
        $xml = '<ConfirmSubscriptionResponse><ConfirmSubscriptionResult><SubscriptionArn>'.$arn.'</SubscriptionArn></ConfirmSubscriptionResult></ConfirmSubscriptionResponse>';
        Http::fakeSequence('sns.us-east-1.amazonaws.com/*')->push($xml, 200)->push($xml, 200);
        $this->mock(SnsMessageVerifier::class)->shouldReceive('verify')->twice()->andReturn(true);
        foreach ([1, 2] as $_) {
            $this->artisan('marketing:confirm-sns', ['--write' => true])->expectsOutput("Confirmada {$this->topic} {$event->id} {$arn}")->assertExitCode(0);
        }
        Http::assertSentCount(2);
    }

    public function test_rejects_invalid_xml_root_and_subscription_arn(): void
    {
        $this->event();
        $xmlBodies = [
            '<broken',
            '<OtherResponse><SubscriptionArn>'.$this->topic.':123e4567-e89b-12d3-a456-426614174000</SubscriptionArn></OtherResponse>',
            '<ConfirmSubscriptionResponse><ConfirmSubscriptionResult><SubscriptionArn>'.$this->topic.':not-a-uuid</SubscriptionArn></ConfirmSubscriptionResult></ConfirmSubscriptionResponse>',
        ];
        $sequence = Http::fakeSequence('sns.us-east-1.amazonaws.com/*');
        foreach ($xmlBodies as $body) $sequence->push($body, 200);
        foreach ($xmlBodies as $_) {
            $this->mock(SnsMessageVerifier::class)->shouldReceive('verify')->once()->andReturn(true);
            $this->artisan('marketing:confirm-sns', ['--write' => true])->assertExitCode(1);
        }
        Http::assertSentCount(3);
    }

    public function test_accepts_official_http_and_https_namespaces_but_rejects_an_unrelated_namespace(): void
    {
        $event = $this->event();
        $arn = $this->topic.':123e4567-e89b-12d3-a456-426614174000';
        $responses = [
            '<ConfirmSubscriptionResponse xmlns="http://sns.amazonaws.com/doc/2010-03-31/"><ConfirmSubscriptionResult><SubscriptionArn>'.$arn.'</SubscriptionArn></ConfirmSubscriptionResult></ConfirmSubscriptionResponse>',
            '<ConfirmSubscriptionResponse xmlns="https://sns.amazonaws.com/doc/2010-03-31/"><ConfirmSubscriptionResult><SubscriptionArn>'.$arn.'</SubscriptionArn></ConfirmSubscriptionResult></ConfirmSubscriptionResponse>',
            '<ConfirmSubscriptionResponse xmlns="https://example.test/sns"><ConfirmSubscriptionResult><SubscriptionArn>'.$arn.'</SubscriptionArn></ConfirmSubscriptionResult></ConfirmSubscriptionResponse>',
        ];
        $sequence = Http::fakeSequence('sns.us-east-1.amazonaws.com/*');
        foreach ($responses as $body) $sequence->push($body, 200);
        $this->mock(SnsMessageVerifier::class)->shouldReceive('verify')->times(3)->andReturn(true);
        $this->artisan('marketing:confirm-sns', ['--write' => true])->expectsOutput("Confirmada {$this->topic} {$event->id} {$arn}")->assertExitCode(0);
        $this->artisan('marketing:confirm-sns', ['--write' => true])->expectsOutput("Confirmada {$this->topic} {$event->id} {$arn}")->assertExitCode(0);
        $this->artisan('marketing:confirm-sns', ['--write' => true])->assertExitCode(1);
        Http::assertSentCount(3);
    }

    public function test_handles_http_timeout_without_leaking_request_details(): void
    {
        $this->event(); $this->verifier();
        $attempted = false;
        Http::fake(function () use (&$attempted) { $attempted = true; throw new ConnectionException('secret-token'); });
        $this->artisan('marketing:confirm-sns', ['--write' => true])->expectsOutput('Falló la solicitud de confirmación SNS.')->assertExitCode(1);
        $this->assertTrue($attempted);
    }
}
