<?php
// app/Console/Commands/NotifyUnclosedTasks.php

namespace App\Console\Commands;

use App\Models\Task;
use App\Models\User;
use App\Notifications\UnclosedTasksNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;

class NotifyUnclosedTasks extends Command
{
    protected $signature  = 'tasks:notify-unclosed {--date=}';
    protected $description = 'Marca como canceled las tareas sin cerrar del día y notifica a los admins.';

    public function handle(): int
    {
        $date = $this->option('date')
            ? Carbon::parse($this->option('date'))
            : today();

        $this->info("🔔 Revisando tareas sin cerrar para: {$date->toDateString()}");

        // 1) Marcar como canceled las que siguen abiertas
        $affected = Task::query()
            ->whereDate('due_date', $date)
            ->whereIn('status', ['pending', 'in_progress'])
            ->update(['status' => 'canceled']);

        $this->info("   Marcadas como canceled: {$affected}");

        // 2) Obtener las canceladas agrupadas por asesor
        $unclosed = Task::query()
            ->with(['assignee:id,name,email', 'contact:id,name'])
            ->where('status', 'canceled')
            ->whereDate('due_date', $date)
            ->get()
            ->groupBy('user_id');

        if ($unclosed->isEmpty()) {
            $this->info('✅ Todas las tareas estaban cerradas.');
            return self::SUCCESS;
        }

        // 3) Notificar a los admins
        $admins = User::role('Administrador')->get();

        if ($admins->isEmpty()) {
            $this->warn('No hay administradores para notificar.');
            return self::SUCCESS;
        }

        foreach ($admins as $admin) {
            $admin->notify(new UnclosedTasksNotification($unclosed, $date));
        }

        $this->info("📧 Notificación enviada a {$admins->count()} admin(s).");
        return self::SUCCESS;
    }
}
