<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use App\Models\Agcliente;
use App\Models\User;

class GedcomController extends Controller
{
    public function getGedcomCliente(Request $request)
    {
        $datacliente = json_decode(json_encode(Agcliente::where("id", $request->id)->get()),true);
        $people = json_decode(json_encode(Agcliente::where("IDCliente",$datacliente[0]["IDCliente"])->get()),true);


        //Asignar ids de padres al nodo 0 (en caso de no tenerlo)
        if (count($people)>2){
            if(!isset($people[0]['IDMadre'])){
                if($people[1]["Sexo"]=="F"){
                    $people[0]['IDMadre']=$people[1]['IDPersona'];
                    $people[0]['IDPadre']=$people[2]['IDPersona'];
                } else {
                    $people[0]['IDMadre']=$people[2]['IDPersona'];
                    $people[0]['IDPadre']=$people[1]['IDPersona'];
                }
            }
        }

        //Eliminar basura de los ids de los padres        
        foreach ($people as $key => $person) {
            if ($person['IDMadre']<1){
                $people[$key]['IDMadre']=null;
            }
            if ($person['IDPadre']<1){
                $people[$key]['IDPadre']=null;
            }
        }

        /*

        echo '<pre>';
        print_r($people);
        echo '</pre>';

        return false;
        
        */

        $arbolcliente = generateGedcom($people);

        // Crear la respuesta HTTP con el contenido del archivo adjunto
        $response = Response::make($arbolcliente);
        $response->header('Content-Disposition', 'attachment; filename= ARBOL CLIENTE '.$people[0]['IDCliente'].'.ged');

        // Descargar el archivo como respuesta a la solicitud HTTP
        return $response;
    }
}

