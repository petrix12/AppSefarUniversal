@props(['agclientes', 'id'])

@php
    $pasaporte = $agclientes->where('IDPersona',1)->first()->IDCliente;
    $nombre = GetNombres($agclientes,$id) . ' ' . GetApellidos($agclientes,$id);
    $persona = GetPersona($id);
    //$carpeta = 'doc/P'.$pasaporte.'/'.$persona.'/';
    /* $carpeta = 'public/doc/P'.$pasaporte.'/'.$persona.'/'; */
    /* if ( ! file_exists($carpeta)){
        $carpeta = 'doc/P0'.$pasaporte.'/Cliente/';
    }
    if ( ! file_exists($carpeta)){
        $carpeta = null;
    } */
    /* if ( ! Storage::disk('s3')->exists($carpeta)){
        $carpeta = null;
    } */
    /* if ( ! existeDoc($pasaporte, $id)) {
        $carpeta = null;
    } */
    $documentos = getDocumentos($pasaporte, $id);
@endphp

@if (existeDoc($pasaporte, $id))
    {{-- @dump($documentos[0]->file) --}}
    <button onclick="document.getElementById('verDocumentos{{ $id }}').showModal()">
        <span class="folder ctrSefar" title="Ver documentos"><i class="far fa-folder-open"></i></span>
    </button>
    <dialog id="verDocumentos{{ $id }}" class="container w-11/12 mt-3 md:w-1/2 p-5 bg-white rounded-md">
        <div class="flex flex-col w-full h-auto ">
            <!-- Título -->
            <div class="flex w-full h-auto justify-center items-center">
                <div class="flex w-10/12 h-auto py-3 justify-center items-center text-lg font-bold">
                    Documentos {{ $nombre }}
                </div>
                <div onclick="document.getElementById('verDocumentos{{ $id }}').close();" class="flex w-1/12 h-auto justify-center cursor-pointer">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#000000" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-x"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                </div>
            </div>
            <!-- Contenido-->
            <div class="w-full h-auto py-4 px-2 justify-center items-center bg-gray-200 rounded text-center text-gray-500">
                @foreach ($documentos as $documento )
                    <ul>
                        <li class="d-flex justify-content-between px-20">
                            {{-- <a href="{{ Storage::disk('s3')->url($documento->location . '/' . $documento->file) }}" target="_blank">{{ $documento->file }}</a> --}}
                            <a href="{{ route('crud.files.show', $documento) }}" target="_blank"><small>({{ $documento->tipo }}) - {{ $documento->file }}</small></a>
                            @can('crud.files.destroy')
                                <form action="{{ route('crud.files.destroy', $documento) }}" method="POST">
                                    @csrf
                                    @method('delete')
                                    <button
                                        type="submit"
                                        class="text-red-600 hover:text-red-900"
                                        onclick="return confirm('¿Está seguro que desea eliminar el archivo?')"><i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            @endcan
                        </li>
                    </ul>
                @endforeach
            </div>
            <div class="px-4 py-3 bg-gray-50 text-right sm:px-6">
                <button onclick="document.getElementById('verDocumentos{{ $id }}').close();" class="cfrSefar inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Listo
                </button>
            </div>
        </div>
    </dialog>
@endif
