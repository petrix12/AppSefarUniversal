<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KalendiaBookingRequest extends Model
{
    public const QUEUED = 'queued';
    public const PROCESSING = 'processing';
    public const NEEDS_SLOT = 'needs_slot';
    public const REVIEW = 'review';
    public const COMPLETED = 'completed';
    public const ACTIVE_STATUSES = [self::QUEUED, self::PROCESSING, self::REVIEW];

    protected $hidden = ['input', 'lease'];
    protected $casts = [
        'input' => 'encrypted:array',
        'requested_date' => 'date',
        'available_at' => 'datetime',
        'leased_until' => 'datetime',
    ];

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Compras::class, 'compra_id');
    }
}
