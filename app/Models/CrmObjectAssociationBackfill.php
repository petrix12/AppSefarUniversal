<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CrmObjectAssociationBackfill extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'association_labels' => 'array',
            'last_page_at' => 'datetime',
        ];
    }
}
