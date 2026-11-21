<?php
namespace App\Services;
use Illuminate\Support\Facades\Validator;
class KalendiaTimezone
{
    public static function validate(mixed $zone): string
    {
        $zone = $zone === null || $zone === '' ? 'America/Bogota' : $zone;
        Validator::make(['timezone'=>$zone], ['timezone'=>['bail','required','string','timezone:all']])->validate();
        return $zone;
    }
    public static function preferred(): string
    {
        $zone = request()->hasSession() ? request()->session()->get('kalendia_timezone') : null;
        return is_string($zone) && in_array($zone, \DateTimeZone::listIdentifiers(\DateTimeZone::ALL_WITH_BC), true) ? $zone : 'America/Bogota';
    }
}
