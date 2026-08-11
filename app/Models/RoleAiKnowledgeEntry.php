<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class RoleAiKnowledgeEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'assistant_id',
        'user_id',
        'title',
        'content',
        'status',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function assistant()
    {
        return $this->belongsTo(RoleAiAssistant::class, 'assistant_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function excerpt(int $limit = 180): string
    {
        $content = preg_replace('/\s+/', ' ', (string) $this->content) ?: '';

        return Str::limit(trim($content), $limit);
    }
}
