<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\RegistrationFollowupService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendRegistrationPaymentFollowups extends Command
{
    protected $signature = 'followups:registration-payment {--dry-run}';
    protected $description = 'Envía hasta tres recordatorios del pago de registro, cada 15 días';

    public function handle(RegistrationFollowupService $followups): int
    {
        $today = Carbon::today('America/Caracas');
        $sent = 0; $skipped = 0;
        User::query()->whereDate('created_at', '>=', '2024-01-01')->where('pay', 0)->role('Cliente')
            ->orderBy('id')->chunkById(200, function ($users) use ($followups, $today, &$sent, &$skipped) {
                foreach ($users as $user) {
                    $created = Carbon::parse($user->created_at)->timezone('America/Caracas')->startOfDay();
                    if ($created->gt($today) || (int) $created->diffInDays($today, true) % 15 !== 0 || ! $followups->eligible($user)) {
                        $skipped++; continue;
                    }
                    if ($this->option('dry-run')) { $this->line('[DRY] Representado '.$user->id.' con recordatorio pendiente.'); $sent++; continue; }
                    $followups->send($user, $today) ? $sent++ : $skipped++;
                }
            });
        $this->line("Recordatorios: {$sent}; omitidos: {$skipped}.");
        return self::SUCCESS;
    }
}
