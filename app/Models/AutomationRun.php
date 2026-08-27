<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutomationRun extends Model
{
    public const PENDING = 'pending';
    public const RUNNING = 'running';
    public const COMPLETED = 'completed';
    public const FAILED = 'failed';
    public const SKIPPED = 'skipped';
    public const CANCELLED = 'cancelled';

    protected $guarded = [];

    protected $casts = [
        'scheduled_for' => 'datetime',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'context' => 'array',
        'result' => 'array',
    ];

    public function rule(): BelongsTo
    {
        return $this->belongsTo(AutomationRule::class, 'automation_rule_id');
    }

    public function action(): BelongsTo
    {
        return $this->belongsTo(AutomationAction::class, 'automation_action_id');
    }

    public function scopeDue($query)
    {
        return $query
            ->where('status', self::PENDING)
            ->where('scheduled_for', '<=', now());
    }
}
