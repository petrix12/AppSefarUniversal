<?php

namespace App\Console\Commands;

use App\Services\AutomaticTeamleaderDealAssociationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class AssociateLocalDealsWithTeamleaderAi extends Command
{
    protected $signature = 'crm:associate-deals-ai
        {--after= : Cursor local explícito}
        {--limit=25 : Máximo de negocios por lote}
        {--write : Guardar las asociaciones locales}
        {--scheduled : Reanudar el barrido programado}';

    protected $description = 'Sugiere y asocia negocios locales HubSpot/Teamleader con IA, sin escribir en los CRM.';

    public function handle(AutomaticTeamleaderDealAssociationService $service): int
    {
        $lock = Cache::lock('crm.local-deal-ai-association', 3600);
        if (! $lock->get()) {
            $this->warn('Ya hay un lote de asociaciones en curso.');
            return self::SUCCESS;
        }

        try {
            $cursorKey = 'crm.local-deal-ai-association.cursor';
            $completedKey = 'crm.local-deal-ai-association.sweep-complete';
            if ($this->option('scheduled') && Cache::has($completedKey)) {
                return self::SUCCESS;
            }
            $after = $this->option('after') === null
                ? (int) Cache::get($cursorKey, 0)
                : max(0, (int) $this->option('after'));
            $result = $service->associateLocalBatch(
                $after,
                max(1, min(100, (int) $this->option('limit'))),
                (bool) $this->option('write'),
            );
            if ($this->option('write') && $result['processed'] > 0) {
                Cache::forever($cursorKey, $result['last_id']);
            }
            if ($this->option('scheduled') && $result['processed'] === 0) {
                Cache::put($completedKey, true, now()->addDay());
                Cache::forget($cursorKey);
            }
        } finally {
            $lock->release();
        }

        $this->table(['procesados', 'asociados', 'propuestos', 'omitidos', 'fallidos', 'último ID'], [[
            $result['processed'], $result['linked'], $result['proposed'], $result['skipped'], $result['failed'], $result['last_id'],
        ]]);
        $this->line('Cursor siguiente: '.$result['last_id'].' (queda guardado en cache después de un lote --write).');
        if (! $this->option('write')) {
            $this->comment('Vista previa: para guardar teamleader_id local, repite con --write.');
        }

        return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
