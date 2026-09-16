<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentRequest extends Model
{
    protected $fillable = [
        'user_id', 'requested_by', 'document_name',
        'document_type', 'status', 'status_changed_at',
        'file_path', 'no_document_button_at'
    ];

    /* Relaciones */
    public function client(): BelongsTo      { return $this->belongsTo(User::class, 'user_id'); }
    public function admin():  BelongsTo      { return $this->belongsTo(User::class, 'requested_by'); }
}
