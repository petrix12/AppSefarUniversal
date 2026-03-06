<?php

// app/Models/HubspotOwnerUser.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HubspotOwnerUser extends Model
{
    protected $table = 'hubspot_owner_user';

    protected $fillable = [
        'user_id',
        'hubspot_owner_id',
        'hubspot_owner_name',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function owner()
    {
        return $this->belongsTo(HubspotOwner::class, 'hubspot_owner_id', 'id');
    }
}
