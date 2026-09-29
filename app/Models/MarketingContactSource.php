<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketingContactSource extends Model
{
    protected $guarded = [];

    protected $casts = ['source_data' => 'array'];
}
