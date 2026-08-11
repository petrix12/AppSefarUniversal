<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Role;

class RoleAiAssistant extends Model
{
    use HasFactory;

    protected $fillable = [
        'role_id',
        'name',
        'model',
        'instructions',
        'is_active',
        'training_enabled',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'training_enabled' => 'boolean',
    ];

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function knowledgeEntries()
    {
        return $this->hasMany(RoleAiKnowledgeEntry::class, 'assistant_id');
    }

    public function activeKnowledgeEntries()
    {
        return $this->knowledgeEntries()->where('status', 'active');
    }

    public function chatSessions()
    {
        return $this->hasMany(RoleAiChatSession::class, 'assistant_id');
    }
}
