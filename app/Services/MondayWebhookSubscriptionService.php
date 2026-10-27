<?php

namespace App\Services;

use RuntimeException;

class MondayWebhookSubscriptionService
{
    public const DEFAULT_EVENTS = [
        'change_column_value',
        'change_name',
        'create_item',
        'item_archived',
        'item_deleted',
        'item_moved_to_any_group',
        'item_restored',
        'create_subitem',
        'change_subitem_column_value',
        'change_subitem_name',
        'move_subitem',
        'subitem_archived',
        'subitem_deleted',
        'create_column',
    ];

    public function __construct(private readonly MondayApiClient $client) {}

    public function configured(): bool
    {
        return trim((string) config('services.monday.webhook_secret')) !== '';
    }

    public function ensure(string $boardId, ?array $events = null): array
    {
        $url = $this->callbackUrl();
        $events = array_values(array_unique($events ?: self::DEFAULT_EVENTS));
        $existing = collect($this->client->webhooks($boardId))
            ->pluck('event')
            ->filter()
            ->map(fn ($event): string => (string) $event)
            ->all();
        $created = [];

        foreach ($events as $event) {
            if (in_array($event, $existing, true)) {
                continue;
            }

            $this->client->createWebhook($boardId, $url, (string) $event);
            $created[] = (string) $event;
        }

        return $created;
    }

    private function callbackUrl(): string
    {
        $secret = trim((string) config('services.monday.webhook_secret'));
        if ($secret === '') {
            throw new RuntimeException('Falta MONDAY_WEBHOOK_SECRET.');
        }

        $url = route('api.monday.webhook', ['token' => $secret]);
        if (! str_starts_with($url, 'https://')) {
            throw new RuntimeException('APP_URL debe ser una URL HTTPS pública para que Monday valide el webhook.');
        }
        if (strlen($url) > 255) {
            throw new RuntimeException('La URL del webhook de Monday supera el límite de 255 caracteres.');
        }

        return $url;
    }
}
