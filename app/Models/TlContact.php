<?php
// app/Models/TlContact.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TlContact extends Model
{
    protected $table = 'tl_contacts';

    // ID es UUID string, no autoincrement
    public $incrementing = false;
    protected $keyType   = 'string';

    protected $fillable = [
        'id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'passport',
        'status',
        'emails',
        'telephones',
        'addresses',
        'custom_fields',
        'tags',
        'raw_data',
        'tl_added_at',
        'tl_updated_at',
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
            ->where('entity_type', 'contact');
    }

    public function deals(): HasMany
    {
        return $this->hasMany(TlDeal::class, 'customer_id')
            ->where('customer_type', 'contact');
    }

    public function projects(): HasMany
    {
        return $this->hasMany(TlProject::class, 'customer_id')
            ->where('customer_type', 'contact');
    }

    // ─── Accessors útiles ─────────────────────────

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function getPrimaryEmailAttribute(): ?string
    {
        return collect($this->emails ?? [])
            ->firstWhere('type', 'primary')['email'] ?? $this->email;
    }

    // ─── Scopes ───────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('status', '!=', 'deleted');
    }

    public function scopeByPassport($query, string $passport)
    {
        return $query->where('passport', $passport);
    }

    // ─── Helper para crear desde raw data de TL ───

    public static function fromTeamleader(array $data): static
    {
        $primaryEmail = collect($data['emails'] ?? [])
            ->firstWhere('type', 'primary')['email'] ?? null;

        $primaryPhone = collect($data['telephones'] ?? [])
            ->first()['number'] ?? null;

        // Buscar el campo pasaporte en custom_fields
        $passport = null;
        foreach ($data['custom_fields'] ?? [] as $cf) {
            if (($cf['definition']['id'] ?? null) === '624a9810-53dc-0770-965b-65891c631673') {
                $passport = $cf['value'] ?? null;
                break;
            }
        }

        return static::updateOrCreate(
            ['id' => $data['id']],
            [
                'first_name'    => $data['first_name']  ?? null,
                'last_name'     => $data['last_name']   ?? null,
                'email'         => $primaryEmail,
                'phone'         => $primaryPhone,
                'passport'      => $passport,
                'status'        => $data['status']      ?? null,
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
