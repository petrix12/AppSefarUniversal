<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class RegistrationPaymentReminder extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $fullName,
        public int $sequence,
        public string $subjectLine,
        public string $paymentUrl
    ) {}

    public function build()
    {
        return $this->subject($this->subjectLine)
            ->view('emails.registration_payment_reminder');
    }
}
