<?php
// app/Models/TlCompany.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TlCompany extends Model
{
    protected $table    = 'tl_companies';
    public $incrementing = false;
    protected $keyType  = 'string';

    protected $fillable = [
        'id', 'name', 'email', 'phone', 'vat_number',
        'website', 'emails', 'telephones', 'addresses',
        'custom_fields', 'tags', 'raw_data',
        'tl_added_at', 'tl_updated_at',
    ];

    protected $casts = [
        'emails'        => 'array',
        'telephones'    => 'array',
        'addresses'     => 'array',
        'custom_fields' => 'array',
        'tags'          => 'array',
        'raw_data'      => 'array',
        'tl_added_at'   => 'datetime',
        'tl_updated_at' => 'datetime',
    ];

    // ─── Relaciones ───────────────────────────────

    public function documents(): HasMany
    {
        return $this->hasMany(TlDocument::class, 'entity_id')
            ->where('entity_type', 'company');
    }

    public function deals(): HasMany
    {
        return $this->hasMany(TlDeal::class, 'customer_id')
            ->where('customer_type', 'company');
    }

    public function projects(): HasMany
    {
        return $this->hasMany(TlProject::class, 'customer_id')
            ->where('customer_type', 'company');
    }

    // ─── Helper ───────────────────────────────────

    public static function fromTeamleader(array $data): static
    {
        $primaryEmail = collect($data['emails'] ?? [])
            ->firstWhere('type', 'primary')['email'] ?? null;

        $primaryPhone = collect($data['telephones'] ?? [])
            ->first()['number'] ?? null;

        return static::updateOrCreate(
            ['id' => $data['id']],
            [
                'name'          => $data['name']        ?? null,
                'email'         => $primaryEmail,
                'phone'         => $primaryPhone,
                'vat_number'    => $data['vat_number']  ?? null,
                'website'       => $data['website']     ?? null,
                'emails'        => $data['emails']      ?? [],
                'telephones'    => $data['telephones']  ?? [],
                'addresses'     => $data['addresses']   ?? [],
                'custom_fields' => $data['custom_fields'] ?? [],
                'tags'          => $data['tags']        ?? [],
                'raw_data'      => $data,
                'tl_added_at'   => $data['added_at']   ?? null,
                'tl_updated_at' => $data['updated_at'] ?? null,
            ]
        );
    }

    // Agregar en TlContact y TlCompany:

    public function invoices(): HasMany
    {
        return $this->hasMany(TlInvoice::class, 'customer_id');
    }

    public function creditNotes(): HasMany
    {
        return $this->hasMany(TlCreditNote::class, 'customer_id');
    }
}
