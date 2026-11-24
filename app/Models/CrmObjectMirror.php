<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CrmObjectMirror extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'properties' => 'array',
            'archived' => 'boolean',
            'remote_created_at' => 'datetime',
            'remote_updated_at' => 'datetime',
            'remote_archived_at' => 'datetime',
            'synced_at' => 'datetime',
        ];
    }
}
