<?php

namespace App\Models;

use App\Models\User;

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
        'porcentajedescuento',
        'deal_id',
        'phasenum'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'id_user');
    }

    public function factura()
    {
        return $this->belongsTo(Factura::class, 'hash_factura', 'hash_factura');
    }
}
