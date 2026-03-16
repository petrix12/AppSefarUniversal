<?php
// app/Models/TlSyncLog.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TlSyncLog extends Model
{
    protected $table = 'tl_sync_logs';

    protected $fillable = [
        'entity', 'status', 'total',
        'processed', 'failed', 'error_message',
        'started_at', 'finished_at',
    ];

    protected $casts = [
        'started_at'  => 'datetime',
        'finished_at' => 'datetime',
    ];

    // ─── Helpers ──────────────────────────────────

    public static function start(string $entity): static
    {
        return static::create([
            'entity'     => $entity,
            'status'     => 'running',
            'started_at' => now(),
        ]);
    }

    public function complete(int $total, int $processed, int $failed = 0): void
    {
        $this->update([
            'status'      => $failed > 0 && $processed === 0 ? 'failed' : 'completed',
            'total'       => $total,
            'processed'   => $processed,
            'failed'      => $failed,
            'finished_at' => now(),
        ]);
    }

    public function fail(string $message): void
    {
        $this->update([
            'status'        => 'failed',
            'error_message' => $message,
            'finished_at'   => now(),
        ]);
    }

    /**
     * ✅ Renombrado de increment() → incrementCounter()
     * para no colisionar con Eloquent::increment()
     */
    public function incrementCounter(string $column = 'processed', int $amount = 1): void
    {
        // Usar increment() de Eloquent directamente (hace UPDATE atómico en BD)
        $this->increment($column, $amount);
    }
}
