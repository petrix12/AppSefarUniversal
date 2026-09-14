<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HubspotDealTeamleaderProjectLink extends Model
{
    protected $fillable = [
        'negocio_id',
        'hubspot_deal_id',
        'teamleader_project_id',
        'match_method',
        'confidence',
        'evidence',
        'linked_by',
    ];

    protected $casts = [
        'confidence' => 'integer',
        'evidence' => 'array',
    ];

    public function negocio(): BelongsTo
    {
        return $this->belongsTo(Negocio::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(TlProject::class, 'teamleader_project_id');
    }
}
