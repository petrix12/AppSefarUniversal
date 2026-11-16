<?php

namespace App\Console\Commands;

use App\Services\TeamleaderWebhookSubscriptionService;
use Illuminate\Console\Command;

class RegisterCrmWebhooks extends Command
{
    protected $signature = 'crm:register-teamleader-webhooks {--apply : Registra las suscripciones; sin esta opción solo verifica.}';
    protected $description = 'Verifica o registra exclusivamente los webhooks CRM de esta App, sin tocar otros destinos.';

    public const TYPES = TeamleaderWebhookSubscriptionService::TYPES;

    public function handle(TeamleaderWebhookSubscriptionService $subscriptions): int
    {
        if ($subscriptions->missingConfiguration() !== []) {
            $this->error('Falta configurar: '.implode(', ', $subscriptions->missingConfiguration()));
            return self::FAILURE;
        }
        $result = $subscriptions->register((bool) $this->option('apply'));
        $this->line($result['missing'].' tipos pendientes para el destino de esta App.');
        if ($result['verified']) $this->info('Suscripciones verificadas. Falta comprobar una entrega real.');
        return self::SUCCESS;
    }
}
