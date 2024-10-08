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

    public function __construct($couponCode, $nombreCliente, $nombreSolicitante, $fechaVencimiento)
    {
        $this->nombreCliente = $nombreCliente;
        $this->nombreSolicitante = $nombreSolicitante
    }

    public function build()
    {
        return $this->subject('Cupón rechazado para ' . $this->nombreCliente)
                    ->view('mail.cupon_rechazado')
                    ->with([
                        'nombreCliente' => $this->nombreCliente,
                        'nombreSolicitante' => $this->nombreSolicitante
                    ]);
    }
}
