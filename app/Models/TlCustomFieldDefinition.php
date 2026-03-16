<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TlCustomFieldDefinition extends Model
{
    protected $table      = 'tl_custom_field_definitions';
    protected $primaryKey = 'id';
    public    $incrementing = false;
    protected $keyType    = 'string';

    protected $fillable = [
        'id',
        'label',
        'type',
        'context',
        'required',
        'configuration',
        'raw_data',
    ];

    protected $casts = [
        'required'      => 'boolean',
        'configuration' => 'array',
        'raw_data'      => 'array',
    ];
}
