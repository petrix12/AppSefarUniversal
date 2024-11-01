<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GeneralCoupon extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'start_date',
        'end_date',
        'newdiscount'
    ];

    public function setTitleAttribute($value)
    {
        // Eliminar todos los tipos de espacios en blanco
        $value = preg_replace('/\s+/', '', $value);
        // Convertir a mayúsculas
        $this->attributes['title'] = strtoupper($value);
    }
}
