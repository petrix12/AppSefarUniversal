<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MondayBoard extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'items_count' => 'integer',
        'raw_data' => 'array',
        'last_seen_at' => 'datetime',
        'last_synced_at' => 'datetime',
        'remote_deleted_at' => 'datetime',
    ];

    public function groups(): HasMany
    {
        return $this->hasMany(MondayGroup::class, 'board_id');
    }

    public function columns(): HasMany
    {
        return $this->hasMany(MondayColumn::class, 'board_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(MondayItem::class, 'board_id');
    }

    public function scopeAvailable($query)
    {
        return $query
            ->whereNull('remote_deleted_at')
            ->where(function ($query): void {
                $query->whereNull('state')->orWhere('state', 'active');
            });
    }
}
