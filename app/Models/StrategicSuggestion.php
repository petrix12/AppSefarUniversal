<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StrategicSuggestion extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'subject',
        'message',
        'status',
        'submitted_at',
        'last_reply_at',
        'closed_at',
        'updated_by',
        'change_log',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'last_reply_at' => 'datetime',
        'closed_at' => 'datetime',
        'change_log' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function replies()
    {
        return $this->hasMany(StrategicSuggestionReply::class, 'suggestion_id')
            ->orderBy('created_at');
    }

    public function attachments()
    {
        return $this->hasMany(StrategicSuggestionAttachment::class, 'suggestion_id')
            ->latest();
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'recibida' => 'Recibida',
            'en_revision' => 'En revisión',
            'respondida' => 'Respondida',
            'cerrada' => 'Cerrada',
            default => ucfirst($this->status),
        };
    }

    public function addLog(string $action, ?int $userId = null, array $extra = []): void
    {
        $log = $this->change_log ?? [];

        $log[] = [
            'action' => $action,
            'user_id' => $userId,
            'timestamp' => now()->format('Y-m-d H:i:s'),
            'extra' => $extra,
        ];

        $this->update([
            'change_log' => $log,
        ]);
    }
}
