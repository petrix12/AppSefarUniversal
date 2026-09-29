<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketingList extends Model
{
    protected $guarded = [];

    protected $casts = [
        'source_config' => 'array',
        'last_imported_at' => 'datetime',
    ];

    public function members(): HasMany
    {
        return $this->hasMany(MarketingListMember::class);
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(MarketingCampaign::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
