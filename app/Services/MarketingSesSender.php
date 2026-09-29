<?php

namespace App\Services;

use App\Models\MarketingCampaign;
use Aws\SesV2\SesV2Client;

class MarketingSesSender
{
    public function send(MarketingCampaign $campaign, string $recipientEmail, array $rendered): string
    {
        $settings = config('marketing.ses');
        if (blank($settings['key']) || blank($settings['secret']) || blank($settings['from_email'])) {
            throw new \RuntimeException('Amazon SES de campañas no está configurado. Completa las variables MARKETING_SES_* antes de enviar.');
        }

        $from = $this->formatAddress($campaign->from_email, $campaign->from_name);
        $client = new SesV2Client([
            'version' => 'latest',
            'region' => $settings['region'],
            'credentials' => ['key' => $settings['key'], 'secret' => $settings['secret']],
        ]);
        $payload = [
            'FromEmailAddress' => $from,
            'Destination' => ['ToAddresses' => [$recipientEmail]],
            'Content' => [
                'Simple' => [
                    'Subject' => ['Data' => $rendered['subject'] ?? $campaign->subject, 'Charset' => 'UTF-8'],
                    'Body' => [
                        'Html' => ['Data' => $rendered['html'], 'Charset' => 'UTF-8'],
                        'Text' => ['Data' => $rendered['text'], 'Charset' => 'UTF-8'],
                    ],
                ],
            ],
            'EmailTags' => [
                ['Name' => 'marketing_campaign_id', 'Value' => (string) $campaign->id],
                ['Name' => 'marketing_recipient_token', 'Value' => (string) data_get($rendered, 'recipient_token')],
            ],
        ];
        if (filled($campaign->reply_to)) {
            $payload['ReplyToAddresses'] = [$campaign->reply_to];
        }
        if (filled($settings['configuration_set'])) {
            $payload['ConfigurationSetName'] = $settings['configuration_set'];
        }

        return (string) $client->sendEmail($payload)->get('MessageId');
    }

    public function sendPreview(MarketingCampaign $campaign, string $email, array $rendered): void
    {
        $this->send($campaign, $email, $rendered + ['recipient_token' => 'preview']);
    }

    private function formatAddress(string $email, ?string $name): string
    {
        $name = trim((string) $name);
        return $name === '' ? $email : '"'.str_replace(['"', "\r", "\n"], '', $name).'" <'.$email.'>';
    }
}
