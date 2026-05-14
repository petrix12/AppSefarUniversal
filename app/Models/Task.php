<?php
// app/Models/Task.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo; // ← importar

class Task extends Model
{
    use HasFactory;

    const SYSTEMS_USER_ID = 13515;
    const SYSTEMS_EMAILS = [
        'sistemasccs@sefarvzla.com',
    ];

    protected $fillable = [
        'user_id',
        'contact_id',
        'title',
        'description',
        'due_date',
        'status',
        'contact_methods',
        'customer_responded',
        'call_effective',
        'reason_no_effective',
        'interest_level',
        'sale_status',
        'sales_tags',
        'reason_no_interest',
        'product_of_interest',
        'follow_up_date',
        'created_by_user_id',
    ];

    protected $casts = [
        'due_date'       => 'date',
        'follow_up_date' => 'date',
        'contact_methods' => 'array',
        'customer_responded' => 'boolean',
        'call_effective' => 'boolean',
        'interest_level' => 'boolean',
        'sales_tags'     => 'array',
    ];

    // ── Constantes de estado ────────────────────────────────
    const STATUS_PENDING     = 'pending';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_COMPLETED   = 'completed';
    const STATUS_CANCELED    = 'canceled';

    const SALE_STATUS_CONTACTED = 'contacted';
    const SALE_STATUS_ARGUMENT  = 'sales_argument';
    const SALE_STATUS_BUDGET    = 'budget_sent';
    const SALE_STATUS_ANALYSIS  = 'proposal_analysis';
    const SALE_STATUS_PAID      = 'paid';

    const SALES_TAG_NO_CONTACT = 'no_contact';
    const SALES_TAG_FOLLOW_UP  = 'follow_up_interested';
    const SALES_TAG_LOW        = 'low_interest';
    const SALES_TAG_HIGH       = 'high_interest';
    const SALES_TAG_MEETING    = 'wants_meeting';

    const CONTACT_METHOD_CALL     = 'call';
    const CONTACT_METHOD_WHATSAPP = 'whatsapp';
    const CONTACT_METHOD_EMAIL    = 'email';

    public static function saleStatusOptions(): array
    {
        return [
            self::SALE_STATUS_CONTACTED => 'Se contacto',
            self::SALE_STATUS_ARGUMENT  => 'Argumento de venta',
            self::SALE_STATUS_BUDGET    => 'Envio de presupuesto',
            self::SALE_STATUS_ANALYSIS  => 'Analisis de propuesta',
            self::SALE_STATUS_PAID      => 'Pago realizado',
        ];
    }

    public static function salesTagOptions(): array
    {
        return [
            self::SALES_TAG_NO_CONTACT => [
                'label' => 'No se pudo establecer contacto',
                'class' => 'secondary',
            ],
            self::SALES_TAG_FOLLOW_UP => [
                'label' => 'Interesado en seguimiento',
                'class' => 'info',
            ],
            self::SALES_TAG_LOW => [
                'label' => 'Poco interesado',
                'class' => 'warning',
            ],
            self::SALES_TAG_HIGH => [
                'label' => 'Muy interesado',
                'class' => 'success',
            ],
            self::SALES_TAG_MEETING => [
                'label' => 'Quiere una reunion',
                'class' => 'primary',
            ],
        ];
    }

    public static function contactMethodOptions(): array
    {
        return [
            self::CONTACT_METHOD_CALL => [
                'label' => 'Llamada',
                'icon' => 'phone-alt',
            ],
            self::CONTACT_METHOD_WHATSAPP => [
                'label' => 'WhatsApp',
                'icon' => 'comment-dots',
            ],
            self::CONTACT_METHOD_EMAIL => [
                'label' => 'Email / correo',
                'icon' => 'envelope',
            ],
        ];
    }

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

    public function scopeNotAssignedToSystems($query)
    {
        $systemsUserIds = self::systemsUserIds();

        return empty($systemsUserIds)
            ? $query
            : $query->whereNotIn('user_id', $systemsUserIds);
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

    public function isAssignedToSystems(): bool
    {
        if ((int) $this->user_id === self::SYSTEMS_USER_ID) {
            return true;
        }

        if ($this->relationLoaded('assignee') && $this->assignee) {
            return in_array(strtolower((string) $this->assignee->email), self::SYSTEMS_EMAILS, true);
        }

        return User::query()
            ->whereKey($this->user_id)
            ->whereIn('email', self::SYSTEMS_EMAILS)
            ->exists();
    }

    public static function systemsUserIds(): array
    {
        return User::query()
            ->where('id', self::SYSTEMS_USER_ID)
            ->orWhereIn('email', self::SYSTEMS_EMAILS)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    public function saleStatusLabel(): ?string
    {
        return self::saleStatusOptions()[$this->sale_status] ?? null;
    }

    public function hasActiveSalesProgress(): bool
    {
        $tags = collect($this->sales_tags ?? []);

        return $this->hasNonCallContactMethod()
            || $this->customer_responded === true
            || filled($this->sale_status)
            || $tags->diff([self::SALES_TAG_NO_CONTACT])->isNotEmpty();
    }

    public function contactMethodLabels(): array
    {
        $options = self::contactMethodOptions();

        return collect($this->contact_methods ?? [])
            ->map(fn ($method) => $options[$method]['label'] ?? null)
            ->filter()
            ->values()
            ->all();
    }

    public function hasNonCallContactMethod(): bool
    {
        $methods = collect($this->contact_methods ?? []);

        return $methods->contains(self::CONTACT_METHOD_WHATSAPP)
            || $methods->contains(self::CONTACT_METHOD_EMAIL);
    }
}
