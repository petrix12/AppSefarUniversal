<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Onidex extends Model
{
    use HasFactory;

    /**
     * The database connection used by the model.
     *
     * @var string
     */
    protected $connection = 'onidex';

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'onidexes';

    public $incrementing = false;

    // nombre1
    public function scopeY1Nombres($query, $p_nombres, $bExacto, $bOrden)
    {
        if ($this->blankFilter($p_nombres)) {
            return $query;
        }

        $columns = $bOrden ? ['nombre1', 'nombre2'] : ['nombre1'];

        return $this->whereText($query, $columns, $p_nombres, $bExacto);
    }

    // nombre2
    public function scopeY2Nombres($query, $s_nombres, $bExacto)
    {
        if ($this->blankFilter($s_nombres)) {
            return $query;
        }

        return $this->whereText($query, ['nombre2'], $s_nombres, $bExacto);
    }

    // apellido1
    public function scopeY1Apellidos($query, $p_apellidos, $bExacto, $bOrden)
    {
        if ($this->blankFilter($p_apellidos)) {
            return $query;
        }

        $columns = $bOrden ? ['apellido1', 'apellido2'] : ['apellido1'];

        return $this->whereText($query, $columns, $p_apellidos, $bExacto);
    }

    // apellido2
    public function scopeY2Apellidos($query, $s_apellidos, $bExacto)
    {
        if ($this->blankFilter($s_apellidos)) {
            return $query;
        }

        return $this->whereText($query, ['apellido2'], $s_apellidos, $bExacto);
    }

    // cedula
    public function scopeYCedulas($query, $cedulas, $bExacto)
    {
        if ($this->blankFilter($cedulas)) {
            return $query;
        }

        $cedulas = trim((string) $cedulas);

        if ($bExacto) {
            return $query->where('cedula', '=', $cedulas);
        }

        return $query->where('cedula', 'like', $this->prefixLike($cedulas));
    }

    // nacion
    public function scopeYNaciones($query, $naciones)
    {
        if ($this->blankFilter($naciones)) {
            return $query;
        }

        $codes = [
            'Argentina' => ['AGT'],
            'Alemania' => ['ALM'],
            'Australia' => ['AUS'],
            'Brasil' => ['BRA'],
            'Curazao' => ['CCL'],
            'Chile' => ['CHI'],
            'China' => ['CHN'],
            'Colombia' => ['CLB'],
            'Costa Rica' => ['CTR'],
            'Cuba' => ['CUB'],
            'Ecuador' => ['ECD'],
            'El Salvador' => ['ELS'],
            'España' => ['EP#', 'EPÐ'],
            'Estados Unidos (USA)' => ['EUA', 'USA'],
            'Francia' => ['FRA'],
            'Guinea Bissau' => ['GNB'],
            'Groenlandia' => ['GRE'],
            'Guyana' => ['GYN'],
            'Haití' => ['HAI'],
            'Holanda' => ['HLD'],
            'Italia' => ['ITL'],
            'Jordania' => ['JDN'],
            'Jamaica' => ['JMC'],
            'Líbano' => ['LBN'],
            'Lituania' => ['LTN'],
            'México' => ['MXC'],
            'Nicaragua' => ['NCR'],
            'NULL' => ['NULL'],
            'Perú' => ['PER'],
            'Polonia' => ['PLN'],
            'Portugal' => ['PTG'],
            'Puerto Rico' => ['PTR'],
            'Rumanía' => ['RMN'],
            'República Dominicana' => ['RPD'],
            'Rusia' => ['RUS'],
            'Sudáfrica' => ['SDF'],
            'Siria' => ['SIR'],
            'Suecia' => ['SUE'],
            'Suiza' => ['SUI'],
            'Trinidad y Tobago' => ['TNT'],
            'Uruguay' => ['URG'],
            'Venezuela' => ['VNZ'],
            'Yugoslavia' => ['YGL'],
        ][trim((string) $naciones)] ?? null;

        if (! $codes) {
            return $query;
        }

        return count($codes) === 1
            ? $query->where('nacion', '=', $codes[0])
            : $query->whereIn('nacion', $codes);
    }

    // fec_nac
    public function scopeYFechas($query, $fechas, $bAnho, $bMes, $bDia, $bRango, $FechaIni, $FechaFin)
    {
        if (! $this->blankFilter($fechas)) {
            $fechaComoEntero = strtotime($fechas);

            if ($fechaComoEntero === false) {
                return $query;
            }

            $anho = date('Y', $fechaComoEntero);
            $mes = date('m', $fechaComoEntero);
            $dia = date('d', $fechaComoEntero);

            if ($bAnho && $bMes && $bDia) {
                return $query->where('fec_nac', '=', date('Y-m-d', $fechaComoEntero));
            }

            if ($bAnho && $bMes && ! $bDia) {
                $inicio = "{$anho}-{$mes}-01";
                $fin = date('Y-m-t', strtotime($inicio));

                return $query->whereBetween('fec_nac', [$inicio, $fin]);
            }

            if ($bAnho && ! $bMes && ! $bDia) {
                return $query->whereBetween('fec_nac', ["{$anho}-01-01", "{$anho}-12-31"]);
            }

            $fecha = ($bAnho ? $anho : '%').'-'.($bMes ? $mes : '%').'-'.($bDia ? $dia : '%');

            return $query->where('fec_nac', 'like', $fecha);
        }

        if ($bRango && ! $this->blankFilter($FechaIni) && ! $this->blankFilter($FechaFin)) {
            return $query->whereBetween('fec_nac', [$FechaIni, $FechaFin]);
        }

        return $query;
    }

    private function whereText($query, array $columns, string $value, $exact)
    {
        $exact = (bool) $exact;
        $operator = $exact ? '=' : 'like';
        $value = $exact ? trim($value) : $this->prefixLike($value);

        return $query->where(function ($query) use ($columns, $operator, $value) {
            foreach ($columns as $index => $column) {
                $method = $index === 0 ? 'where' : 'orWhere';
                $query->{$method}($column, $operator, $value);
            }
        });
    }

    private function prefixLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], trim($value)) . '%';
    }

    private function blankFilter($value): bool
    {
        return trim((string) $value) === '';
    }
}
