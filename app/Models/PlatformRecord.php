<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformRecord extends Model
{
    protected $guarded = [];
    protected $casts = ['data'=>'encrypted:array', 'checked_at'=>'datetime'];
    protected $hidden = ['data'];
}
