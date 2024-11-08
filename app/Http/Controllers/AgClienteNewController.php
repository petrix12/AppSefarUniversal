<?php

namespace App\Http\Controllers;

use App\Models\Agcliente;
use App\Models\Country;
use App\Models\User;
use App\Models\Compras as Compra;
use App\Models\File;
use App\Models\TFile;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use RealRashid\SweetAlert\Facades\Alert;
use App\Mail\CargaSefar;
use Illuminate\Support\Facades\Mail as Mail2;

class AgClienteNewController extends Controller
{
    public function storeNotCliente(Request $request)
    {

        // Determinar quien llamó a este método
        $Origen = $request->Origen;

        $PNacimiento = trim($request->PaisNac);
        $LNacimiento = trim($request->LugarNac);
        $Usuario = Auth()->user()->email;

        $FUpdate = date('Y-m-d H:i:s');
        $Familiares = trim($request->Familiaridad);

        $referido = Auth()->user()->getRoleNames()[0];
        if($referido == "Traviesoevans"){
            $referido = "Travieso Evans";
        }
        if($referido == "Vargassequera"){
            $referido = "Patricia Vargas Sequera";
        }
        if($referido == "BadellLaw"){
            $referido = "Badell Law";
        }
        if($referido == "P&V-Abogados"){
            $referido = "P & V Abogados";
        }
        if($referido == "Mujica-Coto"){
            $referido = "Mujica y Coto Abogados";
        }
        if($referido == "German-Fleitas"){
            $referido = "German Fleitas";
        }
        if($referido == "Soma-Consultores"){
            $referido = "Soma Consultores";
        }

        // Creando persona en agcliente
        $agcliente = Agcliente::create([
            'IDCliente' => trim($request->IDCliente),
            'Nombres' => trim($request->Nombres),
            'Apellidos' => trim($request->Apellidos),

            'IDPersona' => $request->IDPersona,

            'NPasaporte' => trim($request->NPasaporte),
            'PaisPasaporte' => $request->PaisPasaporte,
            'NDocIdent' => trim($request->NDocIdent),
            'PaisDocIdent' => $request->PaisDocIdent,

            'Sexo' => $request->Sexo,

            'AnhoNac' => $request->AnhoNac,
            'MesNac' => $request->MesNac,
            'DiaNac' => $request->DiaNac,
            'LugarNac' => trim($request->LugarNac),
            'PaisNac' => $request->PaisNac,

            'AnhoBtzo' => $request->AnhoBtzo,
            'MesBtzo' => $request->MesBtzo,
            'DiaBtzo' => $request->DiaBtzo,
            'LugarBtzo' => trim($request->LugarBtzo),
            'PaisBtzo' => $request->PaisBtzo,

            'AnhoMatr' => $request->AnhoMatr,
            'MesMatr' => $request->MesMatr,
            'DiaMatr' => $request->DiaMatr,
            'LugarMatr' => trim($request->LugarMatr),
            'PaisMatr' => $request->PaisMatr,

            'AnhoDef' => $request->AnhoDef,
            'MesDef' => $request->MesDef,
            'DiaDef' => $request->DiaDef,
            'LugarDef' => trim($request->LugarDef),
            'PaisDef' => $request->PaisDef,

            'Familiaridad' => $request->Familiaridad,
            'NombresF' => trim($request->NombresF),
            'ApellidosF' => trim($request->ApellidosF),
            'ParentescoF' => trim($request->ParentescoF),
            'NPasaporteF' => trim($request->NPasaporteF),

            'FRegistro' => $request->FRegistro,
            'Observaciones' => $request->Observaciones,
            'Enlace' => $request->Enlace,
            'referido' => $referido,

            'migradoNuevoID' => 1,

            'PNacimiento' => $PNacimiento,
            'LNacimiento' => $LNacimiento,
            'Familiares' => $Familiares,
            'FUpdate' => $FUpdate,
            'Usuario' => $Usuario,
        ]);

        $update = Agcliente::find($request->id_hijo);
        if ($request->Sexo == "M") {
            $update->idPadreNew = $agcliente->id;
        } else {
            $update->idMadreNew = $agcliente->id;
        }

        $update->save();

        $people = json_decode(json_encode(Agcliente::where("IDCliente",trim($request->IDCliente))->get()),true);

        $arreglo = $people;
        $generaciones = array();

        foreach ($arreglo as $id => $persona) {
            if ($persona['idPadreNew'] === null && $persona['idMadreNew'] === null) {
                $generaciones[$persona["id"]] = 1;
            }
        }

        $cambio = true;
        while ($cambio) {
            $cambio = false;
            foreach ($arreglo as $id => $persona) {
                $generacionPadre = isset($generaciones[$persona['idPadreNew']]) ? $generaciones[$persona['idPadreNew']] : 0;
                $generacionMadre = isset($generaciones[$persona['idMadreNew']]) ? $generaciones[$persona['idMadreNew']] : 0;
                $generacionActual = max($generacionPadre, $generacionMadre) + 1;

                if (!isset($generaciones[$persona["id"]]) || $generaciones[$persona["id"]] != $generacionActual) {
                    $generaciones[$persona["id"]] = $generacionActual;
                    $cambio = true;
                }
            }
        }

        $maxGeneraciones = max($generaciones);
        $maxGeneraciones++;

        $columnasparatabla = array();

        for ($i=0; $i<$maxGeneraciones; $i++){
            if ($i == 0){
                if(!isset($columnasparatabla[$i])){
                    $columnasparatabla[$i] = [];
                }

                $columnasparatabla[$i][] =  $arreglo[0];
                $columnasparatabla[$i][0]["showbtn"] = 2;  //2 es persona, 1 es boton de añadir, 0 es nada
            } else {
                foreach ($columnasparatabla[$i-1] as $key2 => $persona2){

                    if(!isset($columnasparatabla[$i])){
                        $columnasparatabla[$i] = [];
                        $j = 0;
                    } else {
                        $j = sizeof($columnasparatabla[$i]);
                    }

                    //padre

                    if (@$persona2["idPadreNew"]==null){

                        if ($persona2["showbtn"] == 0) {
                            $columnasparatabla[$i][$j]["showbtn"] = 0;
                        } else if ($persona2["showbtn"] == 1) {
                            $columnasparatabla[$i][$j]["showbtn"] = 0;
                        } else {
                            $columnasparatabla[$i][$j]["showbtn"] = 1;
                            $columnasparatabla[$i][$j]["showbtnsex"] = "m";
                            $columnasparatabla[$i][$j]["id_hijo"] = $persona2["id"];
                        }

                    } else {
                        foreach ($arreglo as $key => $persona) {
                            if ($persona2["idPadreNew"] == $arreglo[$key]["id"]){
                                $columnasparatabla[$i][$j] = $arreglo[$key];
                                $columnasparatabla[$i][$j]["showbtn"] = 2;
                                break;
                            }
                        }

                    }

                    $j++;

                    // madre

                    if (@$persona2["idMadreNew"]==null){

                        if ($persona2["showbtn"] == 0) {
                            $columnasparatabla[$i][$j]["showbtn"] = 0;
                        } else if ($persona2["showbtn"] == 1) {
                            $columnasparatabla[$i][$j]["showbtn"] = 0;
                        } else {
                            $columnasparatabla[$i][$j]["showbtn"] = 1;
                            $columnasparatabla[$i][$j]["showbtnsex"] = "f";
                            $columnasparatabla[$i][$j]["id_hijo"] = $persona2["id"];
                        }

                    } else {

                        foreach ($arreglo as $key => $persona) {
                            if ($persona2["idMadreNew"] == $arreglo[$key]["id"]){
                                $columnasparatabla[$i][$j] = $arreglo[$key];
                                $columnasparatabla[$i][$j]["showbtn"] = 2;
                                break;
                            }
                        }

                    }
                }
            }
        }

        $tipoarchivos = TFile::all();

        $parentescos = [];
        $parentescos_post_padres = [
            "Abuel",
            "Bisabuel",
            "Tatarabuel",
            "Trastatarabuel",
            "Retatarabuel",
            "Sestarabuel",
            "Setatarabuel",
            "Octatarabuel",
            "Nonatarabuel",
            "Decatarabuel",
            "Undecatarabuel",
            "Duodecatarabuel",
            "Trececatarabuel",
            "Catorcatarabuel",
            "Quincecatarabuel",
            "Deciseiscatarabuel",
            "Decisietecatarabuel",
            "Deciochocatarabuel",
            "Decinuevecatarabuel",
            "Vigecatarabuel",
            "Vigecimoprimocatarabuel",
            "Vigecimosegundocatarabuel",
            "Vigecimotercercatarabuel",
            "Vigecimocuartocatarabuel",
            "Vigecimoquintocatarabuel",
            "Vigecimosextocatarabuel",
            "Vigecimoseptimocatarabuel",
            "Vigecimooctavocatarabuel",
            "Vigecimonovenocatarabuel",
            "Trigecatarabuel",
            "Trigecimoprimocatarabuel",
            "Trigecimosegundocatarabuel",
            "Trigecimotercercatarabuel",
            "Trigecimocuartocatarabuel",
            "Trigecimoquintocatarabuel",
            "Trigecimosextocatarabuel",
            "Trigecimoseptimocatarabuel",
            "Trigecimooctavocatarabuel",
            "Trigecimonovenocatarabuel",
            "Cuarentacatarabuel",
            "Cuarentaprimocatarabuel",
            "Cuarentasegundocatarabuel",
            "Cuarentatercercatarabuel",
        ];
        $prepar = 4;

        function generarTexto($i, $key) {
            $text = "";
            $multiplicador = 4;

            for ($j = 1; $j <= $key; $j++) {
                $text .= (($i % $multiplicador) < ($multiplicador / 2) ? "P " : "M ");
                $multiplicador *= 2;
            }

            $text .= ($i < 2 * ($key + 1) ? "P" : "M");
            return $text;
        }

        foreach ($parentescos_post_padres as $key => $parentesco) {
            if($key <= sizeof($columnasparatabla)){
                $parentescos[$key] = [];

                for ($i = 0; $i < $prepar; $i++) {
                    $textparentesco = $parentesco . ($i % 2 == 0 ? "o" : "a");
                    $text = generarTexto($i, $key);
                    $parentescos[$key][] = $textparentesco . " " . $text;
                }

                $prepar *= 2;
            }
        }
        foreach ($columnasparatabla as $key => $columna){
            foreach ($columna as $key2 => $persona) {
                if ($persona["showbtn"] == 2) {
                    if ($persona["PersonaIDNew"] == null || $persona["PersonaIDNew"] == "null"){
                        DB::table('agclientes')
                        ->where('id', $persona['id'])
                        ->update([
                            'PersonaIDNew' => $key2
                        ]);
                        $columnasparatabla[$key][$key2]["PersonaIDNew"] = $key2;
                    }
                }
            }
        }

        // $mail_sefar = new CargaSefar(auth()->user());
        // Mail2::to([
        //     'pedro.bazo@sefarvzla.com',
        //     'gerenciait@sefarvzla.com',
        //     'sistemasccs@sefarvzla.com',
        //     'automatizacion@sefarvzla.com',
        //     'sistemascol@sefarvzla.com',
        //     /* 'egonzalez@sefarvzla.com', */
        //     'analisisgenealogico@sefarvzla.com',
        //     /* 'arosales@sefarvzla.com', */
        //     'asistentedeproduccion@sefarvzla.com',
        //     'gcuriel@sefarvzla.com',
        //     'organizacionrrhh@sefarvzla.com',
        //     'arodriguez@sefarvzla.com',
        //     '20053496@bcc.hubspot.com'
        //     /* 'organizacionrrhh@sefarvzla.com' */
        // ])->send($mail_sefar);

        $user = auth()->user();
        $recipients = [
            'pedro.bazo@sefarvzla.com',
            'gerenciait@sefarvzla.com',
            'sistemasccs@sefarvzla.com',
            'automatizacion@sefarvzla.com',
            'sistemascol@sefarvzla.com',
            'analisisgenealogico@sefarvzla.com',
            'asistentedeproduccion@sefarvzla.com',
            'gcuriel@sefarvzla.com',
            'organizacionrrhh@sefarvzla.com',
            'arodriguez@sefarvzla.com',
            '20053496@bcc.hubspot.com'
        ];

        // Verifica si el usuario es un cliente
        if ($user->hasRole('Cliente')) {
            // Verifica si el usuario cumple con las condiciones del servicio "Española LMD"
            if (
                $user->servicio == "Española LMD" ||
                Compra::where('id_user', $user->id)
                    ->where('servicio_hs_id', "Española LMD")
                    ->exists()
            ) {
                $additionalEmail = 'lguzmanposso@sefarvzla.com';
                $recipients[] = $additionalEmail;
            }
            // Genera el objeto $mail_sefar para un cliente
            $mail_sefar = new CargaSefar($user);
        } else {
            // Busca el usuario que tenga el elemento passport igual a $request->IDCliente
            $clientUser = User::where('passport', $request->IDCliente)->first();
            if ($clientUser) {
                // Verifica si el cliente cumple con las condiciones del servicio "Española LMD"
                if (
                    $clientUser->servicio == "Española LMD" ||
                    Compra::where('id_user', $clientUser->id)
                        ->where('servicio_hs_id', "Española LMD")
                        ->exists()
                ) {
                    $additionalEmail = 'lguzmanposso@sefarvzla.com';
                    $recipients[] = $additionalEmail;
                }
                // Genera el objeto $mail_sefar para un usuario que no es el loggeado
                $mail_sefar = new CargaSefar($clientUser);
            }
        }

        // Enviar el correo si $mail_sefar está definido
        if (isset($mail_sefar)) {
            Mail2::to($recipients)->send($mail_sefar);
        }

        return redirect()->back()->withInput()->with('refresh', true);
    }

