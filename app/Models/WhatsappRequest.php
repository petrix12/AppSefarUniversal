<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsappRequest extends Model
{
    protected $fillable = [
        'phone_number',
        'message',
        'file_url',
        'file_name',
        'status',
        'error_message',
    ];
}
