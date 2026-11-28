<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RegistrationFollowupState extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['stopped_at' => 'datetime', 'responded_at' => 'datetime'];

    public function user() { return $this->belongsTo(User::class); }
}
