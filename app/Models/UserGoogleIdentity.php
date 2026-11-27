<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserGoogleIdentity extends Model
{
    protected $fillable = ['google_id'];

    protected $hidden = ['google_id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
