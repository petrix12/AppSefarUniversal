<?php

namespace App\Mail;

use App\Models\SolicitudCupon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SolicitudCuponMailable extends Mailable
{
    use Queueable, SerializesModels;

    public $solicitud;

    public function __construct(SolicitudCupon $solicitud)
    {
        $this->solicitud = $solicitud;
    }

    public function build()
    {
        return $this->subject('Solicitud para aprobar cupón')
                    ->view('mail.aprobar-cupon')
                    ->with([
                        'solicitud' => $this->solicitud,
                        'aprobarUrl' => route('cuponaceptar', ['id' => $this->solicitud->id]),
                        'rechazarUrl' => route('rechazarcupon', ['id' => $this->solicitud->id]),
                    ]);
    }
}
