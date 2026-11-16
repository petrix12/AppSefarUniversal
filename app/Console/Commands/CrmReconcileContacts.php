<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\CrmContactMirrorService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class CrmReconcileContacts extends Command
{
    protected $signature = 'crm:reconcile-contacts {provider : hubspot or teamleader} {--after=0} {--limit=50}';
    protected $description = 'Carga o concilia contactos ya vinculados a cuentas Cliente, excluyendo leads.';

    public function handle(CrmContactMirrorService $mirror): int
    {
        if (! config('crm_sync.processing_enabled')) {
            $this->error('El procesamiento CRM está pausado. Primero verifica las entregas y la política de sincronización.');
            return self::FAILURE;
        }
        $provider = $this->argument('provider');
        if (! in_array($provider, ['hubspot', 'teamleader'], true)) return self::INVALID;
        if (! config('crm_sync.'.$provider.'.enabled')) {
            $this->error('El receptor de este proveedor está deshabilitado.');
            return self::FAILURE;
        }
        $lock = Cache::lock('crm.webhooks.'.$provider, 1800);
        if (! $lock->get()) { $this->error('Hay una sincronización de este proveedor en curso.'); return self::FAILURE; }
        $field = $provider === 'hubspot' ? 'hs_id' : 'tl_id';
        $clients = User::role('Cliente')->where('id', '>', max(0, (int) $this->option('after')))
            ->whereNotNull($field)->where($field, '!=', '')->orderBy('id')
            ->limit(max(1, min(100, (int) $this->option('limit'))))->get();
        $failed = 0;
        try {
            foreach ($clients as $client) {
                try {
                    $snapshot = $mirror->syncContact($provider, (string) $client->$field);
                    $this->line($client->id.': '.$snapshot->classification);
                } catch (\Throwable $exception) {
                    $failed++;
                    $this->error($client->id.': '.$exception::class);
                }
            }
        } finally { $lock->release(); }
        $this->line('Siguiente cursor --after='.($clients->last()?->id ?? $this->option('after')));
        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
