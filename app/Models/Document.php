<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    protected $guarded = [];

    public function scopeOrderedForLibrary($query)
    {
        return $query
            ->orderByRaw("CASE WHEN title REGEXP '^[0-9]+' THEN 0 ELSE 1 END")
            ->orderByRaw("CAST(title AS UNSIGNED)")
            ->orderBy('title')
            ->orderByDesc('id');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
