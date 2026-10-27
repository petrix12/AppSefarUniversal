<?php

namespace App\Console\Commands;

use App\Models\MondayBoard;
use App\Services\MondayWebhookSubscriptionService;
use Illuminate\Console\Command;
use Throwable;

class RegisterMondayWebhooksCommand extends Command
{
    protected $signature = 'monday:webhooks
                            {--board=* : IDs de tablero; por defecto usa todos los tableros locales disponibles}
                            {--event=* : Eventos concretos; por defecto registra los necesarios para la réplica}';

    protected $description = 'Registra webhooks de Monday que actualizan la réplica local.';

    public function handle(MondayWebhookSubscriptionService $subscriptions): int
    {
        if (! $subscriptions->configured()) {
            $this->error('Falta MONDAY_WEBHOOK_SECRET.');
            return self::FAILURE;
        }

        $boardIds = collect((array) $this->option('board'))
            ->map(fn ($id): string => trim((string) $id))
            ->filter()
            ->unique()
            ->values();
        if ($boardIds->isEmpty()) {
            $boardIds = MondayBoard::available()->pluck('id');
        }
        if ($boardIds->isEmpty()) {
            $this->error('No hay tableros locales. Ejecuta primero monday:sync.');
            return self::FAILURE;
        }

        $events = collect((array) $this->option('event'))
            ->map(fn ($event): string => trim((string) $event))
            ->filter()
            ->unique()
            ->values();
        if ($events->isEmpty()) {
            $events = collect(MondayWebhookSubscriptionService::DEFAULT_EVENTS);
        }

        $created = 0;
        $skipped = 0;
        $failed = 0;
        foreach ($boardIds as $boardId) {
            try {
                $createdEvents = $subscriptions->ensure((string) $boardId, $events->all());
                if ($subscriptions->skippedAsSubitemsBoard((string) $boardId)) {
                    $skipped++;
                    $this->warn("Tablero {$boardId}: omitido porque es el tablero interno de subelementos.");
                    continue;
                }

                foreach ($createdEvents as $event) {
                    $created++;
                    $this->line("Tablero {$boardId}: {$event}");
                }
            } catch (Throwable $exception) {
                $failed++;
                $this->error("Tablero {$boardId}: {$exception->getMessage()}");
            }
        }

        $this->info("Webhooks creados: {$created}. Tableros internos omitidos: {$skipped}. Fallos: {$failed}. Los ya existentes se conservaron.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
