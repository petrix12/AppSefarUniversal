<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ChatSession extends Model
{
    protected $fillable = ['session_id', 'messages', 'expires_at'];

    protected $casts = [
        'messages' => 'array',
    ];

    public static function createSession()
    {
        return self::create([
            'session_id' => Str::uuid()->toString(),
            'messages' => [],
            'expires_at' => now()->addMinutes(30),
        ]);
    }
}
