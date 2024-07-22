<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use App\Models\Agcliente;
use App\Models\User;
use App\Exports\TreeExport;
use Maatwebsite\Excel\Facades\Excel;

class GedcomController extends Controller
{
    public function getExcelCliente(Request $request){
        $arrayGeneraciones = [
            "Cliente",
            "Padres",
            "Abuelos",
            "Bisabuelos",
            "Tatarabuelos",
            "Trastatarabuelos",
            "Cuartabuelos",
            "Quintabuelos",
            "Sextabuelos",
            "Septabuelos",
            "Octabuelos",
            "Nonabuelos",
            "Decabuelos"
        ];

        // Obtener los datos del cliente
        $datacliente = json_decode(json_encode(Agcliente::where("id", $request->id)->select("id","idPadreNew","idMadreNew","Nombres","Apellidos","NPasaporte","PaisPasaporte","NDocIdent","PaisDocIdent","Sexo","AnhoNac","MesNac","DiaNac","LugarNac","PaisNac","AnhoBtzo","MesBtzo","DiaBtzo","LugarBtzo","PaisBtzo","AnhoMatr" ,"MesMatr","DiaMatr","LugarMatr","PaisMatr","AnhoDef","MesDef","DiaDef","LugarDef","PaisDef","Observaciones","created_at")->first()), true);
        $people = [];

        // Función recursiva para asignar generaciones
        function getGenerations($person, $generationIndex, &$people, $arrayGeneraciones) {
            if ($generationIndex >= count($arrayGeneraciones)) {
                return;
            }

            // Agregar la persona a la lista con su generación
            $person['generacion'] = $arrayGeneraciones[$generationIndex];
            $people[] = $person;

            // Buscar padres y asociar la siguiente generación
            if (!empty($person['idPadreNew'])) {
                $padre = Agcliente::where('id', $person['idPadreNew'])->select("id","idPadreNew","idMadreNew","Nombres","Apellidos","NPasaporte","PaisPasaporte","NDocIdent","PaisDocIdent","Sexo","AnhoNac","MesNac","DiaNac","LugarNac","PaisNac","AnhoBtzo","MesBtzo","DiaBtzo","LugarBtzo","PaisBtzo","AnhoMatr" ,"MesMatr","DiaMatr","LugarMatr","PaisMatr","AnhoDef","MesDef","DiaDef","LugarDef","PaisDef","Observaciones","created_at")->first();
                if ($padre) {
                    getGenerations($padre->toArray(), $generationIndex + 1, $people, $arrayGeneraciones);
                }
            }

            if (!empty($person['idMadreNew'])) {
                $madre = Agcliente::where('id', $person['idMadreNew'])->select("id","idPadreNew","idMadreNew","Nombres","Apellidos","NPasaporte","PaisPasaporte","NDocIdent","PaisDocIdent","Sexo","AnhoNac","MesNac","DiaNac","LugarNac","PaisNac","AnhoBtzo","MesBtzo","DiaBtzo","LugarBtzo","PaisBtzo","AnhoMatr" ,"MesMatr","DiaMatr","LugarMatr","PaisMatr","AnhoDef","MesDef","DiaDef","LugarDef","PaisDef","Observaciones","created_at")->first();
                if ($madre) {
                    getGenerations($madre->toArray(), $generationIndex + 1, $people, $arrayGeneraciones);
                }
            }
        }

        // Iniciar la asignación de generaciones con el cliente
        getGenerations($datacliente, 0, $people, $arrayGeneraciones);

        return Excel::download(new TreeExport($people), 'Arbol Genealógico - '.$request->id.'.xlsx');
    }
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

        $arbolcliente = generateGedcom($people);

        // Crear la respuesta HTTP con el contenido del archivo adjunto
        $response = Response::make($arbolcliente);
        $response->header('Content-Disposition', 'attachment; filename= ARBOL CLIENTE '.$people[0]['IDCliente'].'.ged');

