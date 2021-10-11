@props(['agclientes', 'countries', 'id'])

@php
    try {
        $editar = $agclientes->where('IDPersona',$id)->first()->IDCliente;
    } catch (\Throwable $e) {
        $editar = null;
    }
    $agcliente = GetID($agclientes,$id);
    $IDCliente = $agclientes->where('IDPersona',1)->first()->IDCliente;
    $nombre = GetNombres($agclientes,$id) . ' ' . GetApellidos($agclientes,$id);
    $Sexo = GetSexo($agclientes,$id);
    $Familiares = GetFamiliares($agclientes,$id);

    $Nombres = GetNombres($agclientes,$id);
    $Apellidos = GetApellidos($agclientes,$id);
    $Observaciones = GetObservaciones($agclientes,$id);
    $persona = GetPersona($id);

    $AnhoNac = GetAnhoNac($agclientes,$id);
    $MesNac = GetMesNac($agclientes,$id);
    $DiaNac = GetDiaNac($agclientes,$id);
    $LugarNac = GetLugarNac($agclientes,$id);
    $PaisNac = GetPaisNac($agclientes,$id);

    $AnhoBtzo = GetAnhoBtzo($agclientes,$id);
    $MesBtzo = GetMesBtzo($agclientes,$id);
    $DiaBtzo = GetDiaBtzo($agclientes,$id);
    $LugarBtzo = GetLugarBtzo($agclientes,$id);
    $PaisBtzo = GetPaisBtzo($agclientes,$id);
    
    $AnhoMatr = GetAnhoMatr($agclientes,$id);
    $MesMatr = GetMesMatr($agclientes,$id);
    $DiaMatr = GetDiaMatr($agclientes,$id);
    $LugarMatr = GetLugarMatr($agclientes,$id);
    $PaisMatr = GetPaisMatr($agclientes,$id);

    $AnhoDef = GetAnhoDef($agclientes,$id);
    $MesDef = GetMesDef($agclientes,$id);
    $DiaDef = GetDiaDef($agclientes,$id);
    $LugarDef = GetLugarDef($agclientes,$id);
    $PaisDef = GetPaisDef($agclientes,$id);
@endphp


<button onclick="document.getElementById('editarPersona{{ $id }}').showModal()">
    <span title="Editar persona">
        @if ($editar)
            <i class="fas fa-user-edit text-red-500 hover:text-red-200"></i>
        @else
            <i class="fas fa-user-plus text-gray-500 hover:text-blue-500"></i>
        @endif
    </span>
</button>

