<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemsActivityReport extends Model
{
    protected $guarded = [];

    protected $casts = [
        'report_date' => 'date',
        'source_counts' => 'array',
    ];
}
