<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A generic, human-reviewed edge between two platform fields. It is not an
 * IntegrationFieldMapping and is never consumed by synchronization code.
 */
class UnificationAuditRelation extends Model
{
    public const PROVIDERS = ['app', 'hubspot', 'teamleader', 'monday'];

    public const STATUSES = ['proposed', 'approved', 'needs_information', 'rejected'];

    protected $guarded = [];

    protected $casts = [
        'confidence' => 'integer',
        'reviewed_at' => 'datetime',
        'metadata' => 'array',
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
