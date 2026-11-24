<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmQuoteItem extends Model
{
    protected $fillable = ['description', 'quantity', 'unit_price', 'amount', 'position'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:2', 'unit_price' => 'decimal:2', 'amount' => 'decimal:2'];
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(CrmQuote::class, 'crm_quote_id');
    }
}
