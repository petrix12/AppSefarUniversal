<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CrmObjectAssociation extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'last_event_at' => 'datetime',
        ];
    }
}
