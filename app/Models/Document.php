<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    protected $guarded = [];

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
