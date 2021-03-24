<?php
    // Obtener persona
    function GetPersona($IDPersona){
        $Persona = '';
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