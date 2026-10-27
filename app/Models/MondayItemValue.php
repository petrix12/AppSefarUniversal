<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MondayItemValue extends Model
{
    protected $guarded = [];

    protected $casts = [
        'value' => 'array',
        'raw_data' => 'array',
        'last_synced_at' => 'datetime',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(MondayItem::class, 'item_id');
    }

    public function board(): BelongsTo
    {
        return $this->belongsTo(MondayBoard::class, 'board_id');
    }
}
