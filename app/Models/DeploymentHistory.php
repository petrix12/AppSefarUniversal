<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeploymentHistory extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'migrate_exit_code' => 'integer',
        'optimize_exit_code' => 'integer',
        'mail_sent' => 'boolean',
        'deployed_at' => 'datetime',
    ];
}
