<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MondayServiceRegistration extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'attempts' => 'integer',
        'synced_at' => 'datetime',
    ];
}
