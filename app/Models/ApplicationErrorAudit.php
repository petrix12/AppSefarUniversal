<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApplicationErrorAudit extends Model
{
    protected $fillable = [
        'reference',
        'environment',
        'user_id',
        'user_name',
        'user_email',
        'method',
        'url',
        'route_name',
        'ip_address',
        'user_agent',
        'request_headers',
        'request_data',
        'request_files',
        'status_code',
        'response_content_type',
        'response_body',
        'exception_class',
        'exception_message',
        'exception_file',
        'exception_line',
        'exception_trace',
        'email_recipient',
        'email_sent_at',
        'email_error',
        'occurred_at',
    ];

    protected $casts = [
        'request_headers' => 'array',
        'request_data' => 'array',
        'request_files' => 'array',
        'email_sent_at' => 'datetime',
        'occurred_at' => 'datetime',
    ];
}
