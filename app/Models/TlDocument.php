<?php
// app/Models/TlDocument.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class TlDocument extends Model
{
    protected $table    = 'tl_documents';
    public $incrementing = false;
    protected $keyType  = 'string';

    protected $fillable = [
        'id', 'name', 'entity_type', 'entity_id',
        's3_path', 's3_disk', 'mime_type', 'size_bytes',
        'extension', 'downloaded', 'downloaded_at',
        'raw_data', 'tl_created_at', 'tl_updated_at',
    ];

    protected $casts = [
        'downloaded'    => 'boolean',
        'downloaded_at' => 'datetime',
        'raw_data'      => 'array',
        'tl_created_at' => 'datetime',
        'tl_updated_at' => 'datetime',
    ];

    // ─── Relaciones polimórficas ──────────────────

    public function contact()
    {
        return $this->belongsTo(TlContact::class, 'entity_id');
    }

    public function company()
    {
        return $this->belongsTo(TlCompany::class, 'entity_id');
    }

    public function deal()
    {
        return $this->belongsTo(TlDeal::class, 'entity_id');
    }

    public function project()
    {
        return $this->belongsTo(TlProject::class, 'entity_id');
    }

    // ─── Accessors ────────────────────────────────

    /**
     * URL temporal de S3 (válida 60 minutos por defecto)
     */
    public function getTemporaryUrlAttribute(): ?string
    {
        if (!$this->s3_path) return null;

        return Storage::disk($this->s3_disk)
            ->temporaryUrl($this->s3_path, now()->addMinutes(60));
    }

    /**
     * Tamaño legible por humanos
     */
    public function getReadableSizeAttribute(): string
    {
        $bytes = $this->size_bytes ?? 0;

        return match(true) {
            $bytes >= 1_073_741_824 => round($bytes / 1_073_741_824, 2) . ' GB',
            $bytes >= 1_048_576     => round($bytes / 1_048_576, 2)     . ' MB',
            $bytes >= 1_024         => round($bytes / 1_024, 2)         . ' KB',
            default                 => $bytes . ' B',
        };
    }

    // ─── Scopes ───────────────────────────────────

    public function scopePending($query)
    {
        return $query->where('downloaded', false);
    }

    public function scopeDownloaded($query)
    {
        return $query->where('downloaded', true);
    }

    public function scopeForEntity($query, string $type, string $id)
    {
        return $query->where('entity_type', $type)->where('entity_id', $id);
    }

    // ─── Helper ───────────────────────────────────

    public static function fromTeamleader(array $data, string $entityType, string $entityId): static
    {
        $name      = $data['name'] ?? 'sin-nombre';
        $extension = pathinfo($name, PATHINFO_EXTENSION) ?: null;

        return static::updateOrCreate(
            ['id' => $data['id']],
            [
                'name'          => $name,
                'entity_type'   => $entityType,
                'entity_id'     => $entityId,
                'mime_type'     => $data['content_type'] ?? null,
                'size_bytes'    => $data['size']         ?? null,
                'extension'     => $extension,
                'raw_data'      => $data,
                'tl_created_at' => $data['created_at']  ?? null,
                'tl_updated_at' => $data['updated_at']  ?? null,
            ]
        );
    }
}
