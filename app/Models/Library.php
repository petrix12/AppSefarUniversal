<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Library extends Model
{
    use HasFactory;

    protected $fillable = [
        'documento',
        'formato',
        'tipo',
        'fuente',
        'origen',
        'ubicacion',
        'ubicacion_ant',
        'busqueda',
        'notas',
        'enlace',
        'anho_ini', 
        'anho_fin',
        'pais',
        'ciudad',
        'FIncorporacion',
        'responsabilidad',
        'edicion',
        'editorial',
        'anho_publicacion',
        'no_vol',
        'coleccion',
        'colacion',
        'isbn',
        'serie',
        'no_clasificacion',
        'titulo_revista',
        'resumen',
        'caratula_url',
        'usuario'
    ];
}
