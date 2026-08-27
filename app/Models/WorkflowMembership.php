<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowMembership extends Model
{
    protected $guarded = [];

    protected $casts = [
        'entered_at' => 'datetime',
        'left_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function board(): BelongsTo
    {
        return $this->belongsTo(WorkflowBoard::class, 'workflow_board_id');
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(WorkflowStage::class, 'workflow_stage_id');
    }

    public function transitions(): HasMany
    {
        return $this->hasMany(WorkflowTransition::class);
    }
}
