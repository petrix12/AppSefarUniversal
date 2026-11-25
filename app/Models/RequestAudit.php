<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class RequestAudit extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'email',
        'is_authenticated',
        'method',
        'route_name',
        'url',
        'path',
        'ip_address',
        'user_agent',
        'visited_at',
    ];

    protected $casts = [
        'is_authenticated' => 'boolean',
        'visited_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(static function (self $audit): void {
            if ($audit->user_id === null) {
                throw new LogicException('Una auditoría de solicitudes requiere un usuario.');
            }
        });
    }
}
