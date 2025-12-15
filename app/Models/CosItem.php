<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CosItem extends Model
{
    use HasFactory;

    protected $table = 'cos_items';

    protected $fillable = [
        'paso_id',
        'subfase_id',
        'tipo',
        'texto',
        'url',
    ];

    public function paso()
    {
        return $this->belongsTo(CosPaso::class, 'paso_id');
    }

    public function subfase()
    {
        return $this->belongsTo(CosSubfase::class, 'subfase_id');
    }
}
