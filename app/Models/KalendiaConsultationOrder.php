<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class KalendiaConsultationOrder extends Model
{
    public const SOURCE = 'kalendia_consultation';
    protected $guarded = [];
    protected $hidden = ['lease'];
    protected $casts = ['amount' => 'decimal:2', 'payment_started_at' => 'datetime', 'paid_at' => 'datetime', 'leased_until' => 'datetime'];
    public function user() { return $this->belongsTo(User::class); }
    public function purchase() { return $this->belongsTo(Compras::class, 'compra_id'); }
}
