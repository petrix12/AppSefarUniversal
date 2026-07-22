<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BancaOnlineDocumentRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'country_slug',
        'plan_slug',
        'document_name',
        'document_type',
        'match_keywords',
        'recommended_service_id',
        'recommended_plan_slug',
        'client_label',
        'client_explanation',
        'internal_notes',
        'required',
        'active',
        'client_visible',
        'priority',
        'sort_order',
    ];

    protected $casts = [
        'match_keywords' => 'array',
        'required' => 'boolean',
        'active' => 'boolean',
        'client_visible' => 'boolean',
        'priority' => 'integer',
        'sort_order' => 'integer',
    ];

    public function recommendedService(): BelongsTo
    {
        return $this->belongsTo(Servicio::class, 'recommended_service_id');
    }
}
