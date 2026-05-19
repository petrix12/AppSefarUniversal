<?php

namespace App\Mail;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class DailyTasksAssignedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $advisor,
        public Collection $tasks,
        public Carbon $date,
        public string $tasksUrl
    ) {}

    public function build()
    {
        $total = $this->tasks->count();
        $date = $this->date->format('d/m/Y');

        return $this->subject("Tienes {$total} tarea(s) asignada(s) para hoy - {$date}")
            ->view('emails.daily_tasks_assigned');
    }
}
