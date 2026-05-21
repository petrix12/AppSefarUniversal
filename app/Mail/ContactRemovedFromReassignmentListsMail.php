<?php

namespace App\Mail;

use App\Models\Task;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class ContactRemovedFromReassignmentListsMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Task $task,
        public User $contact,
        public Collection $lists
    ) {}

    public function build()
    {
        return $this->subject("Contacto para retirar de listas de reasignacion: {$this->contact->name}")
            ->view('emails.contact_removed_from_reassignment_lists');
    }
}
