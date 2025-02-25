<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ChatSession;

class CleanExpiredChatSessions extends Command
{
    protected $signature = 'chat:clean-expired';
    protected $description = 'Limpia las sesiones de chat expiradas';

    public function handle()
    {
        ChatSession::where('expires_at', '<', now())->delete();
        $this->info('Sesiones de chat expiradas eliminadas.');
    }
}
