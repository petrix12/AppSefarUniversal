<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmActivity extends Model
{
    public const TYPES = [
        'call' => 'Llamada',
        'email' => 'Correo',
        'whatsapp' => 'WhatsApp',
        'meeting' => 'Reunión',
        'note' => 'Nota',
        'other' => 'Otro',
    ];

    protected $fillable = [
        'lead_id', 'client_id', 'created_by_user_id', 'type', 'subject', 'details', 'occurred_at', 'follow_up_at',
    ];

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime', 'follow_up_at' => 'datetime'];
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
}
