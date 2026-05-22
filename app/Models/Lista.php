<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lista extends Model
{
    protected $table = 'lists';
    protected $fillable = [
        'name',
        'description',
        'owner_id',
        'created_by',
        'include_in_task_pool',
        'disable_hubspot_reassignment',
    ];

    protected $casts = [
        'include_in_task_pool' => 'boolean',
        'disable_hubspot_reassignment' => 'boolean',
    ];

    public function users()
    {
        // parent = Lista -> pivot.fk = list_id, pivot.related = user_id
        return $this->belongsToMany(\App\Models\User::class, 'list_user', 'list_id', 'user_id')
            ->withPivot(['id', 'contacted', 'contacted_at', 'contact_note'])
            ->withTimestamps();
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
