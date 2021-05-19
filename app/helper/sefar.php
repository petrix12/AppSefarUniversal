<?php
// Obtener persona

use App\Models\Agcliente;

function GetPersona($IDPersona){
    $Persona = null;
    switch ($IDPersona) {
        case 1: $Persona = 'Cliente'; break;
        case 2:	$Persona = 'Padre';	break;
        case 3:	$Persona = 'Madre';	break;
        case 4:	$Persona = 'Abuelo Paterno'; break;
        case 5:	$Persona = 'Abuela Paterna'; break;
        case 6:	$Persona = 'Abuelo Materno'; break;
        case 7:	$Persona = 'Abuela Materna'; break;
        case 8: $Persona = 'Bisabuelo Pat. Pat.'; break;
        case 9: $Persona = 'Bisabuela Pat. Pat.'; break;
        case 10: $Persona = 'Bisabuelo Pat. Mat.'; break;
        case 11: $Persona = 'Bisabuela Pat. Mat.'; break;
        case 12: $Persona = 'Bisabuelo Mat. Pat.'; break;				
        case 13: $Persona = 'Bisabuela Mat. Pat.'; break;	
        case 14: $Persona = 'Bisabuelo Mat. Mat.'; break;				
        case 15: $Persona = 'Bisabuela Mat. Mat.'; break;	
        case 16: $Persona = 'Tatarabuelo PPP'; break;	
        case 17: $Persona = 'Tatarabuela PPP'; break;
        case 18: $Persona = 'Tatarabuelo PPM'; break;	
        case 19: $Persona = 'Tatarabuela PPM'; break;	
        case 20: $Persona = 'Tatarabuelo PMP'; break;	
        case 21: $Persona = 'Tatarabuela PMP'; break;
        case 22: $Persona = 'Tatarabuelo PMM'; break;	
        case 23: $Persona = 'Tatarabuela PMM'; break;
        case 24: $Persona = 'Tatarabuelo MPP'; break;	
        case 25: $Persona = 'Tatarabuela MPP'; break;	
        case 26: $Persona = 'Tatarabuelo MPM'; break;	
        case 27: $Persona = 'Tatarabuela MPM'; break;	
        case 28: $Persona = 'Tatarabuelo MMP'; break;	
        case 29: $Persona = 'Tatarabuela MMP'; break;	
        case 30: $Persona = 'Tatarabuelo MMM'; break;	
        case 31: $Persona = 'Tatarabuela MMM'; break;				
    }
    return $Persona;
}

// Obtener persona
function GetPersonaInv($IDPersona)
{
    $Persona = '';
    switch ($IDPersona) {
        case 'Cliente': $Persona = 1; break;
        case 'Padre': $Persona = 2; break;
        case 'Madre': $Persona = 3; break;
        case 'Abuelo Paterno': $Persona = 4; break;
        case 'Abuela Paterna': $Persona = 5; break;
        case 'Abuelo Materno': $Persona = 6; break;
        case 'Abuela Materna': $Persona = 7; break;
        case 'Bisabuelo Pat. Pat.': $Persona = 8; break;
        case 'Bisabuela Pat. Pat.': $Persona = 9; break;
        case 'Bisabuelo Pat. Mat.': $Persona = 10; break;
        case 'Bisabuela Pat. Mat.': $Persona = 11; break;
        case 'Bisabuelo Mat. Pat.': $Persona = 12; break;
        case 'Bisabuela Mat. Pat.': $Persona = 13; break;
        case 'Bisabuelo Mat. Mat.': $Persona = 14; break;
        case 'Bisabuela Mat. Mat.': $Persona = 15; break;
        case 'Tatarabuelo PPP': $Persona = 16; break;
        case 'Tatarabuela PPP': $Persona = 17; break;
        case 'Tatarabuelo PPM': $Persona = 18; break;
        case 'Tatarabuela PPM': $Persona = 19; break;
        case 'Tatarabuelo PMP': $Persona = 20; break;
        case 'Tatarabuela PMP': $Persona = 21; break;
        case 'Tatarabuelo PMM': $Persona = 22; break;
        case 'Tatarabuela PMM': $Persona = 23; break;
        case 'Tatarabuelo MPP': $Persona = 24; break;
        case 'Tatarabuela MPP': $Persona = 25; break;
        case 'Tatarabuelo MPM': $Persona = 26; break;
        case 'Tatarabuela MPM': $Persona = 27; break;
        case 'Tatarabuelo MMP': $Persona = 28; break;
        case 'Tatarabuela MMP': $Persona = 29; break;
        case 'Tatarabuelo MMM': $Persona = 30; break;
        case 'Tatarabuela MMM': $Persona = 31; break;
    }
    return $Persona;
}

