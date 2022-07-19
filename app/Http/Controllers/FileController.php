<?php

namespace App\Http\Controllers;

use App\Models\File;
use Exception;
use App\Models\TFile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use RealRashid\SweetAlert\Facades\Alert;
use Illuminate\Support\Facades\Storage;

class FileController extends Controller
{
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
        $max_size = (int)ini_get('upload_max_filesize') * 10240 * 20;
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
        $anho = date('Y');
        if($request->Origen == "arbol"){
            //$carpeta = 'doc/P'.$pasaporte.'/'.$persona.'/';
            /* $location = str_replace('.','','public/doc/P'.$IDCliente.'/'.GetPersona($request->IDPersona)); */
            $location = 'public/doc/P'.$IDCliente.'/'.GetPersona($request->IDPersona);
        }else{
            $location = str_replace('.','','public/documentos/'.$anho.'/'.$user_id.'/'.GetPersona($request->IDPersona));
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
            if($request->file('file')->storePubliclyAs($location, $fileName)){
                // Agregando registro a la tabla files
                File::create([
                    'file' => $fileName,
                    'location' => $location,
                    'tipo' => $request->tipo,
                    'propietario' => $propietario,
                    'IDCliente' => $IDCliente,
                    'notas' => $request->notas,
                    'IDPersona' => $request->IDPersona,
                    'user_id' => $user_id
                ]);

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
        $pathtoFile = storage_path().'/app/'.$file->location.'/'.$file->file;
        return response()->file($pathtoFile);
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
            $IDCliente = $user->passport;
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
            if($request->file('file')->storePubliclyAs($location, $fileName)){
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
                    Storage::disk('public')->move($oldName, $newName);
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
            $archivo = storage_path().'/app/'.$file->location.'/'.$file->file;
            unlink($archivo);

            $file->delete();

            Alert::info('¡Advertencia!', 'Se ha eliminado el archivo: ' . $nombre);

            return redirect()->route('crud.files.index');
        } catch (Exception $e) {
            Alert::error('¡Error!', 'No se ha encontrado ningún archivo que eliminar');
            return back();
        }
    }
}
