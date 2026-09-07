<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeamleaderSmallBalanceNotice extends Model
{
    protected $fillable = [
        'user_id',
        'teamleader_project_id',
        'phase',
        'preestablished_amount',
        'paid_amount',
        'balance_amount',
        'notified_at',
    ];

    protected $casts = [
        'preestablished_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'balance_amount' => 'decimal:2',
        'notified_at' => 'datetime',
    ];
}
