<?php
// app/Console/Commands/GenerateDailyTasks.php

namespace App\Console\Commands;

use App\Mail\DailyTasksAssignedMail;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class GenerateDailyTasks extends Command
{
    protected $signature = 'tasks:generate-daily
        {--date=      : Fecha Y-m-d (defecto: hoy)}
        {--per=10     : Tareas base por asesor}
        {--dry-run    : Solo muestra, no escribe}
        {--no-email   : No envia correo a los vendedores}
    ';

    protected $description = 'Genera tareas diarias por asesor respetando cupos, evitando repetidos semanales y omitiendo desinteresados.';

    public function handle(): int
    {
        $date   = $this->option('date') ? Carbon::parse($this->option('date')) : today();
        $base   = (int) ($this->option('per') ?? 10);
        $dryRun = (bool) $this->option('dry-run');
        $sendEmails = ! $dryRun && ! (bool) $this->option('no-email');

        $this->info("📅 Fecha: {$date->toDateString()} | Base: {$base} tareas | " . ($dryRun ? 'DRY-RUN' : 'REAL'));

        // 1) Asesores activos
        $advisors = User::role('Coord. de Nacionalidad y Genealogía')
            ->where('exclude_from_task_assignment', false)
            ->get(['id', 'name', 'task_assignment_daily_limit']);

        if ($advisors->isEmpty()) {
            $this->error('No hay usuarios con rol Coord. de Nacionalidad y Genealogía.');
            return self::FAILURE;
        }

        // 2) Contactos disponibles (en list_user, no contactados)
        $contacts = DB::table('list_user as lu')
            ->join('users as u', 'u.id', '=', 'lu.user_id')
            ->join('lists as l', 'l.id', '=', 'lu.list_id')
            ->select(
                'u.id as contact_id',
                'u.name as contact_name',
                'u.owner_id',
                'l.id as list_id',
                'l.name as list_name'
            )
            ->where('lu.contacted', 0)
            ->whereNotNull('u.owner_id')
            ->get();

        if ($contacts->isEmpty()) {
            $this->warn('No hay contactos disponibles en las listas.');
            return self::SUCCESS;
        }

        $totalCreated = 0;

        foreach ($advisors as $advisor) {
            $createdTaskIds = [];
            $dailyLimit = $advisor->task_assignment_daily_limit;
            $advisorBase = is_null($dailyLimit) ? $base : max(0, (int) $dailyLimit);

            // 3) Tareas ya asignadas para ese dia
            $assignedTodayCount = Task::query()
                ->where('user_id', $advisor->id)
                ->whereDate('due_date', $date)
                ->where('status', '!=', Task::STATUS_CANCELED)
                ->count();

            // 4) Follow-ups ya programados para ese dia
            $followUpCount = Task::query()
                ->where('user_id', $advisor->id)
                ->whereDate('due_date', $date)
                ->whereNotNull('follow_up_date')
                ->count();

            $toCreate = max(0, $advisorBase - $assignedTodayCount);

            $this->line("👤 {$advisor->name}: cupo={$advisorBase} / asignadas={$assignedTodayCount} / follow-ups={$followUpCount} → a crear={$toCreate}");

            if ($toCreate === 0) {
                $this->warn("   ↳ Cupo lleno para este asesor.");
                continue;
            }

            // 5) Contactos que ya tienen tarea HOY para este asesor
            $alreadyTaskedToday = Task::query()
                ->where('user_id', $advisor->id)
                ->whereDate('due_date', $date)
                ->pluck('contact_id')
                ->filter()
                ->toArray();

            // 6) Contactos que ya tuvieron tarea en los ÚLTIMOS 7 DÍAS para este asesor
            $weekStart = $date->copy()->subDays(6)->startOfDay(); // hoy incluido = ventana de 7 días
            $weekEnd   = $date->copy()->endOfDay();

            $recentlyTasked = Task::query()
                ->where('user_id', $advisor->id)
                ->whereBetween('due_date', [$weekStart, $weekEnd])
                ->pluck('contact_id')
                ->filter()
                ->unique()
                ->toArray();

            // 7) Contactos con desinterés: excluirlos COMPLETAMENTE
            // Ajusta este filtro según cómo guardes el desinterés:
            // a) status = 'no_interest' / 'desinterest'
            // b) interest_level = 0
            // c) reason_no_interest no null
            $disinterestedContacts = Task::query()
                ->where(function ($q) {
                    $q->whereIn('status', ['desinteres', 'no_interest', 'not_interested'])
                      ->orWhereNotNull('reason_no_interest')
                      ->orWhere('interest_level', 0);
                })
                ->pluck('contact_id')
                ->filter()
                ->unique()
                ->toArray();

            // 8) Armar candidatos limpios
            $candidates = $contacts
                ->where('owner_id', $advisor->id)
                ->reject(function ($contact) use ($alreadyTaskedToday, $recentlyTasked, $disinterestedContacts) {
                    return in_array($contact->contact_id, $alreadyTaskedToday)
                        || in_array($contact->contact_id, $recentlyTasked)
                        || in_array($contact->contact_id, $disinterestedContacts);
                })
                ->values()
                ->shuffle()
                ->take($toCreate);

            if ($candidates->isEmpty()) {
                $this->warn("   ↳ Sin candidatos disponibles.");
                continue;
            }

            foreach ($candidates as $contact) {
                $this->line("   + Tarea → {$contact->contact_name} | Lista: {$contact->list_name} (contact_id={$contact->contact_id})");

                if (! $dryRun) {
                    $task = Task::create([
                        'user_id'            => $advisor->id,
                        'contact_id'         => $contact->contact_id,
                        'title'              => "Comunicarse con el cliente {$contact->contact_name} [Lista: {$contact->list_name}]",
                        'description' => "Lista origen: {$contact->list_name}",
                        'due_date'           => $date->toDateString(),
                        'status'             => Task::STATUS_PENDING,
                        'created_by_user_id' => null,
                    ]);

                    $createdTaskIds[] = $task->id;
                }

                $totalCreated++;
            }

            if ($sendEmails && ! empty($createdTaskIds)) {
                $this->sendAssignedTasksEmail($advisor, $createdTaskIds, $date);
            }
        }

        $this->info("✅ Tareas " . ($dryRun ? 'simuladas' : 'creadas') . ": {$totalCreated}");
        return self::SUCCESS;
    }

    private function sendAssignedTasksEmail(User $advisor, array $taskIds, Carbon $date): void
    {
        if (blank($advisor->email)) {
            $this->warn("   ↳ No se envio correo a {$advisor->name}: no tiene email.");
            return;
        }

        $tasks = Task::query()
            ->with('contact')
            ->whereKey($taskIds)
            ->orderBy('id')
            ->get();

        if ($tasks->isEmpty()) {
            return;
        }

        try {
            Mail::to($advisor->email)->send(new DailyTasksAssignedMail(
                $advisor,
                $tasks,
                $date,
                route('tasks.index')
            ));

            $this->line("   ↳ Correo enviado a {$advisor->email} ({$tasks->count()} tarea(s)).");
        } catch (\Throwable $e) {
            $this->warn("   ↳ No se pudo enviar correo a {$advisor->email}: {$e->getMessage()}");

            Log::error('Error enviando correo de tareas asignadas', [
                'advisor_id' => $advisor->id,
                'advisor_email' => $advisor->email,
                'task_ids' => $taskIds,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
