<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class BrightbeanMessage extends Model
{
    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $guarded = ['id', 'ai_reply', 'ai_replied_at'];

    protected $casts = [
        'body' => 'array',
        'contact' => 'array',
        'internal' => 'boolean',
        'source_created_at' => 'datetime',
        'source_updated_at' => 'datetime',
        'ai_replied_at' => 'datetime',
    ];

    public function setSourceCreatedAtAttribute($value): void
    {
        $this->attributes['source_created_at'] = Carbon::parse($value)
            ->timezone(config('app.timezone'))->format($this->getDateFormat());
    }

    public function setSourceUpdatedAtAttribute($value): void
    {
        $this->attributes['source_updated_at'] = Carbon::parse($value)
            ->timezone(config('app.timezone'))->format($this->getDateFormat());
    }
}
