<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KalendiaBooking extends Model
{
    protected $guarded = [];
    protected $hidden = ['request_payload', 'idempotency_key'];
    protected $casts = ['request_payload' => 'encrypted:array', 'leased_until' => 'datetime', 'paid_amount' => 'decimal:2'];
    public function purchase() { return $this->belongsTo(Compras::class, 'compra_id'); }
}
