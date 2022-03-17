<?php

namespace App\Mail;

use App\Models\Agcliente;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Auth;

class CargaCliente extends Mailable
{
    use Queueable, SerializesModels;

    public $user;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(User $user)
    {
        $this->user = $user;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $passport = Auth::user()->passport;
        $agclientes = Agcliente::where('IDCliente','LIKE',"%$passport%")->get();
        return $this->view('mail.carga-cliente', compact('agclientes'))
            ->subject('GRACIAS ' . strtoupper($this->user->name) . ' POR ACTUALIZAR SU ÁRBOL GENEALÓGICO');
    }
}
