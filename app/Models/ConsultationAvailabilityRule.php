<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConsultationAvailabilityRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'consultation_calendar_id',
        'weekday',
        'starts_at',
        'ends_at',
        'slot_duration_minutes',
        'buffer_minutes',
        'activo',
    ];

    protected $casts = [
        'weekday' => 'integer',
        'slot_duration_minutes' => 'integer',
        'buffer_minutes' => 'integer',
        'activo' => 'boolean',
    ];

    public function calendar()
    {
        return $this->belongsTo(ConsultationCalendar::class, 'consultation_calendar_id');
    }
}
