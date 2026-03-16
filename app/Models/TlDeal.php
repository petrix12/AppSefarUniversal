<?php
// app/Models/TlDeal.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TlDeal extends Model
{
    protected $table    = 'tl_deals';
    public $incrementing = false;
    protected $keyType  = 'string';

    protected $fillable = [
        'id', 'title', 'status', 'amount', 'currency',
        'weighted_revenue', 'customer_id', 'customer_type',
        'responsible_user_id', 'estimated_closing_date',
        'source', 'custom_fields', 'tags', 'raw_data',
        'tl_created_at', 'tl_updated_at',
    ];

    protected $casts = [
        'amount'                 => 'decimal:2',
        'custom_fields'          => 'array',
        'tags'                   => 'array',
        'raw_data'               => 'array',
        'estimated_closing_date' => 'date',
        'tl_created_at'          => 'datetime',
        'tl_updated_at'          => 'datetime',
    ];

    // ─── Relaciones ───────────────────────────────

    public function documents(): HasMany
    {
        return $this->hasMany(TlDocument::class, 'entity_id')
            ->where('entity_type', 'deal');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(TlContact::class, 'customer_id')
            ->where('customer_type', 'contact');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(TlCompany::class, 'customer_id')
            ->where('customer_type', 'company');
    }

    // ─── Scopes ───────────────────────────────────

    public function scopeWon($query)    { return $query->where('status', 'won'); }
    public function scopeLost($query)   { return $query->where('status', 'lost'); }
    public function scopeOpen($query)   { return $query->where('status', 'open'); }

    // ─── Helper ───────────────────────────────────

    public static function fromTeamleader(array $data): static
    {
        $customer = $data['lead']['customer'] ?? null;

        return static::updateOrCreate(
            ['id' => $data['id']],
            [
                'title'                  => $data['title']  ?? null,
                'status'                 => $data['status'] ?? null,
                'amount'                 => $data['estimated_value']['amount'] ?? null,
                'currency'               => $data['estimated_value']['currency'] ?? null,
                'weighted_revenue'       => $data['weighted_revenue']['amount'] ?? null,
                'customer_id'            => $customer['id']   ?? null,
                'customer_type'          => $customer['type'] ?? null,
                'responsible_user_id'    => $data['responsible_user']['id'] ?? null,
                'estimated_closing_date' => $data['estimated_closing_date'] ?? null,
                'source'                 => $data['source']['id'] ?? null,
                'custom_fields'          => $data['custom_fields'] ?? [],
                'tags'                   => $data['tags'] ?? [],
                'raw_data'               => $data,
                'tl_created_at'          => $data['created_at'] ?? null,
                'tl_updated_at'          => $data['updated_at'] ?? null,
            ]
        );
    }

    // Agregar en TlDeal:

    public function invoices(): HasMany
    {
        return $this->hasMany(TlInvoice::class, 'deal_id');
    }
}
