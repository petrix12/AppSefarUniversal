<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MondayFieldMapping extends Model
{
    use HasFactory;
    protected $table = 'monday_field_mappings';

    protected $guarded = [];
}
