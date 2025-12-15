<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CosTextoAdicional extends Model
{
    use HasFactory;

    protected $table = 'cos_textos_adicionales';

    protected $fillable = [
        'paso_id',
        'nombre',
        'texto',
    ];

    public function paso()
    {
        return $this->belongsTo(CosPaso::class, 'paso_id');
    }
}
