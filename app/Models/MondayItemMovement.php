<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MondayItemMovement extends Model
{
    protected $guarded = [];

    protected $casts = [
        'moved_at' => 'datetime',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(MondayItem::class, 'item_id')->withTrashed();
    }
}