        // Descargar el archivo como respuesta a la solicitud HTTP
        return $response;
    }

    public function gedcomexport()
    {
        return view('gedcom.global');
    }

    public function getGedcomGlobal(Request $request)
    {
        $people = json_decode(json_encode(Agcliente::where('IDCliente','<>',0)->get()),true);
      
        $groupedPeople = [];

        $idcorrelativo = 1;

        foreach ($people as $key => $person) {
            $people[$key]["idCorrelativo"] = $idcorrelativo;
            $people[$key]["changedCorr"] = 'Nadie';
            $idcorrelativo = $idcorrelativo + 1;
            if ($person['IDMadre']<1){
                $people[$key]['IDMadre']=null;
            }
            if ($person['IDPadre']<1){
                $people[$key]['IDPadre']=null;
            }

            if (!isset($groupedPeople[$person['IDCliente']])) {
                $groupedPeople[$person['IDCliente']] = [];
            }
            $groupedPeople[$person['IDCliente']][] = $people[$key];
        }

        foreach ($groupedPeople as $key => $people) {
            if (count($groupedPeople[$key])>2){
                if(!isset($groupedPeople[$key][0]['IDMadre'])){
                    if($groupedPeople[$key][1]["Sexo"]=="F"){
                        $groupedPeople[$key][0]['IDMadre']=$groupedPeople[$key][1]['IDPersona'];
                        $groupedPeople[$key][0]['IDPadre']=$groupedPeople[$key][2]['IDPersona'];
                    } else {
                        $groupedPeople[$key][0]['IDMadre']=$groupedPeople[$key][2]['IDPersona'];
                        $groupedPeople[$key][0]['IDPadre']=$groupedPeople[$key][1]['IDPersona'];
                    }
                }
            }
        }

        foreach ($groupedPeople as $key => $people) {
            
            foreach ($people as $key2 => $persona) {
                
                $changedcor = 'Nadie';

                foreach ($people as $key3 => $familiar) {

                    if ($familiar['IDPersona'] === $persona['IDPadre']) {
                        $changedcor = 'Padre';
                        $groupedPeople[$key][$key2]['IDPadre'] = $familiar['idCorrelativo'];
                        $groupedPeople[$key][$key2]["changedCorr"] = $changedcor;
                    }

                    if ($familiar['IDPersona'] === $persona['IDMadre']) {
                        $groupedPeople[$key][$key2]['IDMadre'] = $familiar['idCorrelativo'];
                        if ($changedcor=="Padre"){
                            $groupedPeople[$key][$key2]["changedCorr"] = "Padre y Madre";
                        } else if ($changedcor=="Nadie"){
                            $groupedPeople[$key][$key2]["changedCorr"] = "Madre";
                        }
                    }
                }

            }
            
        }

        foreach ($groupedPeople as $key => $people) {
            
            foreach ($people as $key2 => $persona) {
                
                if ($groupedPeople[$key][$key2]["changedCorr"]=="Madre"){
                    $groupedPeople[$key][$key2]["IDPadre"] = null;
                }

                if ($groupedPeople[$key][$key2]["changedCorr"]=="Padre"){
                    $groupedPeople[$key][$key2]["IDMadre"] = null;
                }

                if ($groupedPeople[$key][$key2]["changedCorr"]=="Nadie"){
                    $groupedPeople[$key][$key2]["IDMadre"] = null;
                    $groupedPeople[$key][$key2]["IDPadre"] = null;
                }

            }
            
        }

        /* 

        $primer_elemento = reset($groupedPeople);
        
        echo("<pre>");
        print_r($primer_elemento);
        echo("</pre>");
        return false;

        */

        $arbolcliente = massiveGed($groupedPeople);

        // Crear la respuesta HTTP con el contenido del archivo adjunto
        $response = Response::make($arbolcliente);
        $response->header('Content-Disposition', 'attachment; filename= AppSefarGlobal.ged');

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

        $nacimiento = "1 BIRT\n";

        if (!is_null($persona['AnhoNac'])){
            if (is_null($persona['MesNac'])){
                $nacimiento .= "2 DATE ".$persona['AnhoNac']."\n";
            } else {
                if (is_null($persona['DiaNac'])){
                    $fecha_original = $persona['AnhoNac']."-".$persona['MesNac']."-01";
                    $fecha_convertida = date("M Y", strtotime($fecha_original));
                    $nacimiento .= "2 DATE ".$fecha_convertida."\n";
                } else {
                    $fecha_original = $persona['AnhoNac']."-".$persona['MesNac']."-".$persona['DiaNac'];
                    $fecha_convertida = date("j M Y", strtotime($fecha_original));
                    $nacimiento .= "2 DATE ".$fecha_convertida."\n";
                }
            }
        }

        $cadena_con_espacios = $persona['PaisNac'];
        $persona['PaisNac'] = preg_replace('/\s+/', ' ', trim($cadena_con_espacios));

        if (strlen($persona['PaisNac']) === 0) {
            $persona['PaisNac'] = null;
        }

        if (!is_null($persona['PaisNac']) && $persona['PaisNac']!=""){
            $nacimiento .= "2 PLAC ";

            $cadena_con_espacios = $persona['LugarNac'];
            $persona['LugarNac'] = preg_replace('/\s+/', ' ', trim($cadena_con_espacios));

            if (strlen($persona['LugarNac']) === 0) {
                $persona['LugarNac'] = null;
            }

            if (!is_null($persona['LugarNac'])){
                $nacimiento .= $persona['LugarNac'].", ";
            }
            $nacimiento .= $persona['PaisNac'];
            $nacimiento .= "\n";
        }

        if ($nacimiento != "1 BIRT\n"){
            $gedcom .= $nacimiento;
        }

        $defuncion = "1 DEAT\n";

        if (!is_null($persona['AnhoDef'])){
            if (is_null($persona['MesDef'])){
                $defuncion .= "2 DATE ".$persona['AnhoDef']."\n";
            } else {
                if (is_null($persona['DiaDef'])){
                    $fecha_original = $persona['AnhoDef']."-".$persona['MesDef']."-01";
                    $fecha_convertida = date("M Y", strtotime($fecha_original));
                    $defuncion .= "2 DATE ".$fecha_convertida."\n";
                } else {
                    $fecha_original = $persona['AnhoDef']."-".$persona['MesDef']."-".$persona['DiaDef'];
                    $fecha_convertida = date("j M Y", strtotime($fecha_original));
                    $defuncion .= "2 DATE ".$fecha_convertida."\n";
                }
            }
        }

        $cadena_con_espacios = $persona['PaisDef'];
        $persona['PaisDef'] = preg_replace('/\s+/', ' ', trim($cadena_con_espacios));

        if (strlen($persona['PaisDef']) === 0) {
            $persona['PaisDef'] = null;
        }

        if (!is_null($persona['PaisDef']) && $persona['PaisDef']!=""){
            $defuncion .= "2 PLAC ";

            $cadena_con_espacios = $persona['LugarDef'];
            $persona['LugarDef'] = preg_replace('/\s+/', ' ', trim($cadena_con_espacios));

            if (strlen($persona['LugarDef']) === 0) {
                $persona['LugarDef'] = null;
            }

            if (!is_null($persona['LugarDef'])){
                $defuncion .= $persona['LugarDef'].", ";
            }
            $defuncion .= $persona['PaisDef'];
            $defuncion .= "\n";
        }

        if ($defuncion != "1 DEAT\n"){
            $gedcom .= $defuncion;
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

        $matrimonio = "1 MARR\n";

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
                    $matrimonio .= "2 DATE ".$infomadre['AnhoMatr']."\n";
                } else {
                    if (is_null($infomadre['DiaMatr'])){
                        $fecha_original = $infomadre['AnhoMatr']."-".$infomadre['MesMatr']."-01";
                        $fecha_convertida = date("M Y", strtotime($fecha_original));
                        $matrimonio .= "2 DATE ".$fecha_convertida."\n";
                    } else {
                        $fecha_original = $infomadre['AnhoMatr']."-".$infomadre['MesMatr']."-".$infomadre['DiaMatr'];
                        $fecha_convertida = date("j M Y", strtotime($fecha_original));
                        $matrimonio .= "2 DATE ".$fecha_convertida."\n";
                    }
                }
            }

            $cadena_con_espacios = $infomadre['PaisMatr'];
            $infomadre['PaisMatr'] = preg_replace('/\s+/', ' ', trim($cadena_con_espacios));

            if (strlen($infomadre['PaisMatr']) === 0) {
                $infomadre['PaisMatr'] = null;
            }

            if (!is_null($infomadre['PaisMatr']) && $infomadre['PaisMatr']!=""){
                $matrimonio .= "2 PLAC ";

                $cadena_con_espacios = $infomadre['LugarMatr'];
                $infomadre['LugarMatr'] = preg_replace('/\s+/', ' ', trim($cadena_con_espacios));

                if (strlen($infomadre['LugarMatr']) === 0) {
                    $infomadre['LugarMatr'] = null;
                }

                if (!is_null($infomadre['LugarMatr'])){
                    $matrimonio .= $infomadre['LugarMatr'].", ";
                }
                $matrimonio .= $infomadre['PaisMatr'];
                $matrimonio .= "\n";
            }
        } else {
            if (!is_null($infopadre)) {
                if(!is_null($infopadre["AnhoMatr"])){
                    if (is_null($infopadre['MesMatr'])){
                        $matrimonio .= "2 DATE ".$infopadre['AnhoMatr']."\n";
                    } else {
                        if (is_null($infopadre['DiaMatr'])){
                            $fecha_original = $infopadre['AnhoMatr']."-".$infopadre['MesMatr']."-01";
                            $fecha_convertida = date("M Y", strtotime($fecha_original));
                            $matrimonio .= "2 DATE ".$fecha_convertida."\n";
                        } else {
                            $fecha_original = $infopadre['AnhoMatr']."-".$infopadre['MesMatr']."-".$infopadre['DiaMatr'];
                            $fecha_convertida = date("j M Y", strtotime($fecha_original));
                            $matrimonio .= "2 DATE ".$fecha_convertida."\n";
                        }
                    }
                }

                $cadena_con_espacios = $infopadre['PaisMatr'];
                $infopadre['PaisMatr'] = preg_replace('/\s+/', ' ', trim($cadena_con_espacios));

                if (strlen($infopadre['PaisMatr']) === 0) {
                    $infopadre['PaisMatr'] = null;
                }

                if (!is_null($infopadre['PaisMatr']) && $infopadre['PaisMatr']!=""){
                    $matrimonio .= "2 PLAC ";

                    $cadena_con_espacios = $infopadre['LugarMatr'];
                    $infopadre['LugarMatr'] = preg_replace('/\s+/', ' ', trim($cadena_con_espacios));

                    if (strlen($infopadre['LugarMatr']) === 0) {
                        $infopadre['LugarMatr'] = null;
                    }

                    if (!is_null($infopadre['LugarMatr'])){
                        $matrimonio .= $infopadre['LugarMatr'].", ";
                    }
                    $matrimonio .= $infopadre['PaisMatr'];
                    $matrimonio .= "\n";
                }
            }
        }

        if ($matrimonio != "1 MARR\n"){
            $gedcom .= $matrimonio;
        }
    }

    $gedcom .= "0 TRLR\n";

    return $gedcom;
}

