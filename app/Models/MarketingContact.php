<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketingContact extends Model
{
    protected $guarded = [];

    protected $casts = [
        'attributes' => 'array',
        'unsubscribed_at' => 'datetime',
        'suppressed_at' => 'datetime',
    ];

    public function memberships(): HasMany
    {
        return $this->hasMany(MarketingListMember::class);
    }

    public function isEmailable(): bool
    {
        return !$this->unsubscribed_at && !$this->suppressed_at;
    }
}
