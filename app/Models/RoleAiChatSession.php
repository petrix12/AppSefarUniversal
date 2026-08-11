<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RoleAiChatSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'assistant_id',
        'user_id',
        'session_id',
        'messages',
        'expires_at',
    ];

    protected $casts = [
        'messages' => 'array',
        'expires_at' => 'datetime',
    ];

    public function assistant()
    {
        return $this->belongsTo(RoleAiAssistant::class, 'assistant_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
