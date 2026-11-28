<?php

namespace App\Mail\Transports;

use App\Models\AppMailLog;
use App\Services\AppMailSesFactory;
use App\Services\AppMailSettings;
use Aws\Exception\AwsException;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Header\MetadataHeader;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;

class AppMailTransport implements TransportInterface
{
    public ?AppMailLog $lastLog = null;

    public function __construct(private ?string $providerOverride = null, private bool $isTest = false) {}

    public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
    {
        $settings = app(AppMailSettings::class);
        // Resolve on every send, including long-lived queue workers.
        $provider = $this->providerOverride ?? $settings->provider();
        $message = clone $message;
        $delivery = app(\App\Services\EmailDeliveryPolicy::class);
        if ($message instanceof Email && ! $delivery->filterRecipients($message)) return null;
        $envelope ??= Envelope::create($message);
        $recipients = array_values(array_filter($envelope->getRecipients(), fn ($recipient) => ! $delivery->blocked($recipient->getAddress())));
        if (! $recipients) return null;
        $envelope = new Envelope($envelope->getSender(), $recipients);
        if ($provider === 'ses') {
            if (! $settings->sesReady() || ! $message instanceof Email) throw new TransportException('La configuración de Amazon SES necesita revisión.');
            $ses = $settings->ses();
            $sender = new Address($ses['from_email'], $ses['from_name'] ?? '');
            $message->from($sender);
            $message->getHeaders()->remove('Sender');
            $message->getHeaders()->remove('Return-Path');
            if (! $message->getReplyTo() && filled($ses['reply_to'] ?? null)) $message->replyTo($ses['reply_to']);
            $envelope = new Envelope($sender, $envelope->getRecipients());
        }
        $log = $this->lastLog = Schema::hasTable('app_mail_logs') ? AppMailLog::create([
            'provider' => $provider, 'from_email' => $envelope->getSender()->getAddress(),
            'recipients' => array_map(fn ($address) => $address->getAddress(), $envelope->getRecipients()),
            'subject' => $message instanceof Email ? Str::limit($message->getSubject() ?? '', 250, '') : null,
            'is_test' => $this->isTest, 'status' => 'sending',
        ]) : null;
        if ($provider === 'ses' && $log && $message instanceof Email) {
            $message->getHeaders()->add(new MetadataHeader('app_mail_log_id', (string) $log->id));
        }
        try {
            $transport = $provider === 'ses' ? app(AppMailSesFactory::class)->make()
                : app(MailManager::class)->mailer($settings->originalMailer())->getSymfonyTransport();
            $sent = $transport->send($message, $envelope);
            if (! $sent) throw new TransportException('El proveedor no confirmó la aceptación del correo.');
        } catch (\Throwable $exception) {
            $code = 'transport_error';
            for ($cause = $exception; $cause; $cause = $cause->getPrevious()) {
                if ($cause instanceof AwsException) $code = preg_replace('/[^A-Za-z0-9_]/', '', $cause->getAwsErrorCode() ?: 'ses_error');
            }
            $this->recordOutcome($log, ['status' => 'failed', 'error_code' => Str::limit($code, 100, '')]);
            // SDK/SMTP exception strings can include raw MIME content or credentials.
            // Do not attach the original exception or automatically resend elsewhere.
            throw new TransportException('No se pudo enviar el correo con el proveedor seleccionado ('.$code.').');
        }
        $this->recordOutcome($log, ['provider_message_id' => $sent->getMessageId(), 'accepted_at' => now()]);
        return $sent;
    }

    private function recordOutcome(?AppMailLog $log, array $values): void
    {
        if (! $log) return;
        try {
            $log->getConnection()->transaction(function () use ($log, $values) {
                $current = AppMailLog::whereKey($log->id)->lockForUpdate()->firstOrFail();
                if (isset($values['accepted_at']) && $current->status === 'sending') $values['status'] = 'accepted';
                $current->update($values);
            });
        } catch (\Throwable) {
            // Once accepted, a logging failure must not cause a duplicate send.
            Log::warning('No se pudo actualizar el historial de correo.', ['mail_log_id' => $log->id]);
        }
    }

    public function __toString(): string
    {
        return 'app-mail';
    }
}
