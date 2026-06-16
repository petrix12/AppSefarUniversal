<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConsultationBooking extends Model
{
    use HasFactory;

    public const STATUS_PENDING_PAYMENT = 'pending_payment';
    public const STATUS_PAID = 'paid';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'consultation_calendar_id',
        'servicio_id',
        'user_id',
        'compra_id',
        'starts_at',
        'ends_at',
        'timezone',
        'status',
        'meeting_url',
        'notes',
        'hubspot_deal_id',
        'paid_at',
        'cancelled_at',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'paid_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function calendar()
    {
        return $this->belongsTo(ConsultationCalendar::class, 'consultation_calendar_id');
    }

    public function servicio()
    {
        return $this->belongsTo(Servicio::class, 'servicio_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function compra()
    {
        return $this->belongsTo(Compras::class, 'compra_id');
    }
}
