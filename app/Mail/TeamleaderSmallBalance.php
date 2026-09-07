<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TeamleaderSmallBalance extends Mailable
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
            ->subject('Revisión requerida: diferencial menor al mínimo de cobro')
            ->view('mail.teamleader-small-balance');
    }
}