    public function updateNotCliente(Request $request)
    {
        $agcliente = Agcliente::findOrFail($request->id);

        $agcliente->update([
            'AnhoNac' => $request->AnhoNac,
            'MesNac' => $request->MesNac,
            'DiaNac' => $request->DiaNac,
            'LugarNac' => trim($request->LugarNac),
            'PaisNac' => $request->PaisNac,

            'NPasaporte' => trim($request->NPasaporte),
            'PaisPasaporte' => $request->PaisPasaporte,
            'NDocIdent' => trim($request->NDocIdent),
            'PaisDocIdent' => $request->PaisDocIdent,

            'AnhoBtzo' => $request->AnhoBtzo,
            'MesBtzo' => $request->MesBtzo,
            'DiaBtzo' => $request->DiaBtzo,
            'LugarBtzo' => trim($request->LugarBtzo),
            'PaisBtzo' => $request->PaisBtzo,

            'AnhoMatr' => $request->AnhoMatr,
            'MesMatr' => $request->MesMatr,
            'DiaMatr' => $request->DiaMatr,
            'LugarMatr' => trim($request->LugarMatr),
            'PaisMatr' => $request->PaisMatr,

            'AnhoDef' => $request->AnhoDef,
            'MesDef' => $request->MesDef,
            'DiaDef' => $request->DiaDef,
            'LugarDef' => trim($request->LugarDef),
            'PaisDef' => $request->PaisDef,

            'Observaciones' => $request->Observaciones,
            'Nombres' => trim($request->Nombres),
            'Apellidos' => trim($request->Apellidos),
        ]);

        return redirect()->back()->withInput()->with('refresh', true);
    }

