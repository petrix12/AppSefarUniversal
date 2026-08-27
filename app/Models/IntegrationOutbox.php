<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegrationOutbox extends Model
{
    protected $table = 'integration_outbox';

    protected $guarded = [];

    protected $casts = [
        'payload' => 'array',
        'available_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    public function externalEntityLink(): BelongsTo
    {
        return $this->belongsTo(ExternalEntityLink::class);
    }
}