function massiveGed($familia)
{
    $gedcom = "0 HEAD\n";
    $gedcom .= "1 SOUR AppSefarUniversal\n";
    $gedcom .= "2 NAME APP Sefar Universal\n";
    $gedcom .= "2 CORP app.sefaruniversal.com\n";
    $gedcom .= "2 VERS 1.0\n";
    $gedcom .= "1 CHAR UTF-8\n";
    $gedcom .= "1 GEDC\n2 VERS 5.5\n";
    $gedcom .= "2 FORM LINEAGE-LINKED\n";

    foreach ($familia as $fkey=>$personas) {

        foreach ($personas as $pkey=>$persona) {
            if (!isset($persona["idCorrelativo"])) {
                print_r($persona);
                echo "idCorrelativo no está definido para una persona.";
                return false;
            }
            $gedcom .= "0 @I".$persona["idCorrelativo"]."@ INDI\n";
            $gedcom .= "1 NAME ".$persona['Nombres']." /".$persona['Apellidos']."/\n";
            $gedcom .= "2 SURN ".$persona['Apellidos']."\n";
            $gedcom .= "2 GIVN ".$persona['Nombres']."\n";

            if (!is_null($persona['Sexo'])){
                $gedcom .= "1 SEX ".$persona['Sexo']."\n";
            }

            $nacimiento = "1 BIRT\n";

            if (!is_null($persona['AnhoNac'])){
                if (is_null($persona['MesNac'])){
                    $nacimiento .= "2 DATE ".$persona['AnhoNac']."\n";
                } else {
                    if (is_null($persona['DiaNac'])){
                        $fecha_original = $persona['AnhoNac']."-".$persona['MesNac']."-01";
                        $fecha_convertida = date("M Y", strtotime($fecha_original));
                        $nacimiento .= "2 DATE ".$fecha_convertida."\n";
                    } else {
                        $fecha_original = $persona['AnhoNac']."-".$persona['MesNac']."-".$persona['DiaNac'];
                        $fecha_convertida = date("j M Y", strtotime($fecha_original));
                        $nacimiento .= "2 DATE ".$fecha_convertida."\n";
                    }
                }
            }

            $cadena_con_espacios = $persona['PaisNac'];
            $persona['PaisNac'] = preg_replace('/\s+/', ' ', trim($cadena_con_espacios));

            if (strlen($persona['PaisNac']) === 0) {
                $persona['PaisNac'] = null;
            }

            if (!is_null($persona['PaisNac']) && $persona['PaisNac']!=""){
                $nacimiento .= "2 PLAC ";

                $cadena_con_espacios = $persona['LugarNac'];
                $persona['LugarNac'] = preg_replace('/\s+/', ' ', trim($cadena_con_espacios));

                if (strlen($persona['LugarNac']) === 0) {
                    $persona['LugarNac'] = null;
                }

                if (!is_null($persona['LugarNac'])){
                    $nacimiento .= $persona['LugarNac'].", ";
                }
                $nacimiento .= $persona['PaisNac'];
                $nacimiento .= "\n";
            }

            if ($nacimiento != "1 BIRT\n"){
                $gedcom .= $nacimiento;
            }

            $defuncion = "1 DEAT\n";

            if (!is_null($persona['AnhoDef'])){
                if (is_null($persona['MesDef'])){
                    $defuncion .= "2 DATE ".$persona['AnhoDef']."\n";
                } else {
                    if (is_null($persona['DiaDef'])){
                        $fecha_original = $persona['AnhoDef']."-".$persona['MesDef']."-01";
                        $fecha_convertida = date("M Y", strtotime($fecha_original));
                        $defuncion .= "2 DATE ".$fecha_convertida."\n";
                    } else {
                        $fecha_original = $persona['AnhoDef']."-".$persona['MesDef']."-".$persona['DiaDef'];
                        $fecha_convertida = date("j M Y", strtotime($fecha_original));
                        $defuncion .= "2 DATE ".$fecha_convertida."\n";
                    }
                }
            }

            $cadena_con_espacios = $persona['PaisDef'];
            $persona['PaisDef'] = preg_replace('/\s+/', ' ', trim($cadena_con_espacios));

            if (strlen($persona['PaisDef']) === 0) {
                $persona['PaisDef'] = null;
            }

            if (!is_null($persona['PaisDef']) && $persona['PaisDef']!=""){
                $defuncion .= "2 PLAC ";

                $cadena_con_espacios = $persona['LugarDef'];
                $persona['LugarDef'] = preg_replace('/\s+/', ' ', trim($cadena_con_espacios));

                if (strlen($persona['LugarDef']) === 0) {
                    $persona['LugarDef'] = null;
                }

                if (!is_null($persona['LugarDef'])){
                    $defuncion .= $persona['LugarDef'].", ";
                }
                $defuncion .= $persona['PaisDef'];
                $defuncion .= "\n";
            }

            if ($defuncion != "1 DEAT\n"){
                $gedcom .= $defuncion;
            }

            $gedcom .= "1 FAMC @F".$persona["idCorrelativo"]."@\n";
            
        }
    }

    foreach ($familia as $fkey=>$personas) {

        // Escribir etiquetas GEDCOM para las relaciones entre familias
        foreach ($personas as $pkey=>$persona) {
            $gedcom .= "0 @F".$persona['idCorrelativo']."@ FAM\n";

            $infopadre = null;
            $infomadre = null;

            if (!is_null($persona['IDPadre'])){
                $gedcom .= "1 HUSB @I".$persona['IDPadre']."@\n";
            }
                
            if (!is_null($persona['IDMadre'])){
                $gedcom .= "1 WIFE @I".$persona['IDMadre']."@\n";
            }

            $gedcom .= "1 CHIL @I".$persona['idCorrelativo']."@\n";

            $matrimonio = "1 MARR\n";

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
                        $matrimonio .= "2 DATE ".$infomadre['AnhoMatr']."\n";
                    } else {
                        if (is_null($infomadre['DiaMatr'])){
                            $fecha_original = $infomadre['AnhoMatr']."-".$infomadre['MesMatr']."-01";
                            $fecha_convertida = date("M Y", strtotime($fecha_original));
                            $matrimonio .= "2 DATE ".$fecha_convertida."\n";
                        } else {
                            $fecha_original = $infomadre['AnhoMatr']."-".$infomadre['MesMatr']."-".$infomadre['DiaMatr'];
                            $fecha_convertida = date("j M Y", strtotime($fecha_original));
                            $matrimonio .= "2 DATE ".$fecha_convertida."\n";
                        }
                    }
                }

                $cadena_con_espacios = $infomadre['PaisMatr'];
                $infomadre['PaisMatr'] = preg_replace('/\s+/', ' ', trim($cadena_con_espacios));

                if (strlen($infomadre['PaisMatr']) === 0) {
                    $infomadre['PaisMatr'] = null;
                }

                if (!is_null($infomadre['PaisMatr']) && $infomadre['PaisMatr']!=""){
                    $matrimonio .= "2 PLAC ";

                    $cadena_con_espacios = $infomadre['LugarMatr'];
                    $infomadre['LugarMatr'] = preg_replace('/\s+/', ' ', trim($cadena_con_espacios));

                    if (strlen($infomadre['LugarMatr']) === 0) {
                        $infomadre['LugarMatr'] = null;
                    }

                    if (!is_null($infomadre['LugarMatr'])){
                        $matrimonio .= $infomadre['LugarMatr'].", ";
                    }
                    $matrimonio .= $infomadre['PaisMatr'];
                    $matrimonio .= "\n";
                }
            } else {
                if (!is_null($infopadre)) {
                    if(!is_null($infopadre["AnhoMatr"])){
                        if (is_null($infopadre['MesMatr'])){
                            $matrimonio .= "2 DATE ".$infopadre['AnhoMatr']."\n";
                        } else {
                            if (is_null($infopadre['DiaMatr'])){
                                $fecha_original = $infopadre['AnhoMatr']."-".$infopadre['MesMatr']."-01";
                                $fecha_convertida = date("M Y", strtotime($fecha_original));
                                $matrimonio .= "2 DATE ".$fecha_convertida."\n";
                            } else {
                                $fecha_original = $infopadre['AnhoMatr']."-".$infopadre['MesMatr']."-".$infopadre['DiaMatr'];
                                $fecha_convertida = date("j M Y", strtotime($fecha_original));
                                $matrimonio .= "2 DATE ".$fecha_convertida."\n";
                            }
                        }
                    }

                    $cadena_con_espacios = $infopadre['PaisMatr'];
                    $infopadre['PaisMatr'] = preg_replace('/\s+/', ' ', trim($cadena_con_espacios));

                    if (strlen($infopadre['PaisMatr']) === 0) {
                        $infopadre['PaisMatr'] = null;
                    }

                    if (!is_null($infopadre['PaisMatr']) && $infopadre['PaisMatr']!=""){
                        $matrimonio .= "2 PLAC ";

                        $cadena_con_espacios = $infopadre['LugarMatr'];
                        $infopadre['LugarMatr'] = preg_replace('/\s+/', ' ', trim($cadena_con_espacios));

                        if (strlen($infopadre['LugarMatr']) === 0) {
                            $infopadre['LugarMatr'] = null;
                        }

                        if (!is_null($infopadre['LugarMatr'])){
                            $matrimonio .= $infopadre['LugarMatr'].", ";
                        }
                        $matrimonio .= $infopadre['PaisMatr'];
                        $matrimonio .= "\n";
                    }
                }
            }

            if ($matrimonio != "1 MARR\n"){
                $gedcom .= $matrimonio;
            }
        }

    }

    $gedcom .= "0 TRLR\n";

    return $gedcom;
}
