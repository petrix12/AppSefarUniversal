<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CosSubfase extends Model
{
    use HasFactory;

    protected $table = 'cos_subfases';

    protected $fillable = [
        'paso_id',
        'titulo',
    ];

    public function paso()
    {
        return $this->belongsTo(CosPaso::class, 'paso_id');
    }

    public function items()
    {
        return $this->hasMany(CosItem::class, 'subfase_id');
    }
}
