<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Servicio extends Model
{
    use HasFactory;

    public const PRICE_TIMEZONE = 'America/Caracas';

    protected $fillable = [
        'id_hubspot',
        'nombre',
        'precio',
        'precio_temporal',
        'precio_temporal_desde',
        'precio_temporal_hasta',
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
        'monday_sync_enabled',
        'monday_board_id',
        'monday_group_id',
        'monday_registration_timing',
        'requires_getinfo',
        'metadata',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'visible_cliente' => 'boolean',
        'requiere_agenda' => 'boolean',
        'monday_sync_enabled' => 'boolean',
        'requires_getinfo' => 'boolean',
        'metadata' => 'array',
        'precio' => 'integer',
        'precio_temporal' => 'integer',
        'precio_temporal_desde' => 'date:Y-m-d',
        'precio_temporal_hasta' => 'date:Y-m-d',
        'duracion_minutos' => 'integer',
        'orden' => 'integer',
        'tipov' => 'integer',
    ];

    public function scopeSellable($query)
    {
        return $query->where('activo', true)->where('visible_cliente', true);
    }

    public function scopeTemporaryPriceEligible($query)
    {
        return $query->where('tipo', 'servicio')->where('moneda', 'EUR')->where('activo', true)->where('precio', '>', 0);
    }

    public function hasTemporaryPrice(?CarbonInterface $at = null): bool
    {
        if ($this->precio_temporal === null || $this->precio_temporal < 1 || $this->precio_temporal > $this->precio
            || ($this->tipo ?? 'servicio') !== 'servicio' || ($this->moneda ?? 'EUR') !== 'EUR'
            || ! $this->precio_temporal_desde || ! $this->precio_temporal_hasta) return false;
        $day = CarbonImmutable::instance($at ?? now())->setTimezone(self::PRICE_TIMEZONE)->format('Y-m-d');
        return $day >= $this->precio_temporal_desde->format('Y-m-d') && $day <= $this->precio_temporal_hasta->format('Y-m-d');
    }

    public function currentPrice(?CarbonInterface $at = null): int
    {
        return $this->hasTemporaryPrice($at) ? $this->precio_temporal : (int) $this->precio;
    }

    public function priceSnapshot(): array
    {
        $at = now();
        return [
            'base_price' => $this->precio,
            'unit_price' => $this->currentPrice($at),
            'currency' => $this->moneda ?: 'EUR',
            'priced_at' => $at->toIso8601String(),
            'promotion' => $this->hasTemporaryPrice($at) ? [
                'price' => $this->precio_temporal,
                'starts_on' => $this->precio_temporal_desde->format('Y-m-d'),
                'ends_on' => $this->precio_temporal_hasta->format('Y-m-d'),
                'timezone' => self::PRICE_TIMEZONE,
            ] : null,
        ];
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
