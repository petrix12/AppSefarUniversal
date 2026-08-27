<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegrationFieldMapping extends Model
{
    public const DIRECTIONS = ['pull', 'push', 'bidirectional'];

    public const CONFLICT_POLICIES = ['local_wins', 'remote_wins', 'manual'];

    protected $guarded = [];

    protected $casts = [
        'transform' => 'array',
        'is_active' => 'boolean',
    ];

    public function customFieldDefinition(): BelongsTo
    {
        return $this->belongsTo(CustomFieldDefinition::class);
    }

    public function scopeInbound($query)
    {
        return $query->whereIn('direction', ['pull', 'bidirectional']);
    }

    public function scopeOutbound($query)
    {
        return $query->whereIn('direction', ['push', 'bidirectional']);
    }
}
