<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FamilyGroupMember extends Model
{
    use HasFactory;

    protected $fillable = [
        'family_group_id',
        'user_id',
        'anchor_agcliente_id',
        'IDCliente',
        'display_name',
        'source',
        'confidence',
        'match_type',
        'match_reasons',
        'evidence',
        'added_by',
    ];

    protected $casts = [
        'match_reasons' => 'array',
        'evidence' => 'array',
    ];

    public function group()
    {
        return $this->belongsTo(FamilyGroup::class, 'family_group_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function anchorPerson()
    {
        return $this->belongsTo(Agcliente::class, 'anchor_agcliente_id');
    }
}
