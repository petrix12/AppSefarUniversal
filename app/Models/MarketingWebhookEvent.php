<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketingWebhookEvent extends Model
{
    protected $guarded = [];

    protected $casts = ['payload' => 'array', 'received_at' => 'datetime'];
}
