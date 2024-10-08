<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SolicitudCupon extends Model
{
    // Especificar la tabla asociada si el nombre no sigue la convención
    protected $table = 'solicitudes_cupones';

    // Permitir asignación masiva en todos los campos
    protected $guarded = [];

    // Campos que deben ser tratados como fechas
    protected $dates = [
        'created_at',
        'updated_at',
    ];

    // Convertir atributos a tipos nativos
    protected $casts = [
        'porcentaje_descuento' => 'integer',
        'aprobado' => 'boolean',
        'estatus_cupon' => 'boolean',
    ];

    /**
     * Obtener el nombre completo del solicitante.
     *
     * @return string
     */
    public function getNombreCompletoSolicitanteAttribute()
    {
        return "{$this->nombre_solicitante} {$this->apellidos_solicitante}";
    }

    /**
     * Obtener el nombre completo del cliente.
     *
     * @return string
     */
    public function getNombreCompletoClienteAttribute()
    {
        return "{$this->nombre_cliente} {$this->apellidos_cliente}";
    }

    /**
     * Obtener los comprobantes de pago como un array.
     *
     * @return array
     */
    public function getComprobantePagoArrayAttribute()
    {
        return explode(',', $this->comprobante_pago);
    }

    /**
     * Establecer el atributo 'aprobado' como booleano.
     *
     * @param  mixed  $value
     * @return void
     */
    public function setAprobadoAttribute($value)
    {
        $this->attributes['aprobado'] = (bool) $value;
    }

    /**
     * Establecer el atributo 'estatus_cupon' como booleano.
     *
     * @param  mixed  $value
     * @return void
     */
    public function setEstatusCuponAttribute($value)
    {
        $this->attributes['estatus_cupon'] = (bool) $value;
    }
}
