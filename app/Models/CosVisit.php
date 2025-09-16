<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CosVisit extends Model
{
    // Nombre de la tabla
    protected $table = 'cos_visitas';

    // Campos que se pueden asignar masivamente
    protected $fillable = [
        'user_id',
        'cliente_id',
        'fecha_visita',
    ];

    // Relación: una visita pertenece a un usuario
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cliente_id');
    }
}
