<?php
// app/Console/Commands/GenerateDailyTasks.php

namespace App\Console\Commands;

use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GenerateDailyTasks extends Command
{
    protected $signature = 'tasks:generate-daily
        {--date=      : Fecha Y-m-d (defecto: hoy)}
        {--per=10     : Tareas base por asesor}
        {--dry-run    : Solo muestra, no escribe}
    ';

    protected $description = 'Genera N tareas diarias por asesor descontando los follow-ups ya programados.';

    public function handle(): int
    {
        $date    = $this->option('date') ? Carbon::parse($this->option('date')) : today();
        $base    = (int) ($this->option('per') ?? 10);
        $dryRun  = (bool) $this->option('dry-run');

        $this->info("📅 Fecha: {$date->toDateString()} | Base: {$base} tareas | " . ($dryRun ? 'DRY-RUN' : 'REAL'));

        // 1) Asesores activos
        $advisors = User::role('Coord. Ventas')->get(['id', 'name']);

        if ($advisors->isEmpty()) {
            $this->error('No hay usuarios con rol Asesor.');
            return self::FAILURE;
        }

        // 2) Contactos disponibles (en list_user, no contactados)
        //    Agrupados por owner_id para matchear con el asesor
        $contacts = DB::table('list_user as lu')
            ->join('users as u', 'u.id', '=', 'lu.user_id')
            ->select('u.id as contact_id', 'u.name as contact_name', 'u.owner_id')
            ->where('lu.contacted', 0)
            ->whereNotNull('u.owner_id')
            ->get();

        if ($contacts->isEmpty()) {
            $this->warn('No hay contactos disponibles en las listas.');
            return self::SUCCESS;
        }

        $totalCreated = 0;

        foreach ($advisors as $advisor) {
            // Follow-ups ya programados para ese día (descuentan del cupo)
            $followUpCount = Task::query()
                ->where('user_id', $advisor->id)
                ->whereDate('due_date', $date)
                ->whereNotNull('follow_up_date') // son follow-ups si tenían follow_up_date al crearse
                ->count();

            $toCreate = max(0, $base - $followUpCount);

            $this->line("👤 {$advisor->name}: follow-ups={$followUpCount} → a crear={$toCreate}");

            if ($toCreate === 0) {
                $this->warn("   ↳ Cupo lleno para este asesor.");
                continue;
            }

            // Contactos de este asesor que NO tengan ya tarea hoy
            $alreadyTasked = Task::query()
                ->where('user_id', $advisor->id)
                ->whereDate('due_date', $date)
                ->pluck('contact_id')
                ->toArray();

            $candidates = $contacts
                ->where('owner_id', $advisor->id)
                ->whereNotIn('contact_id', $alreadyTasked)
                ->values()
                ->shuffle()
                ->take($toCreate);

            if ($candidates->isEmpty()) {
                $this->warn("   ↳ Sin candidatos disponibles.");
                continue;
            }

            foreach ($candidates as $contact) {
                $this->line("   + Tarea → {$contact->contact_name} (contact_id={$contact->contact_id})");

                if (! $dryRun) {
                    Task::create([
                        'user_id'             => $advisor->id,
                        'contact_id'          => $contact->contact_id,
                        'title'               => "Comunicarse con el cliente {$contact->contact_name}",
                        'description'         => null,
                        'due_date'            => $date->toDateString(),
                        'status'              => 'pending',
                        'created_by_user_id'  => null, // sistema
                    ]);
                }

                $totalCreated++;
            }
        }

        $this->info("✅ Tareas " . ($dryRun ? 'simuladas' : 'creadas') . ": {$totalCreated}");
        return self::SUCCESS;
    }
}
