<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

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
        try {
            if (Schema::hasColumn($this->getTable(), 'activo')) {
                $query->where('activo', true);
            }

            if (Schema::hasColumn($this->getTable(), 'visible_cliente')) {
                $query->where('visible_cliente', true);
            }
        } catch (\Throwable $e) {
            // Ambientes sin migraciones nuevas deben seguir pudiendo leer servicios base.
        }

        return $query;
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
