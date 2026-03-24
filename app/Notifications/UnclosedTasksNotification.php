<?php
// app/Notifications/UnclosedTasksNotification.php

namespace App\Notifications;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

class UnclosedTasksNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Collection $unclosedByAdvisor,
        private readonly Carbon     $date
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $totalTasks = $this->unclosedByAdvisor->flatten()->count();
        $dateStr    = $this->date->format('d/m/Y');

        $mail = (new MailMessage)
            ->subject("⚠️ {$totalTasks} tareas sin cerrar — {$dateStr}")
            ->greeting("Hola {$notifiable->name},")
            ->line("Las siguientes tareas del **{$dateStr}** quedaron **sin completar**:");

        foreach ($this->unclosedByAdvisor as $advisorId => $tasks) {
            $advisorName = $tasks->first()->assignee->name ?? "Asesor #{$advisorId}";
            $mail->line("---")
                 ->line("**{$advisorName}** — {$tasks->count()} tarea(s):");

            foreach ($tasks as $task) {
                $contactName = $task->contact->name ?? '?';
                $mail->line("• {$task->title} (Contacto: {$contactName})");
            }
        }

        return $mail
            ->action('Ver panel de tareas', route('tasks.admin.summary'))
            ->line('Este mensaje fue generado automáticamente.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'date'        => $this->date->toDateString(),
            'total'       => $this->unclosedByAdvisor->flatten()->count(),
            'by_advisor'  => $this->unclosedByAdvisor->map(fn($tasks) => [
                'advisor' => $tasks->first()->assignee->name ?? '?',
                'count'   => $tasks->count(),
            ])->values(),
        ];
    }
}
