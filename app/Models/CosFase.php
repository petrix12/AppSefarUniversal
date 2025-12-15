<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CosFase extends Model
{
    use HasFactory;

    protected $table = 'cos_fases';

    protected $fillable = [
        'cos_id',
        'numero',
        'orden',
        'titulo',
    ];

    public function cos()
    {
        return $this->belongsTo(Cos::class);
    }

    public function pasos()
    {
        return $this->hasMany(CosPaso::class, 'fase_id');
    }
}
