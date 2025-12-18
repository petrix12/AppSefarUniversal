<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MondayFormBuilder extends Model
{
    use HasFactory;

    protected $table = 'monday_form_builder';
    protected $guarded = [];

    protected $casts = [
        'settings' => 'array',
        'tag_ids'  => 'array',
    ];
}
