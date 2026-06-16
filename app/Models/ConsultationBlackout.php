<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConsultationBlackout extends Model
{
    use HasFactory;

    protected $fillable = [
        'consultation_calendar_id',
        'starts_at',
        'ends_at',
        'reason',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function calendar()
    {
        return $this->belongsTo(ConsultationCalendar::class, 'consultation_calendar_id');
    }
}
