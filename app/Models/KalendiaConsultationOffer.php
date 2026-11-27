<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class KalendiaConsultationOffer extends Model
{
    protected $guarded = [];
    protected $casts = ['price' => 'decimal:2', 'active' => 'boolean', 'catalog_available' => 'boolean', 'catalog_synced_at' => 'datetime'];
    public function hasPrice(): bool
    {
        return $this->active && $this->price !== null && (float) $this->price > 0
            && $this->currency === 'EUR' && (! $this->catalog_service_id || $this->catalog_available);
    }
}