// Obtener generación
function GetGeneracion($IDPersona)
{
    $Generacion = NULL;
    switch ($IDPersona) {
        case 1: $Generacion = 1; break;
        case 2: $Generacion = 2; break;
        case 3: $Generacion = 2; break;
        case 4: $Generacion = 3; break;
        case 5: $Generacion = 3; break;
        case 6: $Generacion = 3; break;
        case 7: $Generacion = 3; break;
        case 8: $Generacion = 4; break;
        case 9: $Generacion = 4; break;
        case 10: $Generacion = 4; break;
        case 11: $Generacion = 4; break;
        case 12: $Generacion = 4; break;
        case 13: $Generacion = 4; break;
        case 14: $Generacion = 4; break;
        case 15: $Generacion = 4; break;
        case 16: $Generacion = 5; break;
        case 17: $Generacion = 5; break;
        case 18: $Generacion = 5; break;
        case 19: $Generacion = 5; break;
        case 20: $Generacion = 5; break;
        case 21: $Generacion = 5; break;
        case 22: $Generacion = 5; break;
        case 23: $Generacion = 5; break;
        case 24: $Generacion = 5; break;
        case 25: $Generacion = 5; break;
        case 26: $Generacion = 5; break;
        case 27: $Generacion = 5; break;
        case 28: $Generacion = 5; break;
        case 29: $Generacion = 5; break;
        case 30: $Generacion = 5; break;
        case 31: $Generacion = 5; break;
    }
    return $Generacion;
}

// Obtener IDPadre
function GetIDPadre($IDPersona)
{
    $IDPadre = -1;
    switch ($IDPersona) {
        case 1:$IDPadre = 2; break;
        case 2: $IDPadre = 4; break;
        case 3: $IDPadre = 6; break;
        case 4: $IDPadre = 8; break;
        case 5: $IDPadre = 10; break;
        case 6: $IDPadre = 12; break;
        case 7: $IDPadre = 14; break;
        case 8: $IDPadre = 16; break;
        case 9: $IDPadre = 18; break;
        case 10: $IDPadre = 20; break;
        case 11: $IDPadre = 22; break;
        case 12: $IDPadre = 24; break;
        case 13: $IDPadre = 26; break;
        case 14: $IDPadre = 28; break;
        case 15: $IDPadre = 30; break;
    }
    return $IDPadre;
}

// Obtener IDHijo
function GetIDHijo($IDPersona)
{
    $IDHijo = null;
    switch ($IDPersona) {
        case 1:$IDHijo = null; break;
        case 2: $IDHijo = 1; break;
        case 3: $IDHijo = 1; break;
        case 4: $IDHijo = 2; break;
        case 5: $IDHijo = 2; break;
        case 6: $IDHijo = 3; break;
        case 7: $IDHijo = 3; break;
        case 8: $IDHijo = 4; break;
        case 9: $IDHijo = 4; break;
        case 10: $IDHijo = 5; break;
        case 11: $IDHijo = 5; break;
        case 12: $IDHijo = 6; break;
        case 13: $IDHijo = 6; break;
        case 14: $IDHijo = 7; break;
        case 15: $IDHijo = 7; break;
        case 16: $IDHijo = 8; break;
        case 17: $IDHijo = 8; break;
        case 18: $IDHijo = 9; break;
        case 19: $IDHijo = 9; break;
        case 20: $IDHijo = 10; break;
        case 21: $IDHijo = 10; break;
        case 22: $IDHijo = 11; break;
        case 23: $IDHijo = 11; break;
        case 24: $IDHijo = 12; break;
        case 25: $IDHijo = 12; break;
        case 26: $IDHijo = 13; break;
        case 27: $IDHijo = 13; break;
        case 28: $IDHijo = 14; break;
        case 29: $IDHijo = 14; break;
        case 30: $IDHijo = 15; break;
        case 31: $IDHijo = 15; break;
    }
    return $IDHijo;
}

