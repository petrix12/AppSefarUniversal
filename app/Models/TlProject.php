<?php
// app/Models/TlProject.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TlProject extends Model
{
    protected $table    = 'tl_projects';
    public $incrementing = false;
    protected $keyType  = 'string';

    protected $fillable = [
        'id', 'title', 'status', 'customer_id', 'customer_type',
        'responsible_user_id', 'budget_amount', 'budget_currency',
        'starts_on', 'due_on', 'description',
        'participants', 'milestones', 'custom_fields', 'tags', 'raw_data',
        'tl_created_at', 'tl_updated_at',
    ];

    protected $casts = [
        'budget_amount'  => 'decimal:2',
        'starts_on'      => 'date',
        'due_on'         => 'date',
        'participants'   => 'array',
        'milestones'     => 'array',
        'custom_fields'  => 'array',
        'tags'           => 'array',
        'raw_data'       => 'array',
        'tl_created_at'  => 'datetime',
        'tl_updated_at'  => 'datetime',
    ];

    // ─── Relaciones ───────────────────────────────

    public function documents(): HasMany
    {
        return $this->hasMany(TlDocument::class, 'entity_id')
            ->where('entity_type', 'project');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(TlContact::class, 'customer_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(TlCompany::class, 'customer_id');
    }

    // ─── Scopes ───────────────────────────────────

    public function scopeActive($query)    { return $query->where('status', 'active'); }
    public function scopeCompleted($query) { return $query->where('status', 'done'); }

    // ─── Accessors ────────────────────────────────

    public function getCustomFieldValueAttribute(): ?string
    {
        // Obtener valor del campo PRODUCTO
        $productoId = 'fcd48891-20f6-049a-a05f-f78a6f951b4d';

        return collect($this->custom_fields ?? [])
            ->first(fn($cf) => ($cf['definition']['id'] ?? null) === $productoId)['value'] ?? null;
    }

    // ─── Helper ───────────────────────────────────

    public static function fromTeamleader(array $data): static
    {
        $customer = $data['customer'] ?? $data['lead']['customer'] ?? null;
        $budget = $data['budget'] ?? $data['estimated_budget'] ?? [];
        $responsibleUser = $data['responsible_user'] ?? [];

        $customer = is_array($customer) ? $customer : [];
        $budget = is_array($budget) ? $budget : [];
        $responsibleUser = is_array($responsibleUser) ? $responsibleUser : [];

        return static::updateOrCreate(
            ['id' => $data['id']],
            [
                'title'               => $data['title'] ?? null,
                'status'              => $data['status'] ?? null,
                'customer_id'         => $customer['id'] ?? null,
                'customer_type'       => $customer['type'] ?? null,
                'responsible_user_id' => $responsibleUser['id'] ?? null,
                'budget_amount'       => $budget['amount'] ?? null,
                'budget_currency'     => $budget['currency'] ?? null,
                'starts_on'           => $data['starts_on'] ?? null,
                'due_on'              => $data['due_on'] ?? null,
                'description'         => $data['description'] ?? null,
                'participants'        => $data['participants'] ?? [],
                'milestones'          => $data['milestones'] ?? [],
                'custom_fields'       => $data['custom_fields'] ?? [],
                'tags'                => $data['tags'] ?? [],
                'raw_data'            => $data,
                'tl_created_at'       => $data['created_at'] ?? null,
                'tl_updated_at'       => $data['updated_at'] ?? null,
            ]
        );
    }

    // Agregar en TlProject:

    public function invoices(): HasMany
    {
        return $this->hasMany(TlInvoice::class, 'project_id');
    }
}
