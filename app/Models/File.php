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
        'IDPersonaNew',
        'migradoNuevoID',
        'user_id'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'IDCliente', 'passport'); 
    }

}
