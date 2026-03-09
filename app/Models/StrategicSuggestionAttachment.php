<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class StrategicSuggestionAttachment extends Model
{
    protected $fillable = [
        'suggestion_id',
        'reply_id',
        'uploaded_by',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size',
    ];

    public function suggestion()
    {
        return $this->belongsTo(StrategicSuggestion::class, 'suggestion_id');
    }

    public function reply()
    {
        return $this->belongsTo(StrategicSuggestionReply::class, 'reply_id');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function downloadUrl(): string
    {
        return route('strategic-suggestions.attachments.download', $this);
    }

    public function existsOnDisk(): bool
    {
        return Storage::disk($this->disk)->exists($this->path);
    }
}
