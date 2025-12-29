<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RegistrationFollowup extends Model
{
    protected $fillable = ['user_id','sequence','scheduled_for','sent_at','subject'];

    protected $casts = [
        'scheduled_for' => 'date',
        'sent_at' => 'datetime',
    ];
}