function generateGedcom($personas)
{
    $gedcom = "0 HEAD\n";
    $gedcom .= "1 SOUR AppSefarUniversal\n";
    $gedcom .= "2 NAME APP Sefar Universal\n";
    $gedcom .= "2 CORP app.sefaruniversal.com\n";
    $gedcom .= "2 VERS 1.0\n";
    $gedcom .= "1 CHAR UTF-8\n";
    $gedcom .= "1 GEDC\n2 VERS 5.5\n";
    $gedcom .= "2 FORM LINEAGE-LINKED\n";

    foreach ($personas as $persona) {
        $gedcom .= "0 @I".$persona['IDPersona']."@ INDI\n";
        $gedcom .= "1 NAME ".$persona['Nombres']." /".$persona['Apellidos']."/\n";
        $gedcom .= "2 SURN ".$persona['Apellidos']."\n";
        $gedcom .= "2 GIVN ".$persona['Nombres']."\n";

        if (!is_null($persona['Sexo'])){
            $gedcom .= "1 SEX ".$persona['Sexo']."\n";
        }

        if (!is_null($persona['AnhoNac'])){
            if (is_null($persona['MesNac'])){
                $gedcom .= "1 BIRT\n";
                $gedcom .= "2 DATE ".$persona['AnhoNac']."\n";
            } else {
                if (is_null($persona['DiaNac'])){
                    $gedcom .= "1 BIRT\n";
                    $fecha_original = $persona['AnhoNac']."-".$persona['MesNac']."-01";
                    $fecha_convertida = date("M Y", strtotime($fecha_original));
                    $gedcom .= "2 DATE ".$fecha_convertida."\n";
                } else {
                    $gedcom .= "1 BIRT\n";
                    $fecha_original = $persona['AnhoNac']."-".$persona['MesNac']."-".$persona['DiaNac'];
                    $fecha_convertida = date("j M Y", strtotime($fecha_original));
                    $gedcom .= "2 DATE ".$fecha_convertida."\n";
                }
            }
        }

        if (!is_null($persona['PaisNac'])){
            $gedcom .= "2 PLAC ";
            if (!is_null($persona['LugarNac'])){
                $gedcom .= $persona['LugarNac'].", ";
            }
            $gedcom .= $persona['PaisNac'];
            $gedcom .= "\n";
        }

        if (!is_null($persona['AnhoDef'])){
            if (is_null($persona['MesDef'])){
                $gedcom .= "1 DEAT\n";
                $gedcom .= "2 DATE ".$persona['AnhoDef']."\n";
            } else {
                if (is_null($persona['DiaDef'])){
                    $gedcom .= "1 DEAT\n";
                    $fecha_original = $persona['AnhoDef']."-".$persona['MesDef']."-01";
                    $fecha_convertida = date("M Y", strtotime($fecha_original));
                    $gedcom .= "2 DATE ".$fecha_convertida."\n";
                } else {
                    $gedcom .= "1 DEAT\n";
                    $fecha_original = $persona['AnhoDef']."-".$persona['MesDef']."-".$persona['DiaDef'];
                    $fecha_convertida = date("j M Y", strtotime($fecha_original));
                    $gedcom .= "2 DATE ".$fecha_convertida."\n";
                }
            }
        }

        if (!is_null($persona['PaisDef'])){
            $gedcom .= "2 PLAC ";
            if (!is_null($persona['LugarDef'])){
                $gedcom .= $persona['LugarDef'].", ";
            }
            $gedcom .= $persona['PaisDef'];
            $gedcom .= "\n";
        }

        $gedcom .= "1 FAMC @F".$persona['IDPersona']."@\n";
        
    }

    // Escribir etiquetas GEDCOM para las relaciones entre familias
    foreach ($personas as $persona) {
        $gedcom .= "0 @F".$persona['IDPersona']."@ FAM\n";

        $infopadre = null;
        $infomadre = null;

        if (!is_null($persona['IDPadre'])){
            $gedcom .= "1 HUSB @I".$persona['IDPadre']."@\n";
        }
            
        if (!is_null($persona['IDMadre'])){
            $gedcom .= "1 WIFE @I".$persona['IDMadre']."@\n";
        }

        $gedcom .= "1 CHIL @I".$persona['IDPersona']."@\n";

        foreach ($personas as $buscarmadre) {
            if ($persona['IDMadre']==$buscarmadre['IDPersona']){
                $infopadre = $infomadre;
                break;
            }
        }

        foreach ($personas as $buscarpadre) {
            if ($persona['IDPadre']==$buscarpadre['IDPersona']){
                $infomadre = $buscarpadre;
                break;
            }
        }

        if (!is_null($infomadre)) {
            if(!is_null($infomadre["AnhoMatr"])){
                if (is_null($infomadre['MesMatr'])){
                    $gedcom .= "1 MARR\n";
                    $gedcom .= "2 DATE ".$infomadre['AnhoMatr']."\n";
                } else {
                    if (is_null($infomadre['DiaMatr'])){
                        $gedcom .= "1 MARR\n";
                        $fecha_original = $infomadre['AnhoMatr']."-".$infomadre['MesMatr']."-01";
                        $fecha_convertida = date("M Y", strtotime($fecha_original));
                        $gedcom .= "2 DATE ".$fecha_convertida."\n";
                    } else {
                        $gedcom .= "1 MARR\n";
                        $fecha_original = $infomadre['AnhoMatr']."-".$infomadre['MesMatr']."-".$infomadre['DiaMatr'];
                        $fecha_convertida = date("j M Y", strtotime($fecha_original));
                        $gedcom .= "2 DATE ".$fecha_convertida."\n";
                    }
                }
            }
            if (!is_null($infomadre['PaisMatr'])){
                $gedcom .= "2 PLAC ";
                if (!is_null($infomadre['LugarMatr'])){
                    $gedcom .= $infomadre['LugarMatr'].", ";
                }
                $gedcom .= $infomadre['PaisMatr'];
                $gedcom .= "\n";
            }
        } else {
            if (!is_null($infopadre)) {
                if(!is_null($infopadre["AnhoMatr"])){
                    if (is_null($infopadre['MesMatr'])){
                        $gedcom .= "1 MARR\n";
                        $gedcom .= "2 DATE ".$infopadre['AnhoMatr']."\n";
                    } else {
                        if (is_null($infopadre['DiaMatr'])){
                            $gedcom .= "1 MARR\n";
                            $fecha_original = $infopadre['AnhoMatr']."-".$infopadre['MesMatr']."-01";
                            $fecha_convertida = date("M Y", strtotime($fecha_original));
                            $gedcom .= "2 DATE ".$fecha_convertida."\n";
                        } else {
                            $gedcom .= "1 MARR\n";
                            $fecha_original = $infopadre['AnhoMatr']."-".$infopadre['MesMatr']."-".$infopadre['DiaMatr'];
                            $fecha_convertida = date("j M Y", strtotime($fecha_original));
                            $gedcom .= "2 DATE ".$fecha_convertida."\n";
                        }
                    }
                }
                if (!is_null($infopadre['PaisMatr'])){
                    $gedcom .= "2 PLAC ";
                    if (!is_null($infopadre['LugarMatr'])){
                        $gedcom .= $infopadre['LugarMatr'].", ";
                    }
                    $gedcom .= $infopadre['PaisMatr'];
                    $gedcom .= "\n";
                }
            }
        }
    }

    $gedcom .= "0 TRLR\n";

    return $gedcom;
}