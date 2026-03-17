<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\TlInvoice;

class Invoice extends Model
{
    protected $fillable = [
        'invoice_number',
        'user_id',           // quien crea
        'customer_user_id',  // cliente asociado
        'customer_name',
        'customer_email',
        'customer_vat',
        'customer_address',
        'customer_country',
        'invoice_date',
        'expiry_date',
        'currency',
        'status',
        'notes',
        'total_excl_tax',
        'total_tax',
        'total_incl_tax',
        'paid_date',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'expiry_date'  => 'date',
        'paid_date'    => 'date',
    ];

    // Quien creó la factura (admin/empleado)
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // El cliente al que va dirigida
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_user_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class)->orderBy('sort_order');
    }

    public function recalculate(): void
    {
        $excl = $this->lines->sum('total');
        $tax  = $this->lines->sum(fn($l) => round($l->total * ($l->tax_rate / 100), 2));

        $this->update([
            'total_excl_tax' => $excl,
            'total_tax'      => $tax,
            'total_incl_tax' => $excl + $tax,
        ]);
    }

    public static function nextNumber(): string
    {
        $year = date('Y');

        $last = static::where('invoice_number', 'like', "$year / %")
            ->get()
            ->map(fn($i) => (int) trim(explode('/', $i->invoice_number)[1] ?? 0))
            ->max();

        $lastTl = TlInvoice::all()
            ->filter(fn($i) => str_starts_with(trim($i->invoice_number ?? ''), $year))
            ->map(function ($i) {
                $parts = explode('/', $i->invoice_number ?? '');
                return isset($parts[1]) ? (int) trim($parts[1]) : 0;
            })
            ->max();

        $next = max($last ?? 0, $lastTl ?? 0) + 1;

        return "$year / $next";
    }

    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'draft'  => 'secondary',
            'sent'   => 'info',
            'paid'   => 'success',
            default  => 'secondary',
        };
    }
}
