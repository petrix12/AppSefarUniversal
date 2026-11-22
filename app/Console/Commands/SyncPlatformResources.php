<?php
namespace App\Console\Commands;

use App\Services\PlatformMirror;
use Illuminate\Console\Command;

class SyncPlatformResources extends Command
{
    protected $signature='platforms:sync {--limit=2}';
    protected $description='Procesa páginas de importación solicitadas desde la administración de plataformas.';
    public function handle(PlatformMirror $mirror): int
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('platform_sync_states')) { $this->warn('Pendiente la migración de plataformas.'); return self::SUCCESS; }
        $result=$mirror->sync(max(1,min(10,(int)$this->option('limit'))));
        \Illuminate\Support\Facades\Cache::put('platforms.sync.last_run',now()->toIso8601String(),now()->addDay());
        $this->line(json_encode($result)); return self::SUCCESS;
    }
}