    public function getClientFiles(Request $request)
    {
        $familiar = $request->familiarid;
        $clienteid = $request->clienteid;

        $firstcheck = File::where("IDPersonaNew", $familiar)->get();

        if (sizeof(json_decode(json_encode($firstcheck),true))==0){
            $datosag = json_decode(json_encode(Agcliente::find($familiar)),true);

            $id_persona_ag = $datosag["IDPersona"];

            $firstcheck = File::where("IDPersona", $id_persona_ag)->where("IDCliente", $clienteid)->get();

            foreach ($firstcheck as $file) {
                $file->MigradoNuevoID = 1;
                $file->IDPersonaNew = $familiar;
                $file->save();
            }

            $firstcheck = File::where("IDPersonaNew", $familiar)->get();
        }

        $final = array();

        $final["archivos"] =  $firstcheck;

        $final["tipodearchivos"] = TFile::all();

        return response()->json($final);
    }

    public function updatefiletype(Request $request)
    {
        $archivo = File::find($request->id);
        $archivo->tipo = $request->tipo;
        $archivo->save();

        $final = array();
        $final["status"] =  "ok";
        return response()->json($final);
    }

    public function storefile(Request $request){
        $user = User::where('passport', $request->IDCliente)->firstOrFail();

        $originalFileName = $request->file('archivo')->getClientOriginalName();

        $path = 'public/doc/P' . $request->IDCliente;

        Storage::disk('s3')->put($path."/".$originalFileName, file_get_contents($request->file('archivo')));

        File::create([
            'IDPersonaNew' => $request->IDPersonaNew,
            'IDCliente' => $request->IDCliente,
            'tipo' => $request->tipo,
            'file' => $originalFileName,
            'location' => $path,
            'notas' => $request->notas,
            'Propietario' => $user->name,
            'user_id' => $user->id,
        ]);
        return redirect()->back()->withInput();
    }

    public function openfile(Request $request){
        $path = $request->path;

        $url = Storage::disk('s3')->temporaryUrl($path, now()->addMinutes(5));

        return response()->json(['url' => $url]);
    }

    public function deletefile(Request $request)
    {
        $fileId = $request->input('fileId');

        $file = File::findOrFail($fileId);

        if (Storage::disk('s3')->exists($file->location . '/' . $file->file)) {
            Storage::disk('s3')->delete($file->location . '/' . $file->file);
        }

        $file->delete();

        $final = array();
        $final["status"] =  "ok";
        return response()->json($final);
    }

    public function getfileedit(Request $request)
    {
        $fileId = $request->input('fileId');

        $file = File::findOrFail($fileId);

        return response()->json($file);
    }

    public function getfileupdate(Request $request)
    {
        $fileId = $request->id;

        $file = File::findOrFail($fileId);

        $file->update([
            'tipo' => $request->tipo,
            'notas' => $request->notas,
        ]);

        return redirect()->back()->withInput();
    }
}
