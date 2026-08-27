<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A reviewed design decision only. This model is deliberately separate from
 * IntegrationFieldMapping: recording a link here can never enable a sync.
 */
class UnificationAuditLink extends Model
{
    public const STATUSES = ['proposed', 'approved', 'needs_information', 'rejected'];

    public const MATCH_METHODS = ['legacy_catalog', 'exact_name', 'similar_name', 'manual'];

    protected $guarded = [];

    protected $casts = [
        'confidence' => 'integer',
        'metadata' => 'array',
        'reviewed_at' => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }
}