// Obtener id de la persona IDPersona
function GetID($agclientes, $IDPersona){
    try{
        return $agclientes->where('IDPersona',$IDPersona)->first()->id;
    }catch(Exception $e){
        return null;
    }
}

// Obtener nombres de la persona IDPersona
function GetNombres($agclientes, $IDPersona){
    try{
        return $agclientes->where('IDPersona',$IDPersona)->first()->Nombres;
    }catch(Exception $e){
        return null;
    }
}

// Obtener apellidos de la persona IDPersona
function GetApellidos($agclientes, $IDPersona){
    try{
        return $agclientes->where('IDPersona',$IDPersona)->first()->Apellidos;
    }catch(Exception $e){
        return null;
    }
}

// Obtener campo Familiares de la persona IDPersona
function GetFamiliares($agclientes, $IDPersona){
    try{
        return $agclientes->where('IDPersona',$IDPersona)->first()->Familiares;
    }catch(Exception $e){
        return null;
    }
}

// Obtener sexo de la persona IDPersona
function GetSexo($agclientes, $IDPersona){
    try{
        if($IDPersona){
            return $agclientes->where('IDPersona',$IDPersona)->first()->Sexo;
        }else{
            return $IDPersona % 2 ? "F" : "M";
        }
    }catch(Exception $e){
        return null;
    }
}

// Obtener observaciones de la persona IDPersona
function GetObservaciones($agclientes, $IDPersona){
    try{
        return $agclientes->where('IDPersona',$IDPersona)->first()->Observaciones;
    }catch(Exception $e){
        return null;
    }
}

// Obtener año de nacimiento de la persona IDPersona
function GetAnhoNac($agclientes, $IDPersona){
    try{
        return $agclientes->where('IDPersona',$IDPersona)->first()->AnhoNac;
    }catch(Exception $e){
        return null;
    }
}

// Obtener mes de nacimiento de la persona IDPersona
function GetMesNac($agclientes, $IDPersona){
    try{
        return $agclientes->where('IDPersona',$IDPersona)->first()->MesNac;
    }catch(Exception $e){
        return null;
    }
}

// Obtener día de nacimiento de la persona IDPersona
function GetDiaNac($agclientes, $IDPersona){
    try{
        return $agclientes->where('IDPersona',$IDPersona)->first()->DiaNac;
    }catch(Exception $e){
        return null;
    }
}

// Obtener lugar de nacimiento de la persona IDPersona
function GetLugarNac($agclientes, $IDPersona){
    try{
        return $agclientes->where('IDPersona',$IDPersona)->first()->LugarNac;
    }catch(Exception $e){
        return null;
    }
}

// Obtener país de nacimiento de la persona IDPersona
function GetPaisNac($agclientes, $IDPersona){
    try{
        return $agclientes->where('IDPersona',$IDPersona)->first()->PaisNac;
    }catch(Exception $e){
        return null;
    }
}

// Obtener año de bautizo de la persona IDPersona
function GetAnhoBtzo($agclientes, $IDPersona){
    try{
        return $agclientes->where('IDPersona',$IDPersona)->first()->AnhoBtzo;
    }catch(Exception $e){
        return null;
    }
}

// Obtener mes de bautizo de la persona IDPersona
function GetMesBtzo($agclientes, $IDPersona){
    try{
        return $agclientes->where('IDPersona',$IDPersona)->first()->MesBtzo;
    }catch(Exception $e){
        return null;
    }
}

// Obtener día de bautizo de la persona IDPersona
function GetDiaBtzo($agclientes, $IDPersona){
    try{
        return $agclientes->where('IDPersona',$IDPersona)->first()->DiaBtzo;
    }catch(Exception $e){
        return null;
    }
}

