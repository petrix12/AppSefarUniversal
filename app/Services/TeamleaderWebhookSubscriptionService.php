<?php

namespace App\Services;

class TeamleaderWebhookSubscriptionService
{
    public const TYPES = ['contact.added', 'contact.updated', 'contact.deleted', 'deal.created', 'deal.updated', 'deal.moved', 'deal.won', 'deal.lost', 'deal.deleted', 'project.created', 'project.updated', 'project.deleted'];

    public function __construct(private TeamleaderService $teamleader) {}

    public function missingConfiguration(): array
    {
        return array_keys(array_filter([
            'CRM_TEAMLEADER_WEBHOOKS_ENABLED' => ! config('crm_sync.teamleader.enabled'),
            'CRM_TEAMLEADER_WEBHOOK_SECRET' => strlen((string) config('crm_sync.teamleader.secret')) < 32,
            'CRM_TEAMLEADER_ACCOUNT_ID' => blank(config('crm_sync.teamleader.account_id')),
            'APP_URL (HTTPS)' => ! str_starts_with((string) config('app.url'), 'https://'),
        ]));
    }

    public function register(bool $apply): array
    {
        if ($this->missingConfiguration() !== []) throw new \LogicException('Incomplete webhook configuration.');
        // Never derive a secret-bearing callback from the current request Host header.
        $url = rtrim((string) config('app.url'), '/').route('api.crm.webhook', ['provider' => 'teamleader', 'token' => config('crm_sync.teamleader.secret')], false);
        $existing = $this->typesFor($url);
        $missing = array_values(array_diff(self::TYPES, $existing));
        if ($apply && $missing !== []) {
            $this->teamleader->crmWebhooks('register', ['url' => $url, 'types' => array_values(array_unique(array_merge($existing, self::TYPES)))]);
            $missing = array_values(array_diff(self::TYPES, $this->typesFor($url)));
            if ($missing !== []) throw new \RuntimeException('Webhook registration could not be verified.');
        }
        return ['missing' => count($missing), 'expected' => count(self::TYPES), 'verified' => $missing === []];
    }

    private function typesFor(string $url): array
    {
        $subscriptions = $this->teamleader->crmWebhooks('list');
        if (! isset($subscriptions['data']) || ! is_array($subscriptions['data'])) throw new \UnexpectedValueException('Invalid webhook subscriptions response.');
        return collect($subscriptions['data'])->where('url', $url)->flatMap(fn ($hook) => $hook['types'] ?? [])->unique()->values()->all();
    }
}
