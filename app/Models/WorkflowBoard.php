<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowBoard extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    public function stages(): HasMany
    {
        return $this->hasMany(WorkflowStage::class)->orderBy('sort_order');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(WorkflowMembership::class);
    }
}
