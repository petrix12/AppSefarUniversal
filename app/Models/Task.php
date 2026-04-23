<?php
// app/Models/Task.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo; // ← importar

class Task extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'contact_id',
        'title',
        'description',
        'due_date',
        'status',
        'call_effective',
        'reason_no_effective',
        'interest_level',
        'reason_no_interest',
        'product_of_interest',
        'follow_up_date',
        'created_by_user_id',
    ];

    protected $casts = [
        'due_date'       => 'date',
        'follow_up_date' => 'date',
        'call_effective' => 'boolean',
        'interest_level' => 'boolean',
    ];

    // ── Constantes de estado ────────────────────────────────
    const STATUS_PENDING     = 'pending';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_COMPLETED   = 'completed';
    const STATUS_CANCELED    = 'canceled';

    // ── Relaciones ──────────────────────────────────────────
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(User::class, 'contact_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    // ── Scopes ──────────────────────────────────────────────
    public function scopeForToday($query)
    {
        return $query->whereDate('due_date', today());
    }

    public function scopeForDate($query, $date)
    {
        return $query->whereDate('due_date', $date);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeOpen($query)
    {
        return $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_IN_PROGRESS]);
    }

    // ── Helpers ─────────────────────────────────────────────
    public function isClosed(): bool
    {
        return in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_CANCELED]);
    }

    public function isOwnedBy(int $userId): bool
    {
        return $this->user_id === $userId;
    }
}
