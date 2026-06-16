<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConsultationCalendar extends Model
{
    use HasFactory;

    protected $fillable = [
        'servicio_id',
        'nombre',
        'descripcion',
        'timezone',
        'slot_duration_minutes',
        'buffer_minutes',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'slot_duration_minutes' => 'integer',
        'buffer_minutes' => 'integer',
    ];

    public function servicio()
    {
        return $this->belongsTo(Servicio::class, 'servicio_id');
    }

    public function availabilityRules()
    {
        return $this->hasMany(ConsultationAvailabilityRule::class);
    }

    public function blackouts()
    {
        return $this->hasMany(ConsultationBlackout::class);
    }

    public function bookings()
    {
        return $this->hasMany(ConsultationBooking::class);
    }

    public function scopeActive($query)
    {
        return $query->where('activo', true);
    }
}
