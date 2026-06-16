<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Servicio extends Model
{
    use HasFactory;

    protected $fillable = [
        'id_hubspot',
        'nombre',
        'precio',
        'tipov',
        'categoria',
        'tipo',
        'descripcion_publica',
        'activo',
        'visible_cliente',
        'moneda',
        'duracion_minutos',
        'requiere_agenda',
        'orden',
        'hubspot_pipeline_id',
        'hubspot_stage_id',
        'metadata',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'visible_cliente' => 'boolean',
        'requiere_agenda' => 'boolean',
        'metadata' => 'array',
        'precio' => 'integer',
        'duracion_minutos' => 'integer',
        'orden' => 'integer',
        'tipov' => 'integer',
    ];

    public function scopeSellable($query)
    {
        return $query->where('activo', true)->where('visible_cliente', true);
    }

    public function consultationCalendars()
    {
        return $this->hasMany(ConsultationCalendar::class, 'servicio_id');
    }

    public function compras()
    {
        return $this->hasMany(Compras::class, 'servicio_id');
    }
}
