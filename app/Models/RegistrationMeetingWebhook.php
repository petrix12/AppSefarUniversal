<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RegistrationMeetingWebhook extends Model
{
    protected $guarded = [];

    protected $casts = [
        'payload' => 'array', 'next_attempt_at' => 'datetime', 'leased_until' => 'datetime', 'sent_at' => 'datetime',
    ];
}
