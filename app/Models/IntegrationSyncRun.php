<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IntegrationSyncRun extends Model
{
    protected $guarded = [];

    protected $casts = [
        'context' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];
}
