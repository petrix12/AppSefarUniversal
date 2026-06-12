<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HubspotUserProvisioning extends Model
{
    protected $fillable = [
        'user_id',
        'hubspot_user_id',
        'provisioned_at',
        'last_error',
    ];

    protected $casts = [
        'provisioned_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
