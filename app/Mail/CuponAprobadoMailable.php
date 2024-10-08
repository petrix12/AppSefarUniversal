<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CuponAprobadoMailable extends Mailable
{
    use Queueable, SerializesModels;

    public $couponCode;
    public $nombreCliente;
    public $nombreSolicitante;
    public $fechaVencimiento;

    public function __construct($couponCode, $nombreCliente, $nombreSolicitante, $fechaVencimiento)
    {
        $this->couponCode = $couponCode;
        $this->nombreCliente = $nombreCliente;
        $this->nombreSolicitante = $nombreSolicitante;
        $this->fechaVencimiento = $fechaVencimiento;
    }

    public function build()
    {
        return $this->subject('Cupón aprobado para ' . $this->nombreCliente)
                    ->view('mail.cupon_aprobado')
                    ->with([
                        'couponCode' => $this->couponCode,
                        'nombreCliente' => $this->nombreCliente,
                        'nombreSolicitante' => $this->nombreSolicitante,
                        'fechaVencimiento' => $this->fechaVencimiento,
                    ]);
    }
}
