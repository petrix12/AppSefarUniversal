<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BrightbeanSetting extends Model
{
    protected $fillable = ['id', 'ai_enabled', 'instructions', 'last_synced_at'];

    protected $casts = ['ai_enabled' => 'boolean', 'last_synced_at' => 'datetime'];

    public static function current(): self
    {
        return self::firstOrCreate(['id' => 1], ['ai_enabled' => false]);
    }
}
