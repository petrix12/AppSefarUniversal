<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CosPaso extends Model
{
    use HasFactory;

    protected $table = 'cos_pasos';

    protected $fillable = [
        'fase_id',
        'numero',
        'titulo',
        'nombre_corto',
        'promesa',
        'main_cta_texto',
        'main_cta_url',
    ];

    public function fase()
    {
        return $this->belongsTo(CosFase::class, 'fase_id');
    }

    public function subfases()
    {
        return $this->hasMany(CosSubfase::class, 'paso_id');
    }

    public function items()
    {
        return $this->hasMany(CosItem::class, 'paso_id');
    }

    public function textosAdicionales()
    {
        return $this->hasMany(CosTextoAdicional::class, 'paso_id');
    }
}
