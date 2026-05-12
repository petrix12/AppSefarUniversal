<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TlDocumentUserLink extends Model
{
    protected $table = 'tl_document_user_links';

    protected $fillable = [
        'tl_document_id',
        'user_id',
        'tl_contact_id',
        'entity_type',
        'entity_id',
        'matched_by',
        'status',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(TlDocument::class, 'tl_document_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(TlContact::class, 'tl_contact_id');
    }
}
