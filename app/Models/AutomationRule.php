<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AutomationRule extends Model
{
    public const TRIGGER_EVENT = 'event';
    public const TRIGGER_SCHEDULE = 'schedule';
    public const TRIGGER_DATE_FIELD = 'date_field';

    public const TRIGGER_TYPES = [
        self::TRIGGER_EVENT,
        self::TRIGGER_SCHEDULE,
        self::TRIGGER_DATE_FIELD,
    ];

    protected $guarded = [];

    protected $casts = [
        'trigger_config' => 'array',
        'conditions' => 'array',
        'is_active' => 'boolean',
        'last_scheduled_at' => 'datetime',
    ];

    public function actions(): HasMany
    {
        return $this->hasMany(AutomationAction::class)->orderBy('position');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(AutomationRun::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
