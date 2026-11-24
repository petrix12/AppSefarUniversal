<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CrmIdentityReview extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'candidate_user_ids' => 'array',
            'candidate_lead_ids' => 'array',
            'matched_fields' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }
}
