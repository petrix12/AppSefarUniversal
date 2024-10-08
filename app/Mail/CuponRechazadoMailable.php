<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CuponRechazadoMailable extends Mailable
{
    use Queueable, SerializesModels;

    public $nombreCliente;
    public $nombreSolicitante;

    public function __construct($nombreCliente, $nombreSolicitante)
    {
        $this->nombreCliente = $nombreCliente;
        $this->nombreSolicitante = $nombreSolicitante;
    }

    public function build()
    {
        return $this->subject('AVISO - Cupón rechazado para ' . $this->nombreCliente)
                    ->view('mail.cupon-rechazado')
                    ->with([
                        'nombreCliente' => $this->nombreCliente,
                        'nombreSolicitante' => $this->nombreSolicitante
                    ]);
    }
}
