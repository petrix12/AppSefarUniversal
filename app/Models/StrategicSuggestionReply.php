<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StrategicSuggestionReply extends Model
{
    protected $fillable = [
        'suggestion_id',
        'user_id',
        'message',
        'is_admin_reply',
    ];

    protected $casts = [
        'is_admin_reply' => 'boolean',
    ];

    public function suggestion()
    {
        return $this->belongsTo(StrategicSuggestion::class, 'suggestion_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function attachments()
    {
        return $this->hasMany(StrategicSuggestionAttachment::class, 'reply_id')
            ->latest();
    }
}
