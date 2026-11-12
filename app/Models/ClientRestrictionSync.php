<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientRestrictionSync extends Model
{
    protected $guarded = [];
    protected $casts = ['blocked' => 'boolean', 'synced_at' => 'datetime', 'retry_at' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
