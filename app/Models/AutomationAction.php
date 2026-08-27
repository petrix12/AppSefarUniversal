<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AutomationAction extends Model
{
    public const CREATE_TASK = 'create_task';
    public const NOTIFY_USER = 'notify_user';
    public const SET_CUSTOM_FIELD = 'set_custom_field';
    public const MOVE_WORKFLOW = 'move_workflow';

    public const TYPES = [
        self::CREATE_TASK,
        self::NOTIFY_USER,
        self::SET_CUSTOM_FIELD,
        self::MOVE_WORKFLOW,
    ];

    protected $guarded = [];

    protected $casts = [
        'config' => 'array',
        'is_active' => 'boolean',
    ];

    public function rule(): BelongsTo
    {
        return $this->belongsTo(AutomationRule::class, 'automation_rule_id');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(AutomationRun::class);
    }
}
