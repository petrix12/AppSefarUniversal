<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TeamleaderPhaseOverpayment extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly array $payment,
    ) {
    }

    public function build(): self
    {
        return $this
            ->subject('Revisión requerida: sobrepago en fase 3 de Teamleader')
            ->view('mail.teamleader-phase-overpayment');
    }
}
