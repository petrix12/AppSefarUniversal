<?php

namespace App\Console\Commands;

use App\Mail\InProgressTasksReminderMail;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class RemindInProgressTasks extends Command
{
    protected $signature = 'tasks:remind-in-progress
        {--date= : Fecha base opcional YYYY-MM-DD}
        {--days=7 : Dias minimos en progreso}
        {--dry-run : Solo muestra cambios, no envia correos}
        {--no-email : No envia correos}';

    protected $description = 'Envia recordatorios a asesores con tareas en progreso desde hace varios dias.';

    public function handle(): int
    {
        $date = $this->option('date')
            ? Carbon::parse($this->option('date'))->startOfDay()
            : today();

        $days = max(1, (int) ($this->option('days') ?? 7));
        $dryRun = (bool) $this->option('dry-run');
        $sendEmails = ! $dryRun && ! (bool) $this->option('no-email');
        $cutoff = $date->copy()->subDays($days)->endOfDay();

        $this->info("Revisando tareas en progreso desde hace {$days}+ dias. Corte: {$cutoff->toDateString()}");

        $tasks = Task::query()
            ->with([
                'assignee:id,name,email',
                'contact:id,name,email,phone,hs_id',
            ])
            ->where('status', Task::STATUS_IN_PROGRESS)
            ->notAssignedToSystems()
            ->where(function ($query) use ($cutoff) {
                $query->whereDate('due_date', '<=', $cutoff->toDateString())
                    ->orWhere(function ($fallback) use ($cutoff) {
                        $fallback->whereNull('due_date')
                            ->where('created_at', '<=', $cutoff);
                    });
            })
            ->orderBy('user_id')
            ->orderBy('due_date')
            ->orderBy('created_at')
            ->get();

        if ($tasks->isEmpty()) {
            $this->info('No hay tareas en progreso para recordar.');
            return self::SUCCESS;
        }

        $this->info("Tareas en progreso encontradas: {$tasks->count()}");

        $sent = 0;
        $skipped = 0;

        foreach ($tasks->groupBy('user_id') as $advisorId => $advisorTasks) {
            $advisor = $advisorTasks->first()->assignee;

            if (! $advisor || blank($advisor->email)) {
                $skipped++;
                $this->warn("Sin correo para asesor user_id={$advisorId}. Tareas={$advisorTasks->count()}");
                continue;
            }

            $this->line("Recordatorio -> {$advisor->name} ({$advisor->email}) | tareas={$advisorTasks->count()}");

            if (! $sendEmails) {
                continue;
            }

            try {
                Mail::to($advisor->email)->send(new InProgressTasksReminderMail(
                    $advisor,
                    $advisorTasks->values(),
                    $date,
                    $days,
                    route('tasks.index', ['status' => Task::STATUS_IN_PROGRESS])
                ));

                $sent++;
            } catch (\Throwable $e) {
                $skipped++;
                $this->warn("No se pudo enviar correo a {$advisor->email}: {$e->getMessage()}");

                Log::error('Error enviando recordatorio de tareas en progreso', [
                    'advisor_id' => $advisor->id,
                    'advisor_email' => $advisor->email,
                    'task_ids' => $advisorTasks->pluck('id')->values()->all(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info($dryRun ? 'Dry-run completado. No se envio correo.' : 'Recordatorios procesados.');
        $this->info("Correos enviados: {$sent}");
        $this->info("Asesores omitidos/fallidos: {$skipped}");

        return self::SUCCESS;
    }
}
