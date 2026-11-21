<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class KalendiaConsultationOffer extends Model
{
    protected $guarded = [];
    protected $casts = ['price' => 'decimal:2', 'active' => 'boolean'];
    public function hasPrice(): bool { return $this->active && $this->price !== null && (float) $this->price > 0; }
}
