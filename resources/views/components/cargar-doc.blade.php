@props(['agclientes', 'id'])

@php
    $pasaporte = $agclientes->where('IDPersona',1)->first()->IDCliente;
    $nombre = GetNombres($agclientes,$id) . ' ' . GetApellidos($agclientes,$id);
@endphp

<<<<<<< HEAD
<button onclick="document.getElementById('cargarDocumentos{{ $id }}').showModal()">
    <span class="cargar_doc ctrSefar" title="Cargar documentos"><i class="fas fa-upload"></i></span>
</button>

<dialog id="cargarDocumentos{{ $id }}" class="container w-11/12 mt-3 md:w-1/2 p-5 bg-white rounded-md">
=======
<button onclick="document.getElementById('cargarDocumentos{{ $id }}').showModal()"><span class="cargar_doc ctrSefar" title="Cargar documentos"><i class="fas fa-upload"></i></span></button>
<dialog id="cargarDocumentos{{ $id }}" class="container h-auto w-11/12 mt-3 md:w-1/2 p-5 bg-white rounded-md">
>>>>>>> parent of 6eef4a0 (Antes de la integración AWS S3 para documentos)
    <div class="flex flex-col w-full h-auto ">
        <!-- Título -->
        <div class="flex w-full h-auto justify-center items-center">
            <div class="flex w-10/12 h-auto py-3 justify-center items-center text-lg font-bold">
                Cargar documento {{ $nombre }}
            </div>
            <div onclick="document.getElementById('cargarDocumentos{{ $id }}').close();" class="flex w-1/12 h-auto justify-center cursor-pointer">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#000000" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-x"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
            </div>
        </div>
        <!-- Contenido-->
        <form action="{{ route('crud.files.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="shadow overflow-hidden sm:rounded-md">
                <div class="container">
                    {{-- Campos ocultos --}}
                    <input name="IDCliente" type="hidden" value="{{ $pasaporte }}"> {{-- IDCliente --}}
                    <input name="IDPersona" type="hidden" value="{{ $id }}"> {{-- IDPersona --}}
                    <input name="Origen" type="hidden" value="arbol"> {{-- Origen --}}
                    {{-- Fila 1: Documento --}}
                    <div class="md:flex ms:flex-wrap">
                        <div class="px-1 py-2 m-2 flex-1">    {{-- nfile --}}
                            <div>
                                <label for="nfile" class="block text-sm font-medium text-gray-700">Nombre del documento</label>
                                <input
                                    value="{{ old('nfile') }}"
                                    type="text" name="nfile"
                                    placeholder="Opcional (rellenar solo en caso de querer renombrar el documento)"
                                    class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"
                                >
                                @error('nfile')
                                    <small style="color:red">*{{ $message }}*</small>
                                @enderror
                            </div>
                        </div>
                    </div>
                    {{-- Fila 2: Archivo --}}
                    <div class="md:flex ms:flex-wrap">
                        <div class="px-1 py-2 m-2 flex-1">    {{-- file --}}
                            <div>
                                <input id="file{{ $id }}" type="file" name="file" style="display: none"
                                    accept="application/pdf, .doc, .docx, .odf, .xls, .xlsx, .ppt, .pptx, .txt,image/*"
                                />
                                <label for="file{{ $id }}" class="cfrSefar inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white">
                                    <i class="fas fa-upload mr-2"></i> archivo
                                </label>
                                @error('file')
                                    <small style="color:red">*{{ $message }}*</small>
                                @enderror
                            </div>
                        </div>
                    </div>
                    {{-- Fila 3: Notas --}}
                    <div class="md:flex ms:flex-wrap">
                        <div class="px-1 py-2 m-2 flex-1">    {{-- notas --}}
                            <div>
                                <label for="notas" class="block text-sm font-medium text-gray-700" title="Notas">Notas</label>
                                <textarea name="notas" rows="3" class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 mt-1 block w-full sm:text-sm border-gray-300 rounded-md" placeholder="Notas sobre el documento...">{{ old('notas') }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="px-4 py-3 bg-gray-50 text-right sm:px-6">
                    <button type="submit" class="cfrSefar inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        Añadir archivo
                    </button>
                </div>
            </div>
        </form>
    </div>
</dialog>


{{-- 62992756 --}}
