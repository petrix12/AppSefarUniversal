<?php

namespace App\Mail;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class InProgressTasksReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $advisor,
        public Collection $tasks,
        public Carbon $date,
        public int $days,
        public string $tasksUrl
    ) {}

    public function build()
    {
        $total = $this->tasks->count();
        $date = $this->date->format('d/m/Y');

        return $this->subject("Recordatorio: {$total} tarea(s) en progreso desde hace {$this->days}+ dias - {$date}")
            ->view('emails.in_progress_tasks_reminder');
    }
}
