<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowStage extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_terminal' => 'boolean',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    public function board(): BelongsTo
    {
        return $this->belongsTo(WorkflowBoard::class, 'workflow_board_id');
    }
}
