<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lead extends Model
{
    use HasFactory;

    public const STAGE_NEW = 'nuevo';
    public const STAGES = [
        'nuevo' => 'Nuevo',
        'contactado' => 'Contactado',
        'calificado' => 'Calificado',
        'propuesta_enviada' => 'Propuesta enviada',
        'negociacion' => 'En negociación',
    ];
    public const STATUS_OPEN = 'abierto';
    public const STATUS_CONVERTED = 'convertido';
    public const STATUS_LOST = 'perdido';

    protected $fillable = [
        'nombre',
        'hubspot_id',
        'email',
        'telefono',
        'passport',
        'origen',
        'etapa',
        'estado',
        'user_id',
        'converted_user_id',
        'converted_at',
    ];

    protected function casts(): array
    {
        return [
            'converted_at' => 'datetime',
        ];
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function convertedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'converted_user_id');
    }

    public function crmActivities(): HasMany
    {
        return $this->hasMany(CrmActivity::class);
    }

    public function crmQuotes(): HasMany
    {
        return $this->hasMany(CrmQuote::class);
    }
}
