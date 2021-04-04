<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class File extends Model
{
    use HasFactory;

    protected $fillable = [
        'file',
        'location',
        'tipo',
        'propietario',
        'IDCliente',
        'notas',
        'IDPersona',
        'user_id'
    ];

}
