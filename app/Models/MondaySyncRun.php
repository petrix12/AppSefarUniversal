<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class MondaySyncRun extends Model
{
    protected $guarded = [];

    protected $casts = [
        'boards' => 'integer',
        'items' => 'integer',
        'subitems' => 'integer',
        'values' => 'integer',
        'requests' => 'integer',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public static function start(string $scope, ?string $boardId = null): static
    {
        return static::create([
            'token' => (string) Str::uuid(),
            'scope' => $scope,
            'board_id' => $boardId,
            'status' => 'running',
            'started_at' => now(),
        ]);
    }

    public function complete(array $counts = []): void
    {
        $this->update(array_merge($counts, [
            'status' => 'completed',
            'finished_at' => now(),
        ]));
    }

    public function fail(\Throwable|string $error): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => mb_substr($error instanceof \Throwable ? $error->getMessage() : $error, 0, 65000),
            'finished_at' => now(),
        ]);
    }
}
