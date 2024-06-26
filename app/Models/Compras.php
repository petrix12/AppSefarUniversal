<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Compras extends Model
{
    use HasFactory;

    protected $fillable = [
        'id_user',
        'servicio_hs_id',
        'descripcion',
        'pagado',
        'monto',
        'cuponaplicado',
        'hash_factura',
        'montooriginal',
        'porcentajedescuento'
    ];
}
