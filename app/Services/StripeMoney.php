<?php
namespace App\Services;

use Illuminate\Validation\ValidationException;

class StripeMoney
{
    public static function decimals(string $currency): int
    {
        $currency=strtolower($currency);
        if (in_array($currency,['bif','clp','djf','gnf','jpy','kmf','krw','mga','pyg','rwf','vnd','vuv','xaf','xof','xpf'],true)) return 0;
        return in_array($currency,['bhd','jod','kwd','omr','tnd'],true) ? 3 : 2;
    }
    public static function minor(string $amount, string $currency): int
    {
        $places=self::decimals($currency);
        if (!preg_match('/^\d{1,10}(?:\.\d{1,3})?$/D',$amount)) throw ValidationException::withMessages(['amount'=>'Escribe un importe válido con punto decimal.']);
        [$whole,$fraction]=array_pad(explode('.',$amount,2),2,'');
        if (strlen($fraction)>$places || (in_array(strtolower($currency),['isk','ugx'],true) && trim($fraction,'0')!=='')) {
            throw ValidationException::withMessages(['amount'=>'Ese importe tiene más decimales de los permitidos por la moneda.']);
        }
        $minor=(int)$whole*10**$places+(int)str_pad($fraction,$places,'0');
        if ($minor<=0) throw ValidationException::withMessages(['amount'=>'El importe debe ser mayor que cero.']);
        return $minor;
    }
    public static function display(int $minor, string $currency): string
    {
        $places=self::decimals($currency); return number_format($minor/10**$places,$places,',','.').' '.strtoupper($currency);
    }
}
