<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GenealogyUnion extends Model
{
    use HasFactory;

    protected $fillable = [
        'IDCliente',
        'spouse_one_id',
        'spouse_two_id',
        'marriage_date',
        'marriage_place',
        'marriage_country',
        'created_by',
    ];

    public function spouseOne()
    {
        return $this->belongsTo(Agcliente::class, 'spouse_one_id');
    }

    public function spouseTwo()
    {
        return $this->belongsTo(Agcliente::class, 'spouse_two_id');
    }

    public function files()
    {
        return $this->belongsToMany(File::class, 'genealogy_document_union', 'genealogy_union_id', 'file_id')
            ->withTimestamps();
    }
}
