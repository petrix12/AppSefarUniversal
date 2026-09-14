<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class File extends Model
{
    use HasFactory;

    protected $fillable = [
        'file',
        'location',
        'tipo',
        'propietario',
        'IDCliente',
        'notas',
        'IDPersona',
        'IDPersonaNew',
        'migradoNuevoID',
        'user_id',
        'source',
        'client_visible',
        'document_kind',
        'mime_type',
        'size_bytes',
        'source_reference',
        'document_request_id',
    ];

    protected $casts = [
        'client_visible' => 'boolean',
        'migradoNuevoID' => 'boolean',
        'size_bytes' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'IDCliente', 'passport'); 
    }

    public function people()
    {
        return $this->belongsToMany(Agcliente::class, 'genealogy_document_person', 'file_id', 'person_id')
            ->withPivot('relationship')
            ->withTimestamps();
    }

    public function genealogyUnions()
    {
        return $this->belongsToMany(GenealogyUnion::class, 'genealogy_document_union', 'file_id', 'genealogy_union_id')
            ->withTimestamps();
    }

}
