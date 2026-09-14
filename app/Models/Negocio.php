<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Negocio extends Model
{
    use HasFactory;

    protected $table = 'negocios';

    protected $guarded = [];

    public function teamleaderProjectLink(): HasOne
    {
        return $this->hasOne(HubspotDealTeamleaderProjectLink::class);
    }
}
