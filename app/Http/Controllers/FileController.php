<?php

namespace App\Http\Controllers;

use App\Models\File;
use Exception;
use App\Models\TFile;
use App\Models\User;
use App\Models\Agcliente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use RealRashid\SweetAlert\Facades\Alert;
use Illuminate\Support\Facades\Storage;
use App\Services\GenealogyDocumentService;
use App\Services\GenealogyDocumentUploadNotifier;
use App\Services\HubspotService;

class FileController extends Controller
{
    public function __construct(
        private GenealogyDocumentService $documents,
        private GenealogyDocumentUploadNotifier $uploadNotifier,
    )
    {
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return view('crud.files.index');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $t_files = TFile::all();
        return view('crud.files.create', compact('t_files'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        // Filtrar tamaño del archivo
        $max_size = (int)ini_get('upload_max_filesize') * 10240 * 80;
        $peso_file = filesize($request->file('file'));
        if($peso_file > $max_size){
            Alert::error('¡Error!', 'El archivo supera el tamaño máximo permitido.');
            return back();
        }

        // Filtrar extensiones
        $abortar = false;
        switch ($request->file->getClientOriginalExtension()) {
            case 'exe':
                $abortar = true;
                break;
            case 'bat':
                $abortar = true;
                break;
            case 'con':
                $abortar = true;
                break;
            case 'bin':
                $abortar = true;
                break;
            case 'msi':
                $abortar = true;
                break;
            case 'cmd':
                $abortar = true;
                break;
            case 'vbs':
                $abortar = true;
                break;
            case 'vbe':
                $abortar = true;
                break;
            case 'js':
                $abortar = true;
                break;
            case 'lnk':
                $abortar = true;
                break;
        }
        if ($abortar){
            Alert::error('¡Error!', 'No esta permitida la carga de archivo con extensión .'.$request->file->getClientOriginalExtension());
            return back();
        }

        // Validación
        $request->validate([
            'nfile' => 'max:250',
            'file' => 'required',
            'IDPersona' => 'required',
            'notas' => 'max:255',
            'IDCliente' => 'max:175',
        ]);

        // Asignando documento a usuario
        if($request->IDCliente){
            try{
                $user = User::where('passport','LIKE',$request->IDCliente)->first();
                $user_id = $user->id;
                $propietario = $user->name;
                $IDCliente = $user->passport;
            }catch(Exception $e){
                Alert::error('¡Error!', "El IDCliente $request->IDCliente no se encuentra en nuestra base de datos");
                return back();
            }
        }else{
            $user_id = Auth::id();
            $propietario = Auth::user()->name;
            $IDCliente = Auth::user()->passport;
        }

        // Ubicación del documento
        /* $anho = date('Y'); */
        if($request->Origen == "arbol"){
            //$carpeta = 'doc/P'.$pasaporte.'/'.$persona.'/';
            /* $location = str_replace('.','','public/doc/P'.$IDCliente.'/'.GetPersona($request->IDPersona)); */
            $location = 'public/doc/P'.$IDCliente.'/'.GetPersona($request->IDPersona);
        }else{
            // $location = str_replace('.','','public/documentos/'.$anho.'/'.$user_id.'/'.GetPersona($request->IDPersona));
            $location = 'public/doc/P'.$IDCliente.'/'.GetPersona($request->IDPersona);
        }

        // Guarda el archivo en el servidor y registra el archivo en la tabla files
        if($request->hasFile('file')){
            // Nombre del archivo
            if($request->nfile){
                $fileName = $request->nfile.".".$request->file->getClientOriginalExtension();
            } else {
                $fileName = $request->file->getClientOriginalName();
            }
            /* if(Storage::putFileAs('/imagenes/paises/' , $request->file, $fileImg)){ */
            /* if($request->file('file')->storePubliclyAs($location, $fileName)){ */
            $ruta = Storage::disk('s3')->putFileAs($location, $request->file, $fileName, 'public');
            if($ruta) {
                // Agregando registro a la tabla files
                $documentKind = $request->input('document_kind') ?: GenealogyDocumentService::inferKind($request->tipo);
                $clientUpload = Auth::check()
                    && Auth::id() === $user_id
                    && Auth::user()->hasRole('Cliente');

                $storedFile = File::create([
                    'file' => $fileName,
                    'location' => $location,
                    'tipo' => $request->tipo,
                    'propietario' => $propietario,
                    'IDCliente' => $IDCliente,
                    'notas' => $request->notas,
                    'IDPersona' => $request->IDPersona,
                    'user_id' => $user_id,
                    'source' => $clientUpload ? 'app_cliente' : 'staff_upload',
                    'client_visible' => $clientUpload && GenealogyDocumentService::isAllowedKind($documentKind),
                    'document_kind' => $documentKind,
                    'mime_type' => $request->file('file')->getMimeType(),
                    'size_bytes' => $request->file('file')->getSize(),
                ]);

                if ($clientUpload) {
                    $person = Agcliente::query()
                        ->where('IDCliente', $IDCliente)
                        ->where('IDPersona', $request->IDPersona)
                        ->first();
                    $this->uploadNotifier->notify(Auth::user(), $storedFile, $person);
                }

                // Mensaje
                Alert::success('¡Éxito!', 'Se ha añadido el documento: ' . $fileName);

                // Redireccionar a la vista index
                if($request->Origen == "arbol"){
                    return back();
                }else{
                    return redirect()->route('crud.files.index');
                }
            }
        }else{
            Alert::error('¡Error!', 'No se pudo añadir el archivo');
            return back();
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\File  $file
     * @return \Illuminate\Http\Response
     */
    public function show(File $file)
    {
        abort_unless(Auth::check() && $this->documents->canView(Auth::user(), $file), 403);
        /* $pathtoFile = storage_path().'/app/'.$file->location.'/'.$file->file;
        return response()->file($pathtoFile); */
        //return Storage::disk('s3')->url($file->location.'/'.$file->file);
        if(!Storage::disk('s3')->exists($file->location . '/' . $file->file)){
            Alert::error('¡Error!', 'No se ha podido ubicar el documento solicitado.');
            return back();
        }

        return redirect()->away($this->documents->temporaryUrl($file));
    }

    public function viewfile($id)
    {
        $file = File::findOrFail($id);

        abort_unless(Auth::check() && $this->documents->canView(Auth::user(), $file), 403);

        $fileroute = preg_replace('/\/+/', '/', $file->location . "/" . $file->file);

        // Verificar si el archivo existe en S3
        if (!Storage::disk('s3')->exists($fileroute)) {
            abort(404);
        }

        return redirect()->away($this->documents->temporaryUrl($file));
    }

    /**
     * An internal curator decides whether an imported HubSpot file belongs to a
     * genealogy person/union and whether it becomes visible to the client.
     */
    public function associateGenealogyDocument(Request $request, File $file)
    {
        abort_unless(Auth::check() && Auth::user()->can('administrar.documentos'), 403);
        abort_unless($file->source === 'hubspot', 422, 'Solo se pueden asociar desde aquí los archivos importados de HubSpot.');
        $canShareWithClient = HubspotService::isClientEligibleFileSource($file->source, $file->source_reference);

        $documentKinds = array_keys(GenealogyDocumentService::kinds());
        $data = $request->validate([
            'person_id' => ['required', 'integer'],
            'document_kind' => ['required', 'string', 'in:' . implode(',', $documentKinds)],
            'spouse_id' => ['nullable', 'integer'],
            'share_with_client' => ['nullable', 'boolean'],
        ]);

        $person = Agcliente::query()
            ->where('IDCliente', $file->IDCliente)
            ->findOrFail($data['person_id']);

        abort_unless(
            array_key_exists($data['document_kind'], GenealogyDocumentService::allowedKindsForPerson($person)),
            422,
            'Este documento no se solicita para la persona principal.'
        );

        // A curated association replaces a prior automatic or legacy one. In
        // particular, this prevents a marriage certificate from remaining tied
        // to just one spouse after it is linked to the union.
        $file->people()->detach();
        $file->genealogyUnions()->detach();
        $file->forceFill(['IDPersonaNew' => null, 'IDPersona' => 0])->save();

        if ($data['document_kind'] === GenealogyDocumentService::KIND_MARRIAGE) {
            abort_unless(filled($data['spouse_id'] ?? null), 422, 'Seleccione el otro cónyuge.');

            $spouse = Agcliente::query()
                ->where('IDCliente', $file->IDCliente)
                ->findOrFail($data['spouse_id']);

            abort_unless($spouse->id !== $person->id, 422, 'Seleccione dos personas distintas.');
            $this->documents->associateUnion($file, $this->documents->findOrCreateUnion($person, $spouse));
        } else {
            $this->documents->associatePerson($file, $person);
        }

        $file->forceFill([
            'document_kind' => $data['document_kind'],
            'tipo' => GenealogyDocumentService::label($data['document_kind']),
            'client_visible' => $canShareWithClient && $request->boolean('share_with_client'),
        ])->save();

        $message = ! $canShareWithClient
            ? 'Archivo asociado. Este campo de HubSpot es exclusivamente interno.'
            : ($request->boolean('share_with_client')
                ? 'Archivo asociado y compartido con el cliente.'
                : 'Archivo asociado. Sigue siendo privado para el cliente.');

        return response()->json([
            'message' => $message,
            'file' => $this->documents->present($file->fresh()),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\File  $file
     * @return \Illuminate\Http\Response
     */
    public function edit(File $file)
    {
        $user_id = Auth::id();
        if(Auth::user()->hasPermissionTo("administrar.documentos") or ($file->user_id == $user_id)){
            $t_files = TFile::all();
            $user = User::where('id',$file->user_id)->first();
            $IDCliente = $user ? $user->passport : null;
            //dd($IDCliente);
            return view('crud.files.edit', compact('file', 'IDCliente', 't_files'));
        }else{
            Alert::error('¡Warning!', 'No tiene permisos para ver este archivo');
            return back();
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\File  $file
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, File $file)
    {
        /* dd($request->nfile, $request->file('file')); */
        // Filtrar tamaño del archivo
        $max_size = (int)ini_get('upload_max_filesize') * 10240 * 20;
        $peso_file = filesize($request->file('file'));
        if($peso_file > $max_size){
            Alert::error('¡Error!', 'El archivo supera el tamaño máximo permitido.');
            return back();
        }

        // Filtrar extensiones
        if($request->file('file')){
            $abortar = false;
            switch ($request->file->getClientOriginalExtension()) {
                case 'exe':
                    $abortar = true;
                    break;
                case 'bat':
                    $abortar = true;
                    break;
                case 'con':
                    $abortar = true;
                    break;
                case 'bin':
                    $abortar = true;
                    break;
                case 'msi':
                    $abortar = true;
                    break;
                case 'cmd':
                    $abortar = true;
                    break;
                case 'vbs':
                    $abortar = true;
                    break;
                case 'vbe':
                    $abortar = true;
                    break;
                case 'js':
                    $abortar = true;
                    break;
                case 'lnk':
                    $abortar = true;
                    break;
            }
            if ($abortar){
                Alert::error('¡Error!', 'No esta permitida la carga de archivo con extensión .'.$request->file->getClientOriginalExtension());
                return back();
            }
        }

        // Validación
        $request->validate([
            'nfile' => 'max:250',
            'IDPersona' => 'required',
            'notas' => 'max:255',
            'IDCliente' => 'max:175',
        ]);

        // Asignando documento a usuario
        if($request->IDCliente){
            try{
                $user = User::where('passport','LIKE',$request->IDCliente)->first();
                $user_id = $user->id;
                $propietario = $user->name;
                $IDCliente = $user->passport;
            }catch(Exception $e){
                Alert::error('¡Error!', "El IDCliente $request->IDCliente no se encuentra en nuestra base de datos");
                return back();
            }
        }else{
            $user_id = Auth::id();
            $propietario = Auth::user()->name;
            $IDCliente = Auth::user()->passport;
        }

        // Ubicación del documento
        $location = $file->location;

        // Actualiza el archivo en el servidor y en la tabla files
        if($request->hasFile('file')){
            // Nombre del archivo
            if($request->nfile){
                $fileName = $request->nfile.".".$request->file->getClientOriginalExtension();
            } else {
                $fileName = $request->file->getClientOriginalName();
            }
            Storage::disk('s3')->delete($file->location . '/' . $file->file);
            $ruta = Storage::disk('s3')->putFileAs($location, $request->file, $fileName, 'public');
            /* if($request->file('file')->storePubliclyAs($location, $fileName)){ */
            if($ruta){
                // Agregando registro a la tabla files
                $file->file = $fileName;
                $file->location = $location;
                $file->tipo = $request->tipo;
                $file->propietario = $propietario;
                $file->IDCliente = $IDCliente;
                $file->notas = $request->notas;
                $file->IDPersona = $request->IDPersona;
                $file->user_id = $user_id;
                $file->save();

                // Mensaje
                Alert::success('¡Éxito!', 'Se ha añadido el documento: ' . $fileName);

                // Redireccionar a la vista index
                return redirect()->route('crud.files.index');
            }
        }else{
            $fileName = $file->file;
            if($file->file != $request->nfile){
                try {
                    $oldName = str_replace('public/','',$file->location.'/'.$file->file);
                    $extOldName = '.'.pathinfo($oldName, PATHINFO_EXTENSION);

                    $newName = str_replace('public/','',$file->location.'/'.$request->nfile);
                    $extNewName = '.'.pathinfo($newName, PATHINFO_EXTENSION);

                    if($extNewName == '.'){
                        $newName = str_replace('public/','',$file->location.'/'.$request->nfile).$extOldName;
                        $fileName = $request->nfile.$extOldName;
                    }else{
                        $newName = str_replace('public/','',$file->location.'/'.$request->nfile);
                        $fileName = $request->nfile;
                    }
                    // Storage::disk('public')->move($oldName, $newName);
                    Storage::disk('s3')->move($file->location . '/' . $file->file, $location . '/' . $request->nfile);
                } catch (Exception $e) {
                    Alert::error('¡Error!', 'No se pudo actualizar el archivo');
                    return back();
                }
            }
            // Agregando registro a la tabla files
            $file->file = $fileName;
            $file->location = $location;
            $file->tipo = $request->tipo;
            $file->propietario = $propietario;
            $file->IDCliente = $IDCliente;
            $file->notas = $request->notas;
            $file->IDPersona = $request->IDPersona;
            $file->user_id = $user_id;
            $file->save();

            // Mensaje
            Alert::success('¡Éxito!', 'Se ha añadido el documento: ' . $fileName);

            // Redireccionar a la vista index
            return redirect()->route('crud.files.index');
            /* Alert::error('¡Error!', 'No se pudo añadir el archivo');
            return back(); */
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\File  $file
     * @return \Illuminate\Http\Response
     */
    public function destroy(File $file)
    {
        try {
            $nombre = $file->file;

            // Borra el archivo del storage o almacenamiento
            /* $archivo = storage_path().'/app/'.$file->location.'/'.$file->file;
            unlink($archivo); */
            Storage::disk('s3')->delete($file->location . '/' . $file->file);

            $file->delete();

            Alert::info('¡Advertencia!', 'Se ha eliminado el archivo: ' . $nombre);

            /* return redirect()->route('crud.files.index'); */
            return back();
        } catch (Exception $e) {
            Alert::error('¡Error!', 'No se ha encontrado ningún archivo que eliminar');
            return back();
        }
    }
}