<dialog id="editarPersona{{ $id }}" class="container h-auto w-11/12 mt-3 md:w-2/3 p-5 bg-white rounded-md">    
    <div class="flex flex-col w-full h-auto text-left">
        <!-- Título -->
        <div class="flex w-full h-auto justify-center items-center">
            <div class="flex w-10/12 h-auto py-3 justify-center items-center text-lg font-bold">
                @if ($editar)
                    Editar: {{ $nombre }}    
                @else
                    Añadir: {{ $persona }}
                @endif
            </div>
            <div onclick="document.getElementById('editarPersona{{ $id }}').close();" class="flex w-1/12 h-auto justify-center cursor-pointer">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#000000" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-x"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
            </div>
        </div>
        <!-- Contenido-->
        <div {{-- class="flex w-full h-auto py-4 px-2 justify-center items-center bg-gray-200 rounded text-center text-gray-500" --}}>
            @if ($editar)
                {{-- Edición --}}
                <form action="{{ route('crud.agclientes.update', $agcliente) }}" method="POST">
                    @csrf
                    @method('put')
                    <div class="shadow overflow-hidden sm:rounded-md">
                        {{-- Campos ocultos --}}
                        <input name="Origen" type="hidden" value="arbol"> {{-- Origen --}}
                        <input name="IDCliente" type="hidden" value="{{ $IDCliente }}"> {{-- IDCliente --}}
                        <input name="IDPersona" type="hidden" value="{{ $id }}"> {{-- IDPersona --}}
                        <input name="Sexo" type="hidden" value="{{ $Sexo }}"> {{-- Sexo --}}

                        <div class="container">
                            {{-- Fila 1 --}}
                            <div class="md:flex ms:flex-wrap">
                                <div class="px-1 py-2 m-2 flex-1">    {{-- Nombres --}}
                                    <div>
                                        <label for="Nombres" class="block text-sm font-medium text-gray-700">Nombres</label>
                                        <input value="{{ old('Nombres', $Nombres) }}" type="text" name="Nombres" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                        @error('Nombres')
                                            <small style="color:red">*{{ $message }}*</small>
                                        @enderror
                                    </div>
                                </div>

                                <div class="px-1 py-2 m-2 flex-1">    {{-- Apellidos --}}
                                    <div>
                                        <label for="Apellidos" class="block text-sm font-medium text-gray-700">Apellidos</label>
                                        <input value="{{ old('Apellidos', $Apellidos) }}" type="text" name="Apellidos" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                        @error('Apellidos')
                                            <small style="color:red">*{{ $message }}*</small>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                            {{-- Fila 2 --}}
                            <div class="md:flex ms:flex-wrap">
                                <div class="px-1 py-2 m-2 flex-1/2">    {{-- AnhoNac --}}
                                    <div>
                                        <label for="AnhoNac" class="block text-sm font-medium text-gray-700" title="Año de nacimiento">Año Nac.</label>
                                        <input value="{{ old('AnhoNac', $AnhoNac) }}" min="0" max="3000" type="number" name="AnhoNac" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                        @error('AnhoNac')
                                            <small style="color:red">*{{ $message }}*</small>
                                        @enderror
                                    </div>
                                </div>
                                <div class="px-1 py-2 m-2 flex-1/2">    {{-- MesNac --}}
                                    <div>
                                        <label for="MesNac" class="block text-sm font-medium text-gray-700" title="Mes de nacimiento">Mes Nac.</label>
                                        <input value="{{ old('MesNac', $MesNac) }}" min="1" max="12" type="number" name="MesNac" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                        @error('MesNac')
                                            <small style="color:red">*{{ $message }}*</small>
                                        @enderror
                                    </div>
                                </div>
                                <div class="px-1 py-2 m-2 flex-1/2">    {{-- DiaNac --}}
                                    <div>
                                        <label for="DiaNac" class="block text-sm font-medium text-gray-700" title="Día de nacimiento">Día Nac.</label>
                                        <input value="{{ old('DiaNac', $DiaNac) }}" min="1" max="31" type="number" name="DiaNac" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                        @error('DiaNac')
                                            <small style="color:red">*{{ $message }}*</small>
                                        @enderror
                                    </div>
                                </div>
                                <div class="px-1 py-2 m-2 flex-1">    {{-- LugarNac --}}
                                    <div>
                                        <label for="LugarNac" class="block text-sm font-medium text-gray-700" title="Lugar de nacimiento">Lugar Nac.</label>
                                        <input value="{{ old('LugarNac', $LugarNac) }}" type="text" name="LugarNac" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                    </div>
                                </div>
                                <div class="px-1 py-2 m-2 flex-1">    {{-- PaisNac --}}
                                    <div>
                                        <label for="PaisNac" class="block text-sm font-medium text-gray-700" title="País de nacimiento">País Nac.</label>
                                        <select name="PaisNac" autocomplete="country" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                            <option></option>
                                            @foreach ($countries as $country)
                                                @if (old('PaisNac', $PaisNac) == $country->pais)
                                                    <option selected>{{ $country->pais }}</option>
                                                @else
                                                    <option>{{ $country->pais }}</option> 
                                                @endif
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>

                            {{-- Fila 3 --}}
                            <div class="md:flex ms:flex-wrap">
                                <div class="px-1 py-2 m-2 flex-1/2">    {{-- AnhBtzo --}}
                                    <div>
                                        <label for="AnhoBtzo" class="block text-sm font-medium text-gray-700" title="Año de bautizo">Año Btzo.</label>
                                        <input value="{{ old('AnhoBtzo', $AnhoBtzo) }}" min="0" max="3000" type="number" name="AnhoBtzo" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                        @error('AnhoBtzo')
                                            <small style="color:red">*{{ $message }}*</small>
                                        @enderror
                                    </div>
                                </div>
                                <div class="px-1 py-2 m-2 flex-1/2">    {{-- MesBtzo --}}
                                    <div>
                                        <label for="MesBtzo" class="block text-sm font-medium text-gray-700" title="Mes de bautizo">Mes Btzo.</label>
                                        <input value="{{ old('MesBtzo', $MesBtzo) }}" min="1" max="12" type="number" name="MesBtzo" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                        @error('MesBtzo')
                                            <small style="color:red">*{{ $message }}*</small>
                                        @enderror
                                    </div>
                                </div>
                                <div class="px-1 py-2 m-2 flex-1/2">    {{-- DiaBtzo --}}
                                    <div>
                                        <label for="DiaBtzo" class="block text-sm font-medium text-gray-700" title="Día de bautizo">Día Btzo.</label>
                                        <input value="{{ old('DiaBtzo', $DiaBtzo) }}" min="1" max="31" type="number" name="DiaBtzo" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                        @error('DiaBtzo')
                                            <small style="color:red">*{{ $message }}*</small>
                                        @enderror
                                    </div>
                                </div>
                                <div class="px-1 py-2 m-2 flex-1">    {{-- LugarBtzo --}}
                                    <div>
                                        <label for="LugarBtzo" class="block text-sm font-medium text-gray-700" title="Lugar de bautizo">Lugar Btzo.</label>
                                        <input value="{{ old('LugarBtzo', $LugarBtzo) }}" type="text" name="LugarBtzo" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                    </div>
                                </div>
                                <div class="px-1 py-2 m-2 flex-1">    {{-- PaisBtzo --}}
                                    <div>
                                        <label for="PaisBtzo" class="block text-sm font-medium text-gray-700" title="País de bautizo">País Btzo.</label>
                                        <select name="PaisBtzo" autocomplete="country" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                            <option></option>
                                            @foreach ($countries as $country)
                                                @if (old('PaisBtzo', $PaisBtzo) == $country->pais)
                                                    <option selected>{{ $country->pais }}</option>
                                                @else
                                                    <option>{{ $country->pais }}</option> 
                                                @endif
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            {{-- Fila 4 --}}
                            <div class="md:flex ms:flex-wrap">
                                <div class="px-1 py-2 m-2 flex-1/2">    {{-- AnhoMatr --}}
                                    <div>
                                        <label for="AnhoMatr" class="block text-sm font-medium text-gray-700" title="Año de matrimonio">Año Matr.</label>
                                        <input value="{{ old('AnhoMatr', $AnhoMatr) }}" min="0" max="3000" type="number" name="AnhoMatr" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                        @error('AnhoMatr')
                                            <small style="color:red">*{{ $message }}*</small>
                                        @enderror
                                    </div>
                                </div>
                                <div class="px-1 py-2 m-2 flex-1/2">    {{-- MesMatr --}}
                                    <div>
                                        <label for="MesMatr" class="block text-sm font-medium text-gray-700" title="Mes de matrimonio">Mes Matr.</label>
                                        <input value="{{ old('MesMatr', $MesMatr) }}" min="1" max="12" type="number" name="MesMatr" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                        @error('MesMatr')
                                            <small style="color:red">*{{ $message }}*</small>
                                        @enderror
                                    </div>
                                </div>
                                <div class="px-1 py-2 m-2 flex-1/2">    {{-- DiaMatr --}}
                                    <div>
                                        <label for="DiaMatr" class="block text-sm font-medium text-gray-700" title="Día de matrimonio">Día Matr.</label>
                                        <input value="{{ old('DiaMatr', $DiaMatr) }}" min="1" max="31" type="number" name="DiaMatr" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                        @error('DiaMatr')
                                            <small style="color:red">*{{ $message }}*</small>
                                        @enderror
                                    </div>
                                </div>
                                <div class="px-1 py-2 m-2 flex-1">    {{-- LugarMatr --}}
                                    <div>
                                        <label for="LugarMatr" class="block text-sm font-medium text-gray-700" title="Lugar de matrimonio">Lugar Matr.</label>
                                        <input value="{{ old('LugarMatr', $LugarMatr) }}" type="text" name="LugarMatr" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                    </div>
                                </div>
                                <div class="px-1 py-2 m-2 flex-1">    {{-- PaisMatr --}}
                                    <div>
                                        <label for="PaisMatr" class="block text-sm font-medium text-gray-700" title="País de matrimonio">País Matr.</label>
                                        <select name="PaisMatr" autocomplete="country" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                            <option></option>
                                            @foreach ($countries as $country)
                                                @if (old('PaisMatr', $PaisMatr) == $country->pais)
                                                    <option selected>{{ $country->pais }}</option>
                                                @else
                                                    <option>{{ $country->pais }}</option> 
                                                @endif
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            {{-- Fila 5 --}}
                            <div class="md:flex ms:flex-wrap">
                                <div class="px-1 py-2 m-2 flex-1/2">    {{-- AnhoDef --}}
                                    <div>
                                        <label for="AnhoDef" class="block text-sm font-medium text-gray-700" title="Año de defunción">Año Def.</label>
                                        <input value="{{ old('AnhoDef', $AnhoDef) }}" min="0" max="3000" type="number" name="AnhoDef" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                        @error('AnhoDef')
                                            <small style="color:red">*{{ $message }}*</small>
                                        @enderror
                                    </div>
                                </div>
                                <div class="px-1 py-2 m-2 flex-1/2">    {{-- MesMatr --}}
                                    <div>
                                        <label for="MesDef" class="block text-sm font-medium text-gray-700" title="Mes de defunción">Mes Def.</label>
                                        <input value="{{ old('MesDef', $MesDef) }}" min="1" max="12" type="number" name="MesDef" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                        @error('MesDef')
                                            <small style="color:red">*{{ $message }}*</small>
                                        @enderror
                                    </div>
                                </div>
                                <div class="px-1 py-2 m-2 flex-1/2">    {{-- DiaDef --}}
                                    <div>
                                        <label for="DiaDef" class="block text-sm font-medium text-gray-700" title="Día de defunción">Día Def.</label>
                                        <input value="{{ old('DiaDef', $DiaDef) }}" min="1" max="31" type="number" name="DiaDef" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                        @error('DiaDef')
                                            <small style="color:red">*{{ $message }}*</small>
                                        @enderror
                                    </div>
                                </div>
                                <div class="px-1 py-2 m-2 flex-1">    {{-- LugarDef --}}
                                    <div>
                                        <label for="LugarDef" class="block text-sm font-medium text-gray-700" title="Lugar de defunción">Lugar Def.</label>
                                        <input value="{{ old('LugarDef', $LugarDef) }}" type="text" name="LugarDef" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                    </div>
                                </div>
                                <div class="px-1 py-2 m-2 flex-1">    {{-- PaisDef --}}
                                    <div>
                                        <label for="PaisDef" class="block text-sm font-medium text-gray-700" title="País de defunción">País Def.</label>
                                        <select name="PaisDef" autocomplete="country" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                            <option></option>
                                            @foreach ($countries as $country)
                                                @if (old('PaisDef', $PaisDef) == $country->pais)
                                                    <option selected>{{ $country->pais }}</option>
                                                @else
                                                    <option>{{ $country->pais }}</option> 
                                                @endif
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>

                            {{-- Fila 6 --}}
                            <div class="md:flex ms:flex-wrap">
                                <div class="px-1 py-2 m-2 flex-1">    {{-- Observaciones --}}
                                    <div>
                                        <label for="Observaciones" class="block text-sm font-medium text-gray-700" title="Observaciones">Observaciones</label>
                                        <textarea name="Observaciones" rows="3" class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 mt-1 block w-full sm:text-sm border-gray-300 rounded-md" placeholder="Observaciones">{{ old('Observaciones', $Observaciones) }}</textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="px-4 py-3 bg-gray-50 text-right sm:px-6">
                            <button type="submit" class="cfrSefar inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                Actualizar persona
                            </button>
                        </div>
                    </div> 
                </form>
            @else
                {{-- Creación --}}
                <form action="{{ route('crud.agclientes.store') }}" method="POST">
                    @csrf
                    <div class="shadow overflow-hidden sm:rounded-md">
                        {{-- Campos ocultos --}}
                        <input name="Origen" type="hidden" value="arbol"> {{-- Origen --}}
                        <input name="IDCliente" type="hidden" value="{{ $IDCliente }}"> {{-- IDCliente --}}
                        <input name="IDPersona" type="hidden" value="{{ $id }}"> {{-- IDPersona --}}
                        <input name="Sexo" type="hidden" value="{{ $Sexo }}"> {{-- Sexo --}}
                        
                        <div class="container">
                            {{-- Fila 1 --}}
                            <div class="md:flex ms:flex-wrap">
                                <div class="px-1 py-2 m-2 flex-1">    {{-- Nombres --}}
                                    <div>
                                        <label for="Nombres" class="block text-sm font-medium text-gray-700">Nombres</label>
                                        <input value="{{ old('Nombres') }}" type="text" name="Nombres" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                        @error('Nombres')
                                            <small style="color:red">*{{ $message }}*</small>
                                        @enderror
                                    </div>
                                </div>

                                <div class="px-1 py-2 m-2 flex-1">    {{-- Apellidos --}}
                                    <div>
                                        <label for="Apellidos" class="block text-sm font-medium text-gray-700">Apellidos</label>
                                        <input value="{{ old('Apellidos') }}" type="text" name="Apellidos" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                        @error('Apellidos')
                                            <small style="color:red">*{{ $message }}*</small>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                            {{-- Fila 2 --}}
                            <div class="md:flex ms:flex-wrap">
                                <div class="px-1 py-2 m-2 flex-1/2">    {{-- AnhoNac --}}
                                    <div>
                                        <label for="AnhoNac" class="block text-sm font-medium text-gray-700" title="Año de nacimiento">Año Nac.</label>
                                        <input value="{{ old('AnhoNac') }}" min="0" max="3000" type="number" name="AnhoNac" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                        @error('AnhoNac')
                                            <small style="color:red">*{{ $message }}*</small>
                                        @enderror
                                    </div>
                                </div>
                                <div class="px-1 py-2 m-2 flex-1/2">    {{-- MesNac --}}
                                    <div>
                                        <label for="MesNac" class="block text-sm font-medium text-gray-700" title="Mes de nacimiento">Mes Nac.</label>
                                        <input value="{{ old('MesNac') }}" min="1" max="12" type="number" name="MesNac" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                        @error('MesNac')
                                            <small style="color:red">*{{ $message }}*</small>
                                        @enderror
                                    </div>
                                </div>
                                <div class="px-1 py-2 m-2 flex-1/2">    {{-- DiaNac --}}
                                    <div>
                                        <label for="DiaNac" class="block text-sm font-medium text-gray-700" title="Día de nacimiento">Día Nac.</label>
                                        <input value="{{ old('DiaNac') }}" min="1" max="31" type="number" name="DiaNac" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                        @error('DiaNac')
                                            <small style="color:red">*{{ $message }}*</small>
                                        @enderror
                                    </div>
                                </div>
                                <div class="px-1 py-2 m-2 flex-1">    {{-- LugarNac --}}
                                    <div>
                                        <label for="LugarNac" class="block text-sm font-medium text-gray-700" title="Lugar de nacimiento">Lugar Nac.</label>
                                        <input value="{{ old('LugarNac') }}" type="text" name="LugarNac" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                    </div>
                                </div>
                                <div class="px-1 py-2 m-2 flex-1">    {{-- PaisNac --}}
                                    <div>
                                        <label for="PaisNac" class="block text-sm font-medium text-gray-700" title="País de nacimiento">País Nac.</label>
                                        <select name="PaisNac" autocomplete="country" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                            <option></option>
                                            @foreach ($countries as $country)
                                                @if (old('PaisNac') == $country->pais)
                                                    <option selected>{{ $country->pais }}</option>
                                                @else
                                                    <option>{{ $country->pais }}</option> 
                                                @endif
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>

                            {{-- Fila 3 --}}
                            <div class="md:flex ms:flex-wrap">
                                <div class="px-1 py-2 m-2 flex-1/2">    {{-- AnhoBtzo --}}
                                    <div>
                                        <label for="AnhoBtzo" class="block text-sm font-medium text-gray-700" title="Año de bautizo">Año Btzo.</label>
                                        <input value="{{ old('AnhoBtzo') }}" min="0" max="3000" type="number" name="AnhoBtzo" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                        @error('AnhoBtzo')
                                            <small style="color:red">*{{ $message }}*</small>
                                        @enderror
                                    </div>
                                </div>
                                <div class="px-1 py-2 m-2 flex-1/2">    {{-- MesBtzo --}}
                                    <div>
                                        <label for="MesBtzo" class="block text-sm font-medium text-gray-700" title="Mes de bautizo">Mes Btzo.</label>
                                        <input value="{{ old('MesBtzo') }}" min="1" max="12" type="number" name="MesBtzo" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                        @error('MesBtzo')
                                            <small style="color:red">*{{ $message }}*</small>
                                        @enderror
                                    </div>
                                </div>
                                <div class="px-1 py-2 m-2 flex-1/2">    {{-- DiaBtzo --}}
                                    <div>
                                        <label for="DiaBtzo" class="block text-sm font-medium text-gray-700" title="Día de bautizo">Día Btzo.</label>
                                        <input value="{{ old('DiaBtzo') }}" min="1" max="31" type="number" name="DiaBtzo" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                        @error('DiaBtzo')
                                            <small style="color:red">*{{ $message }}*</small>
                                        @enderror
                                    </div>
                                </div>
                                <div class="px-1 py-2 m-2 flex-1">    {{-- LugarBtzo --}}
                                    <div>
                                        <label for="LugarBtzo" class="block text-sm font-medium text-gray-700" title="Lugar de bautizo">Lugar Btzo.</label>
                                        <input value="{{ old('LugarBtzo') }}" type="text" name="LugarBtzo" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                    </div>
                                </div>
                                <div class="px-1 py-2 m-2 flex-1">    {{-- PaisBtzo --}}
                                    <div>
                                        <label for="PaisBtzo" class="block text-sm font-medium text-gray-700" title="País de bautizo">País Btzo.</label>
                                        <select name="PaisBtzo" autocomplete="country" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                            <option></option>
                                            @foreach ($countries as $country)
                                                @if (old('PaisBtzo') == $country->pais)
                                                    <option selected>{{ $country->pais }}</option>
                                                @else
                                                    <option>{{ $country->pais }}</option> 
                                                @endif
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            {{-- Fila 4 --}}
                            <div class="md:flex ms:flex-wrap">
                                <div class="px-1 py-2 m-2 flex-1/2">    {{-- AnhoMatr --}}
                                    <div>
                                        <label for="AnhoMatr" class="block text-sm font-medium text-gray-700" title="Año de matrimonio">Año Matr.</label>
                                        <input value="{{ old('AnhoMatr') }}" min="0" max="3000" type="number" name="AnhoMatr" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                        @error('AnhoMatr')
                                            <small style="color:red">*{{ $message }}*</small>
                                        @enderror
                                    </div>
                                </div>
                                <div class="px-1 py-2 m-2 flex-1/2">    {{-- MesMatr --}}
                                    <div>
                                        <label for="MesMatr" class="block text-sm font-medium text-gray-700" title="Mes de matrimonio">Mes Matr.</label>
                                        <input value="{{ old('MesMatr') }}" min="1" max="12" type="number" name="MesMatr" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                        @error('MesMatr')
                                            <small style="color:red">*{{ $message }}*</small>
                                        @enderror
                                    </div>
                                </div>
                                <div class="px-1 py-2 m-2 flex-1/2">    {{-- DiaMatr --}}
                                    <div>
                                        <label for="DiaMatr" class="block text-sm font-medium text-gray-700" title="Día de matrimonio">Día Matr.</label>
                                        <input value="{{ old('DiaMatr') }}" min="1" max="31" type="number" name="DiaMatr" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                        @error('DiaMatr')
                                            <small style="color:red">*{{ $message }}*</small>
                                        @enderror
                                    </div>
                                </div>
                                <div class="px-1 py-2 m-2 flex-1">    {{-- LugarMatr --}}
                                    <div>
                                        <label for="LugarMatr" class="block text-sm font-medium text-gray-700" title="Lugar de matrimonio">Lugar Matr.</label>
                                        <input value="{{ old('LugarMatr') }}" type="text" name="LugarMatr" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                    </div>
                                </div>
                                <div class="px-1 py-2 m-2 flex-1">    {{-- PaisMatr --}}
                                    <div>
                                        <label for="PaisMatr" class="block text-sm font-medium text-gray-700" title="País de matrimonio">País Matr.</label>
                                        <select name="PaisMatr" autocomplete="country" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                            <option></option>
                                            @foreach ($countries as $country)
                                                @if (old('PaisMatr') == $country->pais)
                                                    <option selected>{{ $country->pais }}</option>
                                                @else
                                                    <option>{{ $country->pais }}</option> 
                                                @endif
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>
 
                            {{-- Fila 5 --}}
                            <div class="md:flex ms:flex-wrap">
                                <div class="px-1 py-2 m-2 flex-1/2">    {{-- AnhoDef --}}
                                    <div>
                                        <label for="AnhoDef" class="block text-sm font-medium text-gray-700" title="Año de defunción">Año Def.</label>
                                        <input value="{{ old('AnhoDef') }}" min="0" max="3000" type="number" name="AnhoDef" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                        @error('AnhoDef')
                                            <small style="color:red">*{{ $message }}*</small>
                                        @enderror
                                    </div>
                                </div>
                                <div class="px-1 py-2 m-2 flex-1/2">    {{-- MesDef --}}
                                    <div>
                                        <label for="MesDef" class="block text-sm font-medium text-gray-700" title="Mes de defunción">Mes Def.</label>
                                        <input value="{{ old('MesDef') }}" min="1" max="12" type="number" name="MesDef" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                        @error('MesDef')
                                            <small style="color:red">*{{ $message }}*</small>
                                        @enderror
                                    </div>
                                </div>
                                <div class="px-1 py-2 m-2 flex-1/2">    {{-- DiaDef --}}
                                    <div>
                                        <label for="DiaDef" class="block text-sm font-medium text-gray-700" title="Día de defunción">Día Def.</label>
                                        <input value="{{ old('DiaDef') }}" min="1" max="31" type="number" name="DiaDef" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                        @error('DiaDef')
                                            <small style="color:red">*{{ $message }}*</small>
                                        @enderror
                                    </div>
                                </div>
                                <div class="px-1 py-2 m-2 flex-1">    {{-- LugarDef --}}
                                    <div>
                                        <label for="LugarDef" class="block text-sm font-medium text-gray-700" title="Lugar de defunción">Lugar Def.</label>
                                        <input value="{{ old('LugarDef') }}" type="text" name="LugarDef" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                    </div>
                                </div>
                                <div class="px-1 py-2 m-2 flex-1">    {{-- PaisDef --}}
                                    <div>
                                        <label for="PaisDef" class="block text-sm font-medium text-gray-700" title="País de defunción">País Def.</label>
                                        <select name="PaisDef" autocomplete="country" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                            <option></option>
                                            @foreach ($countries as $country)
                                                @if (old('PaisDef') == $country->pais)
                                                    <option selected>{{ $country->pais }}</option>
                                                @else
                                                    <option>{{ $country->pais }}</option> 
                                                @endif
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>

                            {{-- Fila 6 --}}
                            <div class="md:flex ms:flex-wrap">
                                <div class="px-1 py-2 m-2 flex-1">    {{-- Observaciones --}}
                                    <div>
                                        <label for="Observaciones" class="block text-sm font-medium text-gray-700" title="Observaciones">Observaciones</label>
                                        <textarea name="Observaciones" rows="3" class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 mt-1 block w-full sm:text-sm border-gray-300 rounded-md" placeholder="Observaciones">{{ old('Observaciones') }}</textarea>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="px-4 py-3 bg-gray-50 text-right sm:px-6">
                            <button type="submit" class="cfrSefar inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                Añadir persona
                            </button>
                        </div>
                    </div> 
                </form>
            @endif
        </div>
        <div class="px-4 py-3 bg-gray-50 text-right sm:px-6">
            <button onclick="document.getElementById('editarPersona{{ $id }}').close();" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-gray-600 hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                {{-- <i class="fas fa-save"> Actualizar</i> --}}
                Cancelar
            </button>
        </div>
    </div>
</dialog>