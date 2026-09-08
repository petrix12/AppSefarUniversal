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
use App\Services\GenealogyService;
use Illuminate\Support\Facades\Mail as Mail2;
use Illuminate\Validation\ValidationException;

class AgClienteNewController extends Controller
{
    private const SPANISH_MONTHS = [
        'enero' => 1,
        'febrero' => 2,
        'marzo' => 3,
        'abril' => 4,
        'mayo' => 5,
        'junio' => 6,
        'julio' => 7,
        'agosto' => 8,
        'septiembre' => 9,
        'setiembre' => 9,
        'octubre' => 10,
        'noviembre' => 11,
        'diciembre' => 12,
    ];

    public function storeNotCliente(Request $request)
    {
        $dateParts = $this->datePartsFromRequest($request);

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

            'AnhoNac' => $dateParts['AnhoNac'],
            'MesNac' => $dateParts['MesNac'],
            'DiaNac' => $dateParts['DiaNac'],
            'LugarNac' => trim($request->LugarNac),
            'PaisNac' => $request->PaisNac,

            'AnhoBtzo' => $dateParts['AnhoBtzo'],
            'MesBtzo' => $dateParts['MesBtzo'],
            'DiaBtzo' => $dateParts['DiaBtzo'],
            'LugarBtzo' => trim($request->LugarBtzo),
            'PaisBtzo' => $request->PaisBtzo,

            'AnhoMatr' => $dateParts['AnhoMatr'],
            'MesMatr' => $dateParts['MesMatr'],
            'DiaMatr' => $dateParts['DiaMatr'],
            'LugarMatr' => trim($request->LugarMatr),
            'PaisMatr' => $request->PaisMatr,

            'AnhoDef' => $dateParts['AnhoDef'],
            'MesDef' => $dateParts['MesDef'],
            'DiaDef' => $dateParts['DiaDef'],
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
        app(GenealogyService::class)->forgetProcessedTree(trim($request->IDCliente));

        $people = json_decode(json_encode(Agcliente::where("IDCliente",trim($request->IDCliente))->get()),true);

        $arreglo = $people;
        $generaciones = array();

        foreach ($arreglo as $id => $persona) {
            if ($persona['idPadreNew'] === null && $persona['idMadreNew'] === null) {
                $generaciones[$persona["id"]] = 1;
            }
        }

        $cambio = true;
        $guard = 0;
        $maxIterations = count($arreglo) + 1;

        while ($cambio && $guard < $maxIterations) {
            $guard++;
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

        $maxGeneraciones = min(max($generaciones), 5);
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
        //     'sistemasccs@sefarvzla.com',
        //     'automatizacion@sefarvzla.com',
        //     'sistemascol@sefarvzla.com',
        //     /* 'egonzalez@sefarvzla.com', */
        //     /* 'arosales@sefarvzla.com', */
        //     'asistentedeproduccion@sefarvzla.com',
        //     'organizacionrrhh@sefarvzla.com',
        //     'arodriguez@sefarvzla.com',
        //     '20053496@bcc.hubspot.com'
        //     /* 'organizacionrrhh@sefarvzla.com' */
        // ])->send($mail_sefar);

        $user = auth()->user();
        $recipients = [
            'pedro.bazo@sefarvzla.com',
            'sistemasccs@sefarvzla.com',
            'automatizacion@sefarvzla.com',
            'sistemascol@sefarvzla.com',
            'asistentedeproduccion@sefarvzla.com',
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
        $dateParts = $this->datePartsFromRequest($request);
        $agcliente = Agcliente::findOrFail($request->id);

        $agcliente->update([
            'AnhoNac' => $dateParts['AnhoNac'],
            'MesNac' => $dateParts['MesNac'],
            'DiaNac' => $dateParts['DiaNac'],
            'LugarNac' => trim($request->LugarNac),
            'PaisNac' => $request->PaisNac,

            'NPasaporte' => trim($request->NPasaporte),
            'PaisPasaporte' => $request->PaisPasaporte,
            'NDocIdent' => trim($request->NDocIdent),
            'PaisDocIdent' => $request->PaisDocIdent,

            'AnhoBtzo' => $dateParts['AnhoBtzo'],
            'MesBtzo' => $dateParts['MesBtzo'],
            'DiaBtzo' => $dateParts['DiaBtzo'],
            'LugarBtzo' => trim($request->LugarBtzo),
            'PaisBtzo' => $request->PaisBtzo,

            'AnhoMatr' => $dateParts['AnhoMatr'],
            'MesMatr' => $dateParts['MesMatr'],
            'DiaMatr' => $dateParts['DiaMatr'],
            'LugarMatr' => trim($request->LugarMatr),
            'PaisMatr' => $request->PaisMatr,

            'AnhoDef' => $dateParts['AnhoDef'],
            'MesDef' => $dateParts['MesDef'],
            'DiaDef' => $dateParts['DiaDef'],
            'LugarDef' => trim($request->LugarDef),
            'PaisDef' => $request->PaisDef,

            'Observaciones' => $request->Observaciones,
            'Nombres' => trim($request->Nombres),
            'Apellidos' => trim($request->Apellidos),
        ]);

        app(GenealogyService::class)->forgetProcessedTree($agcliente->IDCliente);

        return redirect()->back()->withInput()->with('refresh', true);
    }

    /**
     * The tree stores each date in the legacy Year/Month/Day columns.  A
     * genealogical source does not always provide every part of a date, so the
     * form accepts a year, month/year, or a complete date without inventing a
     * missing month or day. Direct legacy submissions remain supported too.
     */
    private function datePartsFromRequest(Request $request): array
    {
        $request->validate([
            'FechaNac' => ['nullable', 'string', 'max:50'],
            'FechaBtzo' => ['nullable', 'string', 'max:50'],
            'FechaMatr' => ['nullable', 'string', 'max:50'],
            'FechaDef' => ['nullable', 'string', 'max:50'],
        ]);

        $parts = [];
        $usesPrecisionAwareFields = $request->boolean('tree_date_precision_input');

        foreach ([
            'Nac' => 'FechaNac',
            'Btzo' => 'FechaBtzo',
            'Matr' => 'FechaMatr',
            'Def' => 'FechaDef',
        ] as $suffix => $field) {
            $date = trim((string) $request->input($field));

            // Older forms and integrations can still post separate date
            // components. Their empty combined field means "keep those
            // components"; the precision-aware form marks itself so a blank
            // field there intentionally clears the date.
            if (!$request->has($field) || (!$usesPrecisionAwareFields && $date === '')) {
                $parts['Anho' . $suffix] = $request->input('Anho' . $suffix);
                $parts['Mes' . $suffix] = $request->input('Mes' . $suffix);
                $parts['Dia' . $suffix] = $request->input('Dia' . $suffix);

                continue;
            }

            if ($date === '') {
                $parts['Anho' . $suffix] = null;
                $parts['Mes' . $suffix] = null;
                $parts['Dia' . $suffix] = null;

                continue;
            }

            $parsedDate = $this->parseTreeDate($date);

            if ($parsedDate === null) {
                throw ValidationException::withMessages([
                    $field => 'Indique una fecha válida: aaaa, mm/aaaa, dd/mm/aaaa o 12 de junio de 1583.',
                ]);
            }

            $parts['Anho' . $suffix] = $parsedDate['year'];
            $parts['Mes' . $suffix] = $parsedDate['month'];
            $parts['Dia' . $suffix] = $parsedDate['day'];
        }

        return $parts;
    }

    private function parseTreeDate(string $value): ?array
    {
        $date = trim(preg_replace('/\s+/', ' ', $value));
        $numericDate = str_replace(' ', '', $date);
        $year = null;
        $month = null;
        $day = null;

        if (preg_match('/^(\d{1,2})\s+de\s+([\p{L}]+)\s+de\s+(\d{4})$/ui', $date, $matches)) {
            $day = (int) $matches[1];
            $month = self::SPANISH_MONTHS[strtolower($matches[2])] ?? null;
            $year = (int) $matches[3];
        } elseif (preg_match('/^(\d{4})$/', $numericDate, $matches)) {
            $year = (int) $matches[1];
        } elseif (preg_match('/^(\d{1,2})[.\/-](\d{4})$/', $numericDate, $matches)) {
            $month = (int) $matches[1];
            $year = (int) $matches[2];
        } elseif (preg_match('/^(\d{4})[.\/-](\d{1,2})$/', $numericDate, $matches)) {
            $year = (int) $matches[1];
            $month = (int) $matches[2];
        } elseif (preg_match('/^(\d{1,2})[.\/-](\d{1,2})[.\/-](\d{4})$/', $numericDate, $matches)) {
            $day = (int) $matches[1];
            $month = (int) $matches[2];
            $year = (int) $matches[3];
        } elseif (preg_match('/^(\d{4})[.\/-](\d{1,2})[.\/-](\d{1,2})$/', $numericDate, $matches)) {
            $year = (int) $matches[1];
            $month = (int) $matches[2];
            $day = (int) $matches[3];
        }

        if ($year === null || $year < 1 || $year > 3000) {
            return null;
        }

        if ($month !== null && ($month < 1 || $month > 12)) {
            return null;
        }

        if ($day !== null && ($month === null || !checkdate($month, $day, $year))) {
            return null;
        }

        return compact('year', 'month', 'day');
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
