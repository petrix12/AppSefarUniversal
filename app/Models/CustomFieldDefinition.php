<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomFieldDefinition extends Model
{
    public const ENTITY_CLIENT = 'client';

    public const DATA_TYPES = [
        'text', 'long_text', 'number', 'decimal', 'boolean', 'date',
        'datetime', 'email', 'url', 'select', 'multiselect', 'json',
    ];

    protected $guarded = [];

    protected $casts = [
        'options' => 'array',
        'validation_rules' => 'array',
        'is_required' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function values(): HasMany
    {
        return $this->hasMany(CustomFieldValue::class);
    }

    public function scopeForEntity($query, string $entityType)
    {
        return $query->where('entity_type', $entityType);
    }
}
