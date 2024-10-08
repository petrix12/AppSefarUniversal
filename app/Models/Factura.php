<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Factura extends Model
{
    use HasFactory;

    protected $fillable = [
        'id_cliente',
        'hash_factura',
        'met',
        'idcus',
        'idcharge'
    ];

    public function compras()
    {
        return $this->hasMany(Compras::class, 'hash_factura', 'hash_factura');
    }
}
