<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HubspotOwner extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'email', 'name', 'active',
        'hubspot_created_at', 'hubspot_updated_at',
    ];

    public function ownerUserLink()
    {
        return $this->hasOne(\App\Models\HubspotOwnerUser::class, 'hubspot_owner_id', 'id')
            ->with('user');
    }
}
