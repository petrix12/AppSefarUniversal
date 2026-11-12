<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientRestrictionScan extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];
    protected $casts = ['actor_id' => 'integer', 'partial' => 'boolean', 'candidates' => 'array', 'expires_at' => 'datetime', 'applied_at' => 'datetime'];
}
