<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Hermano extends Model
{
    use HasFactory;

    protected $table = 'hermanos';

    protected $fillable = [
        'id_main',
        'id_hermano'
    ];

    public function usuarioPrincipal()
    {
        return $this->belongsTo(User::class, 'id_main')->select(['id', 'name', 'email', 'nombres', 'apellidos']); 
    }

    public function hermano()
    {
        return $this->belongsTo(User::class, 'id_hermano')->select(['id', 'name', 'email', 'nombres', 'apellidos']);
    }
}
