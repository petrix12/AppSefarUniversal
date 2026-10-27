<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MondayItem extends Model
{
    use SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'monday_created_at' => 'datetime',
        'monday_updated_at' => 'datetime',
        'raw_data' => 'array',
        'last_synced_at' => 'datetime',
    ];

    public function board(): BelongsTo
    {
        return $this->belongsTo(MondayBoard::class, 'board_id');
    }

    public function values(): HasMany
    {
        return $this->hasMany(MondayItemValue::class, 'item_id');
    }

    public function subitems(): HasMany
    {
        return $this->hasMany(self::class, 'parent_item_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(MondayItemMovement::class, 'item_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_item_id');
    }
}
