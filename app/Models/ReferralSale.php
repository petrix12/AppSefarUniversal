<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReferralSale extends Model
{
    use HasFactory;

    protected $fillable = [
        'coordinator_referral_code_id',
        'coordinator_user_id',
        'buyer_user_id',
        'hash_factura',
        'code',
        'amount',
        'currency',
        'commission_amount',
        'commission_status',
    ];

    protected $casts = [
        'amount' => 'float',
        'commission_amount' => 'float',
    ];

    public function referralCode()
    {
        return $this->belongsTo(CoordinatorReferralCode::class, 'coordinator_referral_code_id');
    }

    public function coordinator()
    {
        return $this->belongsTo(User::class, 'coordinator_user_id');
    }

    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_user_id');
    }
}
