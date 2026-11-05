<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BrightbeanOutboundMessage extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['leased_until' => 'datetime', 'attempts' => 'integer'];
}
