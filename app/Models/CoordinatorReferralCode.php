<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CoordinatorReferralCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'coordinator_user_id',
        'code',
        'active',
        'last_sent_at',
    ];

    protected $casts = [
        'active' => 'boolean',
        'last_sent_at' => 'datetime',
    ];

    public function coordinator()
    {
        return $this->belongsTo(User::class, 'coordinator_user_id');
    }

    public function sales()
    {
        return $this->hasMany(ReferralSale::class);
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}
