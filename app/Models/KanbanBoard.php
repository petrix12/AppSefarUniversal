<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KanbanBoard extends Model
{
    protected $fillable = ['name', 'description', 'created_by_user_id'];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function columns(): HasMany
    {
        return $this->hasMany(KanbanColumn::class)->orderBy('sort_order')->orderBy('id');
    }
}
