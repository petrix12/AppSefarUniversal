<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Cos extends Model
{
    use HasFactory;

    protected $table = 'cos';

    protected $fillable = [
        'nombre',
    ];

    public function fases()
    {
        return $this->hasMany(CosFase::class);
    }
}
