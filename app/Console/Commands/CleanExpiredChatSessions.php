<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ChatSession;
use App\Models\RoleAiChatSession;

class CleanExpiredChatSessions extends Command
{
    protected $signature = 'chat:clean-expired';
    protected $description = 'Limpia las sesiones de chat expiradas';

    public function handle()
    {
        ChatSession::where('expires_at', '<', now())->delete();
        RoleAiChatSession::where('expires_at', '<', now())->delete();
        $this->info('Sesiones de chat expiradas eliminadas.');
    }
}
