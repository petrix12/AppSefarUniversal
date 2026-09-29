<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserChangeAudit extends Model
{
    protected $fillable = [
        'user_id',
        'changed_by_user_id',
        'old_values',
        'new_values',
        'source',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }
}
