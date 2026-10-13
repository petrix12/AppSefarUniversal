<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KanbanColumn extends Model
{
    protected $fillable = ['kanban_board_id', 'name', 'sort_order'];

    public function board(): BelongsTo
    {
        return $this->belongsTo(KanbanBoard::class, 'kanban_board_id');
    }

    public function cards(): HasMany
    {
        return $this->hasMany(KanbanCard::class)->orderBy('sort_order')->orderBy('id');
    }
}
