<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Book extends Model
{
    use HasFactory;

    protected $fillable = [
        'titulo',
        'subtitulo',
        'autor',
        'editorial',
        'coleccion',
        'fecha',
        'edicion',
        'paginacion',
        'isbn',
        'notas',
        'enlace',
        'claves',
        'catalogador',
    ];
}
