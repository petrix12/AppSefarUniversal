<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientRestrictionEvent extends Model
{
    protected $guarded = [];
    protected $casts = ['details' => 'array'];
}