// Obtener lugar de bautizo de la persona IDPersona
function GetLugarBtzo($agclientes, $IDPersona){
    try{
        return $agclientes->where('IDPersona',$IDPersona)->first()->LugarBtzo;
    }catch(Exception $e){
        return null;
    }
}

// Obtener país de bautizo de la persona IDPersona
function GetPaisBtzo($agclientes, $IDPersona){
    try{
        return $agclientes->where('IDPersona',$IDPersona)->first()->PaisBtzo;
    }catch(Exception $e){
        return null;
    }
}

// Obtener año de matrimonio de la persona IDPersona
function GetAnhoMatr($agclientes, $IDPersona){
    try{
        return $agclientes->where('IDPersona',$IDPersona)->first()->AnhoMatr;
    }catch(Exception $e){
        return null;
    }
}

// Obtener mes de matrimonio de la persona IDPersona
function GetMesMatr($agclientes, $IDPersona){
    try{
        return $agclientes->where('IDPersona',$IDPersona)->first()->MesMatr;
    }catch(Exception $e){
        return null;
    }
}

// Obtener día de matrimonio de la persona IDPersona
function GetDiaMatr($agclientes, $IDPersona){
    try{
        return $agclientes->where('IDPersona',$IDPersona)->first()->DiaMatr;
    }catch(Exception $e){
        return null;
    }
}

// Obtener lugar de matrimonio de la persona IDPersona
function GetLugarMatr($agclientes, $IDPersona){
    try{
        return $agclientes->where('IDPersona',$IDPersona)->first()->LugarMatr;
    }catch(Exception $e){
        return null;
    }
}

// Obtener país de matrimonio de la persona IDPersona
function GetPaisMatr($agclientes, $IDPersona){
    try{
        return $agclientes->where('IDPersona',$IDPersona)->first()->PaisMatr;
    }catch(Exception $e){
        return null;
    }
}

// Obtener año de defunción de la persona IDPersona
function GetAnhoDef($agclientes, $IDPersona){
    try{
        return $agclientes->where('IDPersona',$IDPersona)->first()->AnhoDef;
    }catch(Exception $e){
        return null;
    }
}

// Obtener mes de defunción de la persona IDPersona
function GetMesDef($agclientes, $IDPersona){
    try{
        return $agclientes->where('IDPersona',$IDPersona)->first()->MesDef;
    }catch(Exception $e){
        return null;
    }
}

// Obtener día de defunción de la persona IDPersona
function GetDiaDef($agclientes, $IDPersona){
    try{
        return $agclientes->where('IDPersona',$IDPersona)->first()->DiaDef;
    }catch(Exception $e){
        return null;
    }
}

// Obtener lugar de defunción de la persona IDPersona
function GetLugarDef($agclientes, $IDPersona){
    try{
        return $agclientes->where('IDPersona',$IDPersona)->first()->LugarDef;
    }catch(Exception $e){
        return null;
    }
}

// Obtener país de defunción de la persona IDPersona
function GetPaisDef($agclientes, $IDPersona){
    try{
        return $agclientes->where('IDPersona',$IDPersona)->first()->PaisDef;
    }catch(Exception $e){
        return null;
    }
}

// Obtener fecha de matrimonio de la persona IDPersona
function GetFechaMatr($agclientes, $IDPersona){
    try{
        $AnhoMatr = $agclientes->where('IDPersona',$IDPersona)->first()->AnhoMatr;
        $AnhoMatr = $AnhoMatr ? $AnhoMatr : '--';
        $MesMatr = $agclientes->where('IDPersona',$IDPersona)->first()->MesMatr;
        $MesMatr = $MesMatr ? $MesMatr : '--';
        $DiaMatr = $agclientes->where('IDPersona',$IDPersona)->first()->DiaMatr;
        $DiaMatr = $DiaMatr ? $DiaMatr : '--';
        
        return "$DiaMatr / $MesMatr / $AnhoMatr";
    }catch(Exception $e){
        return null;
    }
}

// Obtener datos de matrimonio de la persona IDPersona
function GetDatosMatrimonio($agclientes, $IDPersona){
    $matrimonio = "Matrimonio: ";
    $lugar = GetLugarMatr($agclientes,$IDPersona);
    $matrimonio = $matrimonio . $lugar . ' ';
    $fecha = GetFechaMatr($agclientes,$IDPersona);
    $matrimonio = $matrimonio . $fecha;
    return $matrimonio;
}

