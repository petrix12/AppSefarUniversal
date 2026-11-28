<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailSuppression extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['blocked_at' => 'datetime', 'cleared_at' => 'datetime'];

    public const REASONS = ['hard_bounce' => 'Rebote permanente', 'complaint' => 'Queja por spam'];
}
