<?php

namespace App\Console\Commands;

use App\Services\TeamleaderService;
use Illuminate\Console\Command;

class RegisterCrmWebhooks extends Command
{
    protected $signature = 'crm:register-teamleader-webhooks {--apply : Registra las suscripciones; sin esta opción solo verifica.}';
    protected $description = 'Verifica o registra exclusivamente los webhooks CRM de esta App, sin tocar otros destinos.';

    public const TYPES = ['contact.added', 'contact.updated', 'contact.deleted', 'deal.created', 'deal.updated', 'deal.moved', 'deal.won', 'deal.lost', 'deal.deleted'];

    public function handle(TeamleaderService $teamleader): int
    {
        if (! config('crm_sync.teamleader.enabled') || strlen((string) config('crm_sync.teamleader.secret')) < 32
            || blank(config('crm_sync.teamleader.account_id')) || ! str_starts_with(config('app.url'), 'https://')) {
            $this->error('Configura HTTPS, cuenta, secreto y recepción de Teamleader antes de registrar.');
            return self::FAILURE;
        }
        $url = route('api.crm.webhook', ['provider' => 'teamleader', 'token' => config('crm_sync.teamleader.secret')]);
        $subscriptions = $teamleader->crmWebhooks('list');
        $existing = collect($subscriptions['data'] ?? [])->where('url', $url)->flatMap(fn ($hook) => $hook['types'] ?? [])->all();
        $missing = array_values(array_diff(self::TYPES, $existing));
        $this->line(count($missing).' tipos pendientes para el destino de esta App.');
        if ($this->option('apply') && $missing !== []) {
            $teamleader->crmWebhooks('register', ['url' => $url, 'types' => array_values(array_unique(array_merge($existing, self::TYPES)))]);
            $this->info('Suscripciones registradas. Verifica la recepción de un evento real antes de cambiar el COS a lectura local.');
        }
        return self::SUCCESS;
    }
}
