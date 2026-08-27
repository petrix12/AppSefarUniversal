<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExternalEntityLink extends Model
{
    public const PROVIDERS = ['hubspot', 'monday', 'teamleader'];

    protected $guarded = [];

    protected $casts = [
        'metadata' => 'array',
        'external_updated_at' => 'datetime',
        'last_pulled_at' => 'datetime',
        'last_pushed_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    public function scopeForEntity($query, string $entityType, int $entityId)
    {
        return $query
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId);
    }
}
