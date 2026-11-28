<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppMailLog extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'recipients' => 'array', 'is_test' => 'boolean',
        'accepted_at' => 'datetime', 'delivered_at' => 'datetime',
        'bounced_at' => 'datetime', 'complained_at' => 'datetime',
    ];

    public const STATUSES = [
        'sending' => 'Enviando', 'accepted' => 'Aceptado por el proveedor',
        'delivered' => 'Entregado al servidor', 'bounced' => 'Rebote',
        'complained' => 'Queja por spam', 'failed' => 'Error de envío',
    ];
}
