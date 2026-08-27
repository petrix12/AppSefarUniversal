<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomFieldValue extends Model
{
    protected $guarded = [];

    protected $casts = [
        'source_updated_at' => 'datetime',
    ];

    public function definition(): BelongsTo
    {
        return $this->belongsTo(CustomFieldDefinition::class, 'custom_field_definition_id');
    }

    public function getDecodedValueAttribute(): mixed
    {
        if ($this->value === null) {
            return null;
        }

        return json_decode((string) $this->value, true, 512, JSON_THROW_ON_ERROR);
    }

    public function scopeForEntity($query, string $entityType, int $entityId)
    {
        return $query
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId);
    }
}
