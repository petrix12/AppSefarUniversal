<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrgReportingLine extends Model
{
    protected $fillable = ['manager_user_id', 'subordinate_user_id'];

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_user_id');
    }

    public function subordinate(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subordinate_user_id');
    }
}
