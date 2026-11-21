<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class KalendiaAppointment extends Model
{
    protected $guarded = [];
    protected $hidden = ['customer'];
    protected $casts = ['starts_at' => \App\Casts\UtcDateTime::class, 'ends_at' => \App\Casts\UtcDateTime::class,
        'checked_at' => 'datetime', 'customer' => 'encrypted:array'];
    public function user() { return $this->belongsTo(User::class); }
    public function getDisplayStatusAttribute(): string
    {
        return match ($this->status) { 'cancelled' => 'Cancelada', 'confirmed' => 'Confirmada', 'completed' => 'Completada', 'no_show' => 'No asistió', default => 'Pendiente' };
    }
}
