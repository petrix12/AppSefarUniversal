<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CrmQuote extends Model
{
    public const STATUSES = [
        'draft' => 'Borrador',
        'sent' => 'Enviado',
        'accepted' => 'Aceptado',
        'rejected' => 'Rechazado',
        'expired' => 'Vencido',
    ];

    protected $fillable = [
        'quote_number', 'lead_id', 'client_id', 'created_by_user_id', 'title', 'description',
        'subtotal', 'tax_amount', 'total', 'currency', 'status', 'valid_until',
    ];

    protected function casts(): array
    {
        return ['valid_until' => 'date', 'subtotal' => 'decimal:2', 'tax_amount' => 'decimal:2', 'total' => 'decimal:2'];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(CrmQuoteItem::class)->orderBy('position');
    }
}
