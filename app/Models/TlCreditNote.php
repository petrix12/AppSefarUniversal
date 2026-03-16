<?php
// app/Models/TlCreditNote.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class TlCreditNote extends Model
{
    protected $table     = 'tl_credit_notes';
    public $incrementing = false;
    protected $keyType   = 'string';

    protected $fillable = [
        'id', 'credit_note_number', 'status',
        'customer_id', 'customer_type', 'customer_name',
        'total_price_excl_tax', 'total_price_incl_tax', 'currency',
        'invoice_id', 'credit_note_date',
        'pdf_s3_path', 'pdf_s3_disk',
        'pdf_downloaded', 'pdf_downloaded_at',
        'invoice_lines', 'custom_fields', 'raw_data',
        'tl_created_at', 'tl_updated_at',
    ];

    protected $casts = [
        'total_price_excl_tax' => 'decimal:2',
        'total_price_incl_tax' => 'decimal:2',
        'credit_note_date'     => 'date',
        'pdf_downloaded'       => 'boolean',
        'pdf_downloaded_at'    => 'datetime',
        'invoice_lines'        => 'array',
        'custom_fields'        => 'array',
        'raw_data'             => 'array',
        'tl_created_at'        => 'datetime',
        'tl_updated_at'        => 'datetime',
    ];

    // ─── Relaciones ───────────────────────────────

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(TlInvoice::class, 'invoice_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(TlContact::class, 'customer_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(TlCompany::class, 'customer_id');
    }

    // ─── Accessors ────────────────────────────────

    public function getPdfUrlAttribute(): ?string
    {
        if (!$this->pdf_s3_path) return null;

        return Storage::disk($this->pdf_s3_disk)
            ->temporaryUrl($this->pdf_s3_path, now()->addMinutes(60));
    }

    // ─── Helper ───────────────────────────────────

    public static function fromTeamleader(array $data): static
    {
        $customer  = $data['invoicee']['customer']   ?? null;
        $totalExcl = $data['total']['tax_exclusive'] ?? null;
        $totalIncl = $data['total']['tax_inclusive'] ?? null;

        return static::updateOrCreate(
            ['id' => $data['id']],
            [
                'credit_note_number'    => $data['credit_note_number'] ?? null,
                'status'                => $data['status']             ?? null,
                'customer_id'           => $customer['id']             ?? null,
                'customer_type'         => $customer['type']           ?? null,
                'customer_name'         => $data['invoicee']['name']   ?? null,
                'total_price_excl_tax'  => $totalExcl['amount']        ?? null,
                'total_price_incl_tax'  => $totalIncl['amount']        ?? null,
                'currency'              => $totalExcl['currency']      ?? null,
                'invoice_id'            => $data['original_invoice']['id'] ?? null,
                'credit_note_date'      => $data['credit_note_date']   ?? null,
                'invoice_lines'         => $data['invoice_lines']      ?? [],
                'custom_fields'         => $data['custom_fields']      ?? [],
                'raw_data'              => $data,
                'tl_created_at'         => $data['created_at']         ?? null,
                'tl_updated_at'         => $data['updated_at']         ?? null,
            ]
        );
    }
}
