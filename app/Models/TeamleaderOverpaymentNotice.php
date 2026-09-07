<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeamleaderOverpaymentNotice extends Model
{
    protected $fillable = [
        'user_id',
        'teamleader_project_id',
        'phase',
        'preestablished_amount',
        'paid_amount',
        'overpaid_amount',
        'notified_at',
    ];

    protected $casts = [
        'preestablished_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'overpaid_amount' => 'decimal:2',
        'notified_at' => 'datetime',
    ];
}
