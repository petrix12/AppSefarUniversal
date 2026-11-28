<?php

namespace App\Console\Commands;

use App\Models\MarketingWebhookEvent;
use App\Services\SnsMessageVerifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Throwable;

class ConfirmMarketingSesSubscription extends Command
{
    protected $signature = 'marketing:confirm-sns {--topic=} {--write}';
    protected $aliases = ['app-mail:confirm-sns'];
    protected $description = 'Inspecciona o confirma una suscripción de Amazon SNS para correos de la app o campañas';

    public function handle(SnsMessageVerifier $verifier): int
    {
        $allowed = array_values(array_unique(array_filter(array_merge(
            (array) config('marketing.ses_sns_topic_arns', []), (array) config('app_mail.ses_sns_topic_arns', [])
        ), 'is_string')));
        $topic = $this->option('topic');
        if ($topic !== null && $topic !== '') {
            if (!in_array($topic, $allowed, true)) {
                $this->error('Topic no permitido.');
                return self::FAILURE;
            }
        } elseif (count($allowed) === 1) {
            $topic = $allowed[0];
        } else {
            $this->error('Indique un topic permitido con --topic.');
            return self::FAILURE;
        }

        $event = MarketingWebhookEvent::query()->where('provider', 'ses_sns')
            ->where('event_type', 'SubscriptionConfirmation')
            ->where('payload->TopicArn', $topic)->latest('id')->first();
        if (!$event) {
            $this->line('Sin evento pendiente.');
            return self::SUCCESS;
        }
        $message = $event->payload;
        if (!is_array($message) || ($message['Type'] ?? null) !== 'SubscriptionConfirmation'
            || !$this->hasNonEmptyStrings($message, ['MessageId', 'TopicArn', 'Token', 'SubscribeURL'])
            || $message['TopicArn'] !== $topic) {
            $this->error('Evento de confirmación inválido.');
            return self::FAILURE;
        }
        try {
            if (!$verifier->verify($message, $allowed)) {
                $this->error('Firma SNS inválida.');
                return self::FAILURE;
            }
        } catch (Throwable) {
            $this->error('No se pudo verificar la firma SNS.');
            return self::FAILURE;
        }

        $url = $message['SubscribeURL'];
        if (!$this->validSubscribeUrl($url, $topic, $message['Token'])) {
            $this->error('URL de confirmación no permitida.');
            return self::FAILURE;
        }
        if (!$this->option('write')) {
            $this->info("dry-run topic={$topic} event={$event->id}");
            return self::SUCCESS;
        }

        try {
            $response = Http::timeout(10)->withOptions(['allow_redirects' => false])->get($url);
            if (!$response->successful() || !$this->validResponse($response->body(), $topic)) {
                $this->error('SNS no confirmó la suscripción.');
                return self::FAILURE;
            }
            $subscriptionArn = $this->subscriptionArn($response->body(), $topic);
            $this->info("Confirmada {$topic} {$event->id} {$subscriptionArn}");
            return self::SUCCESS;
        } catch (Throwable) {
            $this->error('Falló la solicitud de confirmación SNS.');
            return self::FAILURE;
        }
    }

    private function hasNonEmptyStrings(array $message, array $keys): bool
    {
        foreach ($keys as $key) {
            if (!isset($message[$key]) || !is_string($message[$key]) || $message[$key] === '') {
                return false;
            }
        }
        return true;
    }

    private function validSubscribeUrl(string $url, string $topic, string $token): bool
    {
        $parts = parse_url($url);
        $arn = explode(':', $topic);
        if (count($arn) < 6 || ($parts['scheme'] ?? null) !== 'https'
            || ($parts['host'] ?? null) !== 'sns.'.$arn[3].'.amazonaws.com'
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])
            || isset($parts['fragment']) || ($parts['path'] ?? '') !== '/' || !isset($parts['query'])) {
            return false;
        }
        $query = [];
        foreach (explode('&', $parts['query']) as $pair) {
            [$key, $value] = array_pad(explode('=', $pair, 2), 2, '');
            $key = urldecode($key);
            if (array_key_exists($key, $query)) return false;
            $query[$key] = urldecode($value);
        }
        return ($query['Action'] ?? null) === 'ConfirmSubscription'
            && ($query['TopicArn'] ?? null) === $topic && ($query['Token'] ?? null) === $token;
    }

    private function validResponse(string $body, string $topic): bool
    {
        return $this->subscriptionArn($body, $topic) !== null;
    }

    private function subscriptionArn(string $body, string $topic): ?string
    {
        if (preg_match('/<!\s*(?:DOCTYPE|ENTITY)\b/i', $body)) {
            return null;
        }
        $previous = libxml_use_internal_errors(true);
        try {
            $xml = simplexml_load_string($body, \SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOBLANKS);
            if (!$xml || $xml->getName() !== 'ConfirmSubscriptionResponse') {
                return null;
            }
            $namespace = $xml->getDocNamespaces(false);
            if ($namespace && !in_array($namespace[''] ?? null, [
                'http://sns.amazonaws.com/doc/2010-03-31/',
                'https://sns.amazonaws.com/doc/2010-03-31/',
            ], true)) {
                return null;
            }
            $results = $xml->children($namespace[''] ?? '')->ConfirmSubscriptionResult;
            if (count($results) !== 1 || count($results[0]->SubscriptionArn) !== 1) {
                return null;
            }
            $value = trim((string) $results[0]->SubscriptionArn);
            return preg_match('/^'.preg_quote($topic, '/').':[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}$/i', $value)
                ? $value : null;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }
}
