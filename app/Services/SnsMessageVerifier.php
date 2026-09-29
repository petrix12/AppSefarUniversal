<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class SnsMessageVerifier
{
    public function verify(array $message): bool
    {
        $allowedTopics = config('marketing.ses_sns_topic_arns', []);
        if (!$allowedTopics || !in_array($message['TopicArn'] ?? '', $allowedTopics, true)) {
            return false;
        }
        if (!in_array($message['Type'] ?? '', ['Notification', 'SubscriptionConfirmation', 'UnsubscribeConfirmation'], true)
            || !in_array((string) ($message['SignatureVersion'] ?? ''), ['1', '2'], true)
            || empty($message['Signature']) || !$this->isValidCertificateUrl((string) ($message['SigningCertURL'] ?? ''), (string) $message['TopicArn'])) {
            return false;
        }

        try {
            $certificateUrl = (string) $message['SigningCertURL'];
            $certificate = Cache::remember('marketing:sns-cert:'.hash('sha256', $certificateUrl), now()->addDay(), function () use ($certificateUrl) {
                return Http::timeout(5)->get($certificateUrl)->throw()->body();
            });
            $algorithm = $message['SignatureVersion'] === '1' ? OPENSSL_ALGO_SHA1 : OPENSSL_ALGO_SHA256;

            return openssl_verify(
                $this->stringToSign($message),
                base64_decode((string) $message['Signature'], true) ?: '',
                $certificate,
                $algorithm
            ) === 1;
        } catch (\Throwable) {
            return false;
        }
    }

    private function stringToSign(array $message): string
    {
        $fields = $message['Type'] === 'Notification'
            ? ['Message', 'MessageId', 'Subject', 'Timestamp', 'TopicArn', 'Type']
            : ['Message', 'MessageId', 'SubscribeURL', 'Timestamp', 'Token', 'TopicArn', 'Type'];
        $result = '';
        foreach ($fields as $field) {
            if (array_key_exists($field, $message)) {
                $result .= $field."\n".$message[$field]."\n";
            }
        }
        return $result;
    }

    private function isValidCertificateUrl(string $url, string $topicArn): bool
    {
        $parts = parse_url($url);
        if (($parts['scheme'] ?? '') !== 'https' || isset($parts['port']) || !str_ends_with($parts['path'] ?? '', '.pem')) {
            return false;
        }
        $topicParts = explode(':', $topicArn);
        $region = $topicParts[3] ?? '';
        return $region !== '' && ($parts['host'] ?? '') === "sns.{$region}.amazonaws.com";
    }
}
