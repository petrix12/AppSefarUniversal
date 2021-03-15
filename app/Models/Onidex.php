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

    // nombre1
    public function scopeY1Nombres($query, $p_nombres, $bExacto, $bOrden){
        if ($p_nombres){
            if($bExacto){
                if($bOrden){
                    return $query->where('nombre1','like',$p_nombres)->orWhere('nombre2','like',$p_nombres);
                } else {
                    return $query->where('nombre1','like',$p_nombres);
                }
            }else{
                if($bOrden){
                    return $query->where('nombre1','like','%'.$p_nombres.'%')->orWhere('nombre2','like','%'.$p_nombres.'%');
                } else {
                    return $query->where('nombre1','like','%'.$p_nombres.'%');
                }               
                
            }
        }
    } 

    // nombre2
    public function scopeY2Nombres($query, $s_nombres, $bExacto){
        if ($s_nombres){
            if($bExacto){
                return $query->where('nombre2','like',$s_nombres);
            }else{
                return $query->where('nombre2','like','%'.$s_nombres.'%');
            }
        }
    }

    // apellido1
    public function scopeY1Apellidos($query, $p_apellidos, $bExacto, $bOrden){
        if ($p_apellidos){
            if($bExacto){
                if($bOrden){
                    return $query->where('apellido1','like',$p_apellidos)->orWhere('apellido2','like',$p_apellidos);
                } else {
                    return $query->where('apellido1','like',$p_apellidos);
                }                
            }else{
                if($bOrden){
                    return $query->where('apellido1','like','%'.$p_apellidos.'%')->orWhere('apellido2','like','%'.$p_apellidos.'%');
                } else {
                    return $query->where('apellido1','like','%'.$p_apellidos.'%');
                }  
            }
        }
    } 

    // apellido2
    public function scopeY2Apellidos($query, $s_apellidos, $bExacto){
        if ($s_apellidos){
            if($bExacto){
                return $query->where('apellido2','like',$s_apellidos);
            }else{
                return $query->where('apellido2','like','%'.$s_apellidos.'%');
            }
        }
    } 

    // cedula
    public function scopeYCedulas($query, $cedulas, $bExacto){
        if ($cedulas){
            if($bExacto){
                return $query->where('cedula','=',$cedulas);
            }else{
                return $query->where('cedula','like','%'.$cedulas.'%');
            }
        }
    }
    
    // nacion
    public function scopeYNaciones($query, $naciones){
        if ($naciones){
            switch ($naciones) {
                case 'Argentina': return $query->where('nacion','like','AGT'); break;
                case 'Alemania': return $query->where('nacion','like','ALM'); break;
                case 'Australia': return $query->where('nacion','like','AUS'); break;
                case 'Brasil': return $query->where('nacion','like','BRA'); break;
                case 'Curazao': return $query->where('nacion','like','CCL'); break;
                case 'Chile': return $query->where('nacion','like','CHI'); break;
                case 'China': return $query->where('nacion','like','CHN'); break;
                case 'Colombia': return $query->where('nacion','like','CLB'); break;
                case 'Costa Rica': return $query->where('nacion','like','CTR'); break;
                case 'Cuba': return $query->where('nacion','like','CUB'); break;
                case 'Ecuador': return $query->where('nacion','like','ECD'); break;
                case 'El Salvador': return $query->where('nacion','like','ELS'); break;
                case 'España': return $query->where('nacion','like','EP#')->orWhere('nacion','like','EPÐ'); break;
                case 'Estados Unidos (USA)': return $query->where('nacion','like','EUA')->orWhere('nacion','like','USA'); break;
                case 'Francia': return $query->where('nacion','like','FRA'); break;
                case 'Guinea Bissau': return $query->where('nacion','like','GNB'); break;
                case 'Groenlandia': return $query->where('nacion','like','GRE'); break;
                case 'Guyana': return $query->where('nacion','like','GYN'); break;
                case 'Haití': return $query->where('nacion','like','HAI'); break;
                case 'Holanda': return $query->where('nacion','like','HLD'); break;
                case 'Italia': return $query->where('nacion','like','ITL'); break;
                case 'Jordania': return $query->where('nacion','like','JDN'); break;
                case 'Jamaica': return $query->where('nacion','like','JMC'); break;
                case 'Líbano': return $query->where('nacion','like','LBN'); break;
                case 'Lituania': return $query->where('nacion','like','LTN'); break;
                case 'México': return $query->where('nacion','like','MXC'); break;
                case 'Nicaragua': return $query->where('nacion','like','NCR'); break;
                case 'NULL': return $query->where('nacion','like','NULL'); break;
                case 'Perú': return $query->where('nacion','like','PER'); break;
                case 'Polonia': return $query->where('nacion','like','PLN'); break;
                case 'Portugal': return $query->where('nacion','like','PTG'); break;
                case 'Puerto Rico': return $query->where('nacion','like','PTR'); break;
                case 'Rumanía': return $query->where('nacion','like','RMN'); break;
                case 'República Dominicana': return $query->where('nacion','like','RPD'); break;
                case 'Rusia': return $query->where('nacion','like','RUS'); break;
                case 'Sudáfrica': return $query->where('nacion','like','SDF'); break;
                case 'Siria': return $query->where('nacion','like','SIR'); break;
                case 'Suecia': return $query->where('nacion','like','SUE'); break;
                case 'Suiza': return $query->where('nacion','like','SUI'); break;
                case 'Trinidad y Tobago': return $query->where('nacion','like','TNT'); break;
                case 'Uruguay': return $query->where('nacion','like','URG'); break;
                case 'Venezuela': return $query->where('nacion','like','VNZ'); break;
                case 'Yugoslavia': return $query->where('nacion','like','YGL'); break;

                //default: return $query->orWhere('nacion','like','0'); break;             
            }
            //return $query->orWhere('fec_nac','like','%'.$nac.'%');
        }
    }

    // fec_nac
    public function scopeYFechas($query, $fechas, $bAnho, $bMes, $bDia, $bRango, $FechaIni, $FechaFin){
        if ($fechas){
            $fechaComoEntero = strtotime($fechas);
            $anho = $bAnho ? date("Y", $fechaComoEntero) : '%';
            $mes = $bMes ? date("m", $fechaComoEntero) : '%';
            $dia = $bDia ? date("d", $fechaComoEntero) : '%';
            $NFecha = $anho.'-'.$mes.'-'.$dia;
            //return $query->orWhere('fec_nac','=',$fechas);
            return $query->Where('fec_nac','like','%'.$NFecha.'%');
        }
        if($bRango){
            return $query->Where('fec_nac','>=',$FechaIni)
                        ->Where('fec_nac','<=',$FechaFin);
        }
    }
}
