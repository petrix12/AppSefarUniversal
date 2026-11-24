<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CrmSyncConflict extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'values' => 'encrypted:array',
            'local_updated_at' => 'datetime',
            'remote_updated_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }
}
