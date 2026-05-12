<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientChatAttachment extends Model
{
    protected $fillable = [
        'message_id',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size',
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(ClientChatMessage::class, 'message_id');
    }

    public function downloadUrl(): string
    {
        return route('crud.users.internal-chat.attachments.download', [
            'user' => $this->message->client_id,
            'attachment' => $this,
        ]);
    }
}
