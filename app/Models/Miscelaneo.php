<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Miscelaneo extends Model
{
    use HasFactory;

    protected $fillable = [
        'titulo',
        'autor',
        'publicado',
        'editorial',
        'volumen',
        'paginacion',
        'isbn',
        'notas',
        'enlace',
        'claves',
        'material',
        'catalogador',
    ];
}
