<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\User;

class ClaveGeneradaMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $password;

    public function __construct(User $user, $password)
    {
        $this->user = $user;
        $this->password = $password;
    }

    public function build()
    {
        return $this->subject($this->user->nombres. ', ¡BIENVENIDO a Sefar Universal! Aquí estan tus credenciales de acceso')
                    ->view('mail.clave_generada'); // 👈 aquí va tu HTML Blade
    }
}
