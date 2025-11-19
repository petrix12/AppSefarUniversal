<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Modelo para los números de teléfono a los que se enviarán reportes.
 * Permite múltiples registros.
 */
class ReportPhoneNumbers extends Model
{
    use HasFactory;

    // Nombre de la tabla
    protected $table = 'report_phone_numbers';

    // Campos que se pueden llenar masivamente
    protected $fillable = [
        'phone_number',
    ];
}
