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
        $this->line(json_encode($mirror->sync(max(1,min(10,(int)$this->option('limit')))))); return self::SUCCESS;
    }
}