// Obtener años de vida de la persona IDPersona
function GetVida($agclientes, $IDPersona){
    try{
        $AnhoNac = $agclientes->where('IDPersona',$IDPersona)->first()->AnhoNac;
        $AnhoNac = $AnhoNac ? $AnhoNac : '--';
        $AnhoDef = $agclientes->where('IDPersona',$IDPersona)->first()->AnhoDef;
        $AnhoDef = $AnhoDef ? $AnhoDef : '--';
        return $AnhoNac . ' / ' . $AnhoDef;
    }catch(Exception $e){
        return null;
    }
}

// Obtener lugar y años de vida de la persona IDPersona
function GetVidaCompleta($agclientes, $IDPersona){
    try{
        $AnhoNac = $agclientes->where('IDPersona',$IDPersona)->first()->AnhoNac;
        $AnhoNac = $AnhoNac ? $AnhoNac : '--';
        $MesNac = $agclientes->where('IDPersona',$IDPersona)->first()->MesNac;
        $MesNac = $MesNac ? $MesNac : '--';
        $DiaNac = $agclientes->where('IDPersona',$IDPersona)->first()->DiaNac;
        $DiaNac = $DiaNac ? $DiaNac : '--';
        $LugarNac = $agclientes->where('IDPersona',$IDPersona)->first()->LugarNac;
        $Nacimiento ="Nacimiento: $LugarNac, $DiaNac / $MesNac / $AnhoNac";

        $AnhoDef = $agclientes->where('IDPersona',$IDPersona)->first()->AnhoDef;
        $AnhoDef = $AnhoDef ? $AnhoDef : '--';
        $MesDef = $agclientes->where('IDPersona',$IDPersona)->first()->MesDef;
        $MesDef = $MesDef ? $MesDef : '--';
        $DiaDef = $agclientes->where('IDPersona',$IDPersona)->first()->DiaDef;
        $DiaDef = $DiaDef ? $DiaDef : '--';
        $LugarDef = $agclientes->where('IDPersona',$IDPersona)->first()->LugarDef;
        $Defuncion ="Defunción: $LugarDef, $DiaDef / $MesDef / $AnhoDef";

        return "$Nacimiento - $Defuncion";
    }catch(Exception $e){
        return null;
    }
}

function AddPersona($IDCliente, $IDPersona){
    $Sexo = $IDPersona % 2 ? 'F' : 'M';
    $agcliente = Agcliente::create([
        'IDCliente' => $IDCliente,
        'IDPersona' => $IDPersona,
        'Sexo' => $Sexo,
        'IDPadre' => GetIDPadre($IDPersona),
        'IDMadre' => GetIDPadre($IDPersona) + 1,
        'Generacion' => GetGeneracion($IDPersona),
        'FUpdate' => date('Y-m-d H:i:s'),
        'Usuario' => Auth()->user()->email,  
    ]);
    return $agcliente;
}

// Contenido de la caja de persona para la cajas de la vista Olivo
function GetBoxPerson($agclientes, $IDPersona): string{
    $contenido = '
        <p class="text-xs underline" title="'.GetDatosMatrimonio($agclientes,$IDPersona).'">'.GetPersona($IDPersona).'</p>
        <p class="text-xs font-bold">'.GetNombres($agclientes, $IDPersona).'</p>
        <p class="text-xs font-bold">'.GetApellidos($agclientes, $IDPersona).'</p>
    ';
    /* 
    <p class="text-xs font-bold">{{ GetNombres($agclientes,1) }}</p>
    <p class="text-xs font-bold">{{ GetApellidos($agclientes,1) }}</p>
    @php
        $mostraLN = GetLugarNac($agclientes,1);
    @endphp
    <p class="text-xs">{{ $mostraLN = GetLugarNac($agclientes,1) }}</p>
    @if ($mostraLN)
        <p class="text-xs">Lugar de nacimiento</p>    
    @endif
    */
    //return $contenido;
    return GetPersona($IDPersona);
}