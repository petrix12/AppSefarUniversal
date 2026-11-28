<?php

namespace App\Mail\Transports;

use Aws\SesV2\SesV2Client;
use Symfony\Component\Mailer\Header\MetadataHeader;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Message;

class AppSesTransport extends AbstractTransport
{
    public function __construct(private SesV2Client $client, private ?string $configurationSet = null)
    {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        $payload = [
            'FromEmailAddress' => $message->getEnvelope()->getSender()->toString(),
            'Destination' => ['ToAddresses' => array_map(fn ($address) => $address->getAddress(), $message->getEnvelope()->getRecipients())],
            'Content' => ['Raw' => ['Data' => $message->toString()]],
        ];
        if (filled($this->configurationSet)) $payload['ConfigurationSetName'] = $this->configurationSet;
        if ($message->getOriginalMessage() instanceof Message) {
            foreach ($message->getOriginalMessage()->getHeaders()->all() as $header) {
                if ($header instanceof MetadataHeader) $payload['EmailTags'][] = ['Name' => $header->getKey(), 'Value' => $header->getValue()];
            }
        }
        $result = $this->client->sendEmail($payload);
        $messageId = $result->get('MessageId');
        if (!is_string($messageId) || trim($messageId) === '') {
            throw new \Symfony\Component\Mailer\Exception\TransportException('Amazon SES no confirmó la aceptación del correo.');
        }
        $message->setMessageId($messageId);
    }

    public function __toString(): string
    {
        return 'app-ses';
    }
}
