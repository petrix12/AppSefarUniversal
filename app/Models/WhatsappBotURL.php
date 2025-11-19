<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Modelo para la URL única del Bot de WhatsApp.
 * Se mapea a la tabla 'whatsapp_bot_u_r_l'
 */
class WhatsappBotURL extends Model
{
    use HasFactory;

    // Nombre de la tabla
    protected $table = 'whatsapp_bot_u_r_l';

    // Campos que se pueden llenar masivamente
    protected $fillable = [
        'url',
    ];
}
