<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CrmContactSnapshot extends Model
{
    protected $guarded = [];
    protected $casts = ['deal_ids' => 'array', 'contact' => 'array', 'synced_at' => 'datetime'];
}
