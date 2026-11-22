<?php
namespace App\Console\Commands;

use App\Services\PlatformEventProcessor;
use Illuminate\Console\Command;

class ProcessPlatformEvents extends Command
{
    protected $signature='platforms:process-events {--limit=4}';
    protected $description='Actualiza los espejos administrativos desde webhooks de Stripe y Kalendia.';
    public function handle(PlatformEventProcessor $processor): int
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('platform_events')) { $this->warn('Pendiente la migración de plataformas.'); return self::SUCCESS; }
        \Illuminate\Support\Facades\Cache::put('platforms.events.last_run',now()->toIso8601String(),now()->addDay());
        $this->line(json_encode($processor->process(max(1,min(20,(int)$this->option('limit')))))); return self::SUCCESS;
    }
}
