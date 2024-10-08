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
        return $this->subject('Solicitud para aprobar cupón - Cliente: '. $this->solicitud->nombre_cliente. " ". $this->solicitud->apellido_cliente)
                    ->view('mail.aprobar-cupon')
                    ->with([
                        'solicitud' => $this->solicitud,
                        'aprobarUrl' => "https://app.sefaruniversal.com/cuponaceptar/".$this->solicitud->id,
                        'rechazarUrl' => "https://app.sefaruniversal.com/cuponrechazar/".$this->solicitud->id,
                    ]);
    }
}
