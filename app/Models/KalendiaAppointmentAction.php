<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class KalendiaAppointmentAction extends Model
{
    protected $guarded = [];
    protected $hidden = ['payload', 'lease'];
    protected $casts = ['payload' => 'encrypted:array', 'leased_until' => 'datetime'];
}
