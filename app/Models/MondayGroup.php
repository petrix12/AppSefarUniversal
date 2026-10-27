<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MondayGroup extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'raw_data' => 'array',
        'last_synced_at' => 'datetime',
    ];

    public function board(): BelongsTo
    {
        return $this->belongsTo(MondayBoard::class, 'board_id');
    }
}
