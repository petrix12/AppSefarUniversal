<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MondayData extends Model
{
    use HasFactory;

    protected $table = 'monday_data';

    protected $guarded = [];
}
