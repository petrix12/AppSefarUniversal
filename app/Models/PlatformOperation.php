<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformOperation extends Model
{
    protected $guarded = [];
    protected $casts = ['payload'=>'encrypted:array', 'leased_until'=>'datetime'];
    protected $hidden = ['payload','lease'];
    public function actor() { return $this->belongsTo(User::class, 'actor_id'); }
    public function record() { return $this->belongsTo(PlatformRecord::class, 'record_id'); }
}
