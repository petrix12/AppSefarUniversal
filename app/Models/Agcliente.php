<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Agcliente extends Model
{
    use HasFactory;

    protected $table = 'agclientes';

    protected $fillable = [
        'id',
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
        'referido',
        'FTM',
        'FUpdate',
        'Usuario',
    ];

    // Filtro de búsqueda
    public function scopeBuscar($query, $search){
        return $query->where('IDCliente','LIKE',"%$search%")
        ->orWhere('Nombres','LIKE',"%$search%")
        ->orWhere('Apellidos','LIKE',"%$search%")
        ->orWhere('NPasaporte','LIKE',"%$search%")
        ->orWhere('PaisPasaporte','LIKE',"%$search%")
        ->orWhere('NDocIdent','LIKE',"%$search%")
        ->orWhere('PaisDocIdent','LIKE',"%$search%")
        ->orWhere('PaisDocIdent','LIKE',"%$search%")
        ->orWhere('LugarNac','LIKE',"%$search%")
        ->orWhere('PaisNac','LIKE',"%$search%")
        ->orWhere('LugarBtzo','LIKE',"%$search%")
        ->orWhere('PaisBtzo','LIKE',"%$this->search%")
        ->orWhere('LugarMatr','LIKE',"%$search%")
        ->orWhere('PaisMatr','LIKE',"%$search%")
        ->orWhere('PaisDef','LIKE',"%$search%")
        ->orWhere('Observaciones','LIKE',"%$search%")
        ->orWhere('NombresF','LIKE',"%$search%")
        ->orWhere('ApellidosF','LIKE',"%$search%")
        ->orWhere('NPasaporteF','LIKE',"%$search%")
        ->orWhere('PNacimiento','LIKE',"%$search%")
        ->orWhere('LNacimiento','LIKE',"%$search%")
        ->orWhere('Usuario','LIKE',"%$search%");
    }

    // Filtro para clientes referidos
    public function scopeRol($query){
        // Clientes con el rol Traviesoevans
        if(Auth()->user()->hasRole('Traviesoevans')){
            return $query->where('referido','Travieso Evans');
        }
        
        // Clientes con el rol Vargassequera
        if(Auth()->user()->hasRole('Vargassequera')){
            return $query->where('referido','Patricia Vargas Sequera');
        }
        
        // Clientes con el rol Badell Law
        if(Auth()->user()->hasRole('BadellLaw')){
            return $query->where('referido','Badell Law');
        }
        
        // Clientes con el rol P & V Abogados
        if(Auth()->user()->hasRole('P&V-Abogados')){
            return $query->where('referido','P & V Abogados');
        }
    }

    // Filtro para ver solo clientes
    public function scopeClientes($query, $solo_clientes){
        if($solo_clientes){
            return $query->where('IDPersona',1);
        }
    }
}