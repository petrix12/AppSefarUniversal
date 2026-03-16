<?php
// app/Models/TlInvoice.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class TlInvoice extends Model
{
    protected $table     = 'tl_invoices';
    public $incrementing = false;
    protected $keyType   = 'string';

    protected $fillable = [
        'id', 'invoice_number', 'status',
        'customer_id', 'customer_type', 'customer_name',
        'total_price_excl_tax', 'total_price_incl_tax',
        'paid_at_date', 'currency',
        'invoice_date', 'expiry_date', 'paid_date',
        'deal_id', 'project_id',
        'pdf_s3_path', 'pdf_s3_disk',
        'pdf_downloaded', 'pdf_downloaded_at',
        'invoice_lines', 'payment_terms',
        'custom_fields', 'raw_data',
        'tl_created_at', 'tl_updated_at',
    ];

    protected $casts = [
        'total_price_excl_tax' => 'decimal:2',
        'total_price_incl_tax' => 'decimal:2',
        'paid_at_date'         => 'decimal:2',
        'invoice_date'         => 'date',
        'expiry_date'          => 'date',
        'paid_date'            => 'date',
        'pdf_downloaded'       => 'boolean',
        'pdf_downloaded_at'    => 'datetime',
        'invoice_lines'        => 'array',
        'payment_terms'        => 'array',
        'custom_fields'        => 'array',
        'raw_data'             => 'array',
        'tl_created_at'        => 'datetime',
        'tl_updated_at'        => 'datetime',
    ];

    // ─── Relaciones ───────────────────────────────

    public function contact(): BelongsTo
    {
        return $this->belongsTo(TlContact::class, 'customer_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(TlCompany::class, 'customer_id');
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(TlDeal::class, 'deal_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(TlProject::class, 'project_id');
    }

    public function creditNotes(): HasMany
    {
        return $this->hasMany(TlCreditNote::class, 'invoice_id');
    }

    // ─── Scopes ───────────────────────────────────

    public function scopePaid($query)
    {
        return $query->where('status', 'matched');
    }

    public function scopeOutstanding($query)
    {
        return $query->where('status', 'outstanding');
    }

    public function scopeLate($query)
    {
        return $query->where('status', 'late');
    }

    public function scopeWithoutPdf($query)
    {
        return $query->where('pdf_downloaded', false);
    }

    // ─── Accessors ────────────────────────────────

    public function getPdfUrlAttribute(): ?string
    {
        if (!$this->pdf_s3_path) return null;

        return Storage::disk($this->pdf_s3_disk)
            ->temporaryUrl($this->pdf_s3_path, now()->addMinutes(60));
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->status === 'late'
            || ($this->expiry_date && $this->expiry_date->isPast() && $this->status !== 'matched');
    }

    // ─── Helper ───────────────────────────────────

    // En app/Models/TlInvoice.php — reemplaza fromTeamleader()

    public static function fromTeamleader(array $data): static
    {
        $customer  = $data['invoicee']['customer'] ?? null;
        $totalExcl = $data['total']['tax_exclusive'] ?? null;
        $totalIncl = $data['total']['tax_inclusive'] ?? null;

        // deal y project vienen directos, NO en related_to
        $dealId    = $data['deal']['id']    ?? null;
        $projectId = $data['project']['id'] ?? null;

        // Líneas aplanadas desde grouped_lines
        $invoiceLines = [];
        foreach ($data['grouped_lines'] ?? [] as $group) {
            $section = $group['section']['title'] ?? null;
            foreach ($group['line_items'] ?? [] as $line) {
                $invoiceLines[] = array_merge($line, ['_section' => $section]);
            }
        }

        return static::updateOrCreate(
            ['id' => $data['id']],
            [
                'invoice_number'       => $data['invoice_number']   ?? null,
                'status'               => $data['status']           ?? null,
                'customer_id'          => $customer['id']           ?? null,
                'customer_type'        => $customer['type']         ?? null,
                'customer_name'        => $data['invoicee']['name'] ?? null,
                'total_price_excl_tax' => $totalExcl['amount']      ?? null,
                'total_price_incl_tax' => $totalIncl['amount']      ?? null,
                'currency'             => $data['currency']         ?? $totalExcl['currency'] ?? null,
                'invoice_date'         => $data['invoice_date']     ?? null,
                'expiry_date'          => $data['due_on']           ?? null, // ← due_on, no expiry_date
                'paid_date'            => $data['paid_at']          ?? null,
                'deal_id'              => $dealId,
                'project_id'           => $projectId,
                'invoice_lines'        => $invoiceLines,
                'payment_terms'        => $data['payment_term']     ?? null,
                'custom_fields'        => $data['custom_fields']    ?? [],
                'raw_data'             => $data,
                'tl_created_at'        => $data['created_at']       ?? null,
                'tl_updated_at'        => $data['updated_at']       ?? null,
            ]
        );
    }
}
