<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FamilyGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'primary_id_cliente',
        'match_key',
        'status',
        'notes',
        'created_by',
        'updated_by',
    ];

    public function members()
    {
        return $this->hasMany(FamilyGroupMember::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
