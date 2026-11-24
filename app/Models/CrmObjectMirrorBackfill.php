<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CrmObjectMirrorBackfill extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'archived' => 'boolean',
            'last_page_at' => 'datetime',
        ];
    }
}
