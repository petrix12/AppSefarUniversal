<?php

namespace Tests\Unit;

use App\Services\SnsMessageVerifier;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SnsMessageVerifierTest extends TestCase
{
    public function test_it_verifies_a_signed_confirmation_without_following_certificate_redirects(): void
    {
        $topic = 'arn:aws:sns:us-east-1:123456789012:marketing';
        config()->set('marketing.ses_sns_topic_arns', [$topic]);
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $publicKey = openssl_pkey_get_details($key)['key'];
        $message = [
            'Type' => 'SubscriptionConfirmation',
            'Message' => 'Subscription confirmation fixture',
            'MessageId' => 'test-message',
            'SubscribeURL' => 'https://sns.us-east-1.amazonaws.com/?Action=ConfirmSubscription',
            'Timestamp' => '2026-11-22T12:00:00Z',
            'Token' => 'test-token',
            'TopicArn' => $topic,
            'SignatureVersion' => '2',
            'SigningCertURL' => 'https://sns.us-east-1.amazonaws.com/'.uniqid('signature-test-', true).'.pem',
        ];
        $canonical = '';
        foreach (['Message', 'MessageId', 'SubscribeURL', 'Timestamp', 'Token', 'TopicArn', 'Type'] as $field) {
            $canonical .= $field."\n".$message[$field]."\n";
        }
        openssl_sign($canonical, $signature, $key, OPENSSL_ALGO_SHA256);
        $message['Signature'] = base64_encode($signature);
        Http::preventStrayRequests();
        Http::fake(function (Request $request, array $options) use ($publicKey, $message) {
            $this->assertSame($message['SigningCertURL'], $request->url());
            $this->assertFalse($options['allow_redirects']);

            return Http::response($publicKey);
        });

        $verifier = new SnsMessageVerifier();
        $this->assertTrue($verifier->verify($message));
        $message['Token'] = 'tampered-token';
        $this->assertFalse($verifier->verify($message));
        Http::assertSentCount(1);
    }
}
