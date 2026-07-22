<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BancaOnlineEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'compra_id',
        'event',
        'email',
        'country_slug',
        'plan_slug',
        'package_id',
        'entry_point',
        'case_status',
        'quote_id',
        'checkout_token',
        'ip_address',
        'user_agent',
        'url',
        'payload',
        'occurred_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'occurred_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function compra()
    {
        return $this->belongsTo(Compras::class, 'compra_id');
    }
}
