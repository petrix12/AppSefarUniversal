<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Agcliente extends Model
{
    use HasFactory;

    protected $table = 'agclientes';

    protected $fillable = [
        'IDCliente',
        'IDPersona',
        'IDPadre',
        'IDMadre',
        'Generacion',
        'Nombres',
        'Apellidos',
        'NPasaporte',
        'PaisPasaporte',
        'NDocIdent',
        'PaisDocIdent',
        'Sexo',
        'AnhoNac',
        'MesNac',
        'DiaNac',
        'LugarNac',
        'PaisNac',
        'AnhoBtzo',
        'MesBtzo',
        'DiaBtzo',
        'LugarBtzo',
        'PaisBtzo',
        'AnhoMatr',
        'MesMatr',
        'DiaMatr',
        'LugarMatr',
        'PaisMatr',
        'AnhoDef',
        'MesDef',
        'DiaDef',
        'LugarDef',
        'PaisDef',
        'Vive',
        'Observaciones',
        'Familiaridad',
        'NombresF',
        'ApellidosF',
        'ParentescoF',
        'NPasaporteF',
        'FRegistro',
        'PNacimiento',
        'LNacimiento',
        'Familiares',
        'Enlace',
        'FTM',
        'FUpdate',
        'Usuario',
    ];
}
