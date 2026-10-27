<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MondayWebhookEvent extends Model
{
    protected $guarded = [];

    protected $casts = [
        'payload' => 'array',
        'processed_at' => 'datetime',
    ];
}
