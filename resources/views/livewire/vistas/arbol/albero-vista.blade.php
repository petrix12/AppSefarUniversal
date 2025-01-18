<div>
    <div class="p-2 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="lg:text-center">
            <h2 class="text-sm font-bold ctvSefar cfaSefar tracking-wide pt-2 rounded-lg opacity-75 flex h-8 justify-center items-center">
                {{-- Cliente: {{ $agclientes[0]->Nombres.', '.$agclientes[0]->Apellidos.' / '.$agclientes[0]->IDCliente}} --}}
                Cliente: {{ GetNombres($agclientes,1) .' '. GetApellidos($agclientes,1).' / '.$agclientes[0]->IDCliente }}
                {{ getServicio($agclientes[0]->IDCliente) ? ' / Servicio: ' . getServicio($agclientes[0]->IDCliente) : '' }}
            </h2>
            <p class="mt-2 text-lg leading-8 font-extrabold tracking-tight text-gray-900 sm:text-4xl">
                {{-- ALBERO GENEALOGICO PER LE RICOSTRUZIONI DI CITTADINANZA --}}
                ÁRBOL GENEALÓGICO PARA EL ESTUDIO DE OBTENCIÓN DE NACIONALIDAD
            </p>
        </div>
    </div>
    <div class="container overflow-x-scroll">
        <div class="flex justify-between">
            <div class="px-4 py-2 m-2">
                {{-- LÍNEA GENEALÓGICA --}}
                <div class="text-left">
                    <label for="LineaGenealogica" class="px-3 block text-sm font-medium text-gray-700" title="Indicar línea genealógica">Línea Genealógica</label>
                    <select wire:model.live="LineaGenealogica" name="LineaGenealogica" autocomplete="on" class="w-44 mt-1 block py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        <option value="16">Tatarabuelos PPP</option>
                        <option value="18">Tatarabuelos PPM</option>
                        <option value="20">Tatarabuelos PMP</option>
                        <option value="22">Tatarabuelos PMM</option>
                        <option value="24">Tatarabuelos MPP</option>
                        <option value="26">Tatarabuelos MPM</option>
                        <option value="28">Tatarabuelos MMP</option>
                        <option value="30">Tatarabuelos MMM</option>
                    </select>
                </div>
            </div>
            <div class="px-4 py-2 m-2">
                {{-- FAMILIARES --}}
                <div class="justify-center">
                    <label for="Familiares" class="px-3 block text-sm font-medium text-gray-700" title="Familiares en el proceso">Familiares</label>
                    <select wire:model.live="IDFamiliar" style="width:450px" name="Familiares" class="w-44 mt-1 block py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        <option value="{{ null }}">-</option>
                        @foreach ($families as $family)
                            <option value="{{ $family->IDFamiliar }}">{{ $family->Familiar.' - '.$family->Parentesco }}</option>
                        @endforeach
                    </select>
                    @if($IDFamiliar)
                    <div class="pt-2">
                        <div class="px-4 py-3 bg-gray-50 text-right sm:px-6">
                            <a href="{{ route('arboles.albero.index', $IDFamiliar) }}" target="_blank" class="cfrSefar inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                Ir a familiar seleccionado
                            </a>
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div style="height:70rem" class="container relative overflow-x-scroll">
        {{-- TATARABUELO --}}
        <div class="caja_persona" style="top: 20px; left: 50px;">
            @php
                $IDTatarabuelo = $LineaGenealogica;
                $idT = GetID($agclientes,$IDTatarabuelo);
            @endphp
            @if ($idT)
                <h1 class="text-center font-bold text-sm pt-1 ctvSefar" title="Editar">
                    <a href="{{ route('crud.agclientes.edit', $idT ) }}">{{ GetPersona($IDTatarabuelo) }}</a>
                </h1>
            @else
                <h1 class="text-center font-bold text-sm pt-1 text-red" title="Añadir">
                    <a href="{{ route('crud.agclientes.create') }}">{{ GetPersona($IDTatarabuelo) }}</a>
                </h1>
            @endif

            <p class="text-center text-sm ctrSefar">{{ GetNombres($agclientes,$IDTatarabuelo) }}</p>
            <p class="text-center text-sm ctrSefar">{{ GetApellidos($agclientes,$IDTatarabuelo) }}</p>
            @if ($idT)
                <p class="text-center text-xs">Lugar de nacimiento:</p>
            @endif
            <p class="text-center text-xs">{{ GetLugarNac($agclientes,$IDTatarabuelo) }}</p>
            <p class="text-center text-xs" title="{{ GetVidaCompleta($agclientes,$IDTatarabuelo) }}">{{ GetVida($agclientes,$IDTatarabuelo) }}</p>

            {{-- <span class="editar"><x-editar-persona :agclientes='$agclientes' :countries='$countries' :id='$IDTatarabuelo'/></span> --}}
            <span class="editar"><x-editar-persona-i-v2 :agclientes='$agclientes' :countries='$countries' :id='$IDTatarabuelo'/></span>
            <x-cargar-doc :agclientes='$agclientes' :id='$IDTatarabuelo'/>
            <x-ver-doc :agclientes='$agclientes' :id='$IDTatarabuelo'/>
        </div>

        {{-- TATARABUELA --}}
        <div class="caja_persona" style="top: 20px; left: 670px;">
            @php
                $pasoConyugueT = 1;
                $IDTatarabuela = $IDTatarabuelo + $pasoConyugueT;
                $idTa = GetID($agclientes,$IDTatarabuela);
            @endphp

            @if ($idTa)
                <h1 class="text-center font-bold text-sm pt-1 ctvSefar" title="Editar">
                    <a href="{{ route('crud.agclientes.edit', $idTa ) }}">{{ GetPersona($IDTatarabuela) }}</a>
                </h1>
            @else
            <h1 class="text-center font-bold text-sm pt-1 text-red" title="Añadir">
                <h1 class="text-center font-bold text-sm pt-1 text-red" title="Añadir">
                    <a href="{{ route('crud.agclientes.create') }}">{{ GetPersona($IDTatarabuela) }}</a>
                </h1>
            </h1>
            @endif

            <p class="text-center text-sm ctrSefar">{{ GetNombres($agclientes,$IDTatarabuela) }}</p>
            <p class="text-center text-sm ctrSefar">{{ GetApellidos($agclientes,$IDTatarabuela) }}</p>
            @if ($idTa)
                <p class="text-center text-xs">Lugar de nacimiento:</p>
            @endif
            <p class="text-center text-xs">{{ GetLugarNac($agclientes,$IDTatarabuela) }}</p>
            <p class="text-center text-xs" title="{{ GetVidaCompleta($agclientes,$IDTatarabuela) }}">{{ GetVida($agclientes,$IDTatarabuela) }}</p>
            {{-- <span class="editar"><x-editar-persona :agclientes='$agclientes' :countries='$countries' :id='$IDTatarabuela'/></span> --}}
            <span class="editar"><x-editar-persona-i-v2 :agclientes='$agclientes' :countries='$countries' :id='$IDTatarabuela'/></span>
            <x-cargar-doc :agclientes='$agclientes' :id='$IDTatarabuela'/>
            <x-ver-doc :agclientes='$agclientes' :id='$IDTatarabuela'/>
        </div>

        {{-- matrimonio --}}
        <div class="caja_matrimonio" style="top: 150px; left: 370px;">
            @if ($idT)
                <p class="text-center text-sm font-bold">Matrimonio</p>
            @endif
            <p class="text-center text-xs">{{ GetLugarMatr($agclientes,$IDTatarabuelo) }}</p>
            <p class="text-center text-xs">{{ GetFechaMatr($agclientes,$IDTatarabuela) }}</p>
        </div>
        {{-- flechas --}}
        <img class="img_triple_flecha" src="{{ asset('img\flechas\triple_flecha.png') }}" alt="flechas" style="top: 40px; left: 460px;">
        <img class="img_flecha_curva" src="{{ asset('img\flechas\flecha_curva.png') }}" alt="flechas" style="top: 170px; left: 310px;">

        {{-- BISABUELO --}}
        <div class="caja_persona" style="top: 220px; left: 50px;">
            @php
                $IDBisabuelo = GetIDHijo($IDTatarabuelo);
                $idB = GetID($agclientes,$IDBisabuelo);
            @endphp

            @if ($idB)
                <h1 class="text-center font-bold text-sm pt-1 ctvSefar" title="Editar">
                    <a href="{{ route('crud.agclientes.edit', $idB ) }}">{{ GetPersona($IDBisabuelo) }}</a>
                </h1>
            @else
                <h1 class="text-center font-bold text-sm pt-1 text-red" title="Añadir">
                    <a href="{{ route('crud.agclientes.create') }}">{{ GetPersona($IDBisabuelo) }}</a>
                </h1>
            @endif

            <p class="text-center text-sm ctrSefar">{{ GetNombres($agclientes,$IDBisabuelo) }}</p>
            <p class="text-center text-sm ctrSefar">{{ GetApellidos($agclientes,$IDBisabuelo) }}</p>
            @if ($idB)
                <p class="text-center text-xs">Lugar de nacimiento:</p>
            @endif
            <p class="text-center text-xs">{{ GetLugarNac($agclientes,$IDBisabuelo) }}</p>
            <p class="text-center text-xs" title="{{ GetVidaCompleta($agclientes,$IDBisabuelo) }}">{{ GetVida($agclientes,$IDBisabuelo) }}</p>
            {{-- <span class="editar"><x-editar-persona :agclientes='$agclientes' :countries='$countries' :id='$IDBisabuelo'/></span> --}}
            <span class="editar"><x-editar-persona-i-v2 :agclientes='$agclientes' :countries='$countries' :id='$IDBisabuelo'/></span>
            <x-cargar-doc :agclientes='$agclientes' :id='$IDBisabuelo'/>
            <x-ver-doc :agclientes='$agclientes' :id='$IDBisabuelo'/>
        </div>

        {{-- BISABUELA --}}
        <div class="caja_persona" style="top: 220px; left: 670px;">
            @php
                $pasoConyugueB = $IDBisabuelo % 2 ? -1 : 1;
                $IDBisabuela = $IDBisabuelo + $pasoConyugueB;
                $idBa = GetID($agclientes,$IDBisabuela);
            @endphp

            @if ($idBa)
                <h1 class="text-center font-bold text-sm pt-1 ctvSefar" title="Editar">
                    <a href="{{ route('crud.agclientes.edit', $idBa ) }}">{{ GetPersona($IDBisabuela) }}</a>
                </h1>
            @else
                <h1 class="text-center font-bold text-sm pt-1 text-red" title="Añadir">
                    <a href="{{ route('crud.agclientes.create') }}">{{ GetPersona($IDBisabuela) }}</a>
                </h1>
            @endif

            <p class="text-center text-sm ctrSefar">{{ GetNombres($agclientes,$IDBisabuela) }}</p>
            <p class="text-center text-sm ctrSefar">{{ GetApellidos($agclientes,$IDBisabuela) }}</p>
            @if ($idBa)
                <p class="text-center text-xs">Lugar de nacimiento:</p>
            @endif
            <p class="text-center text-xs">{{ GetLugarNac($agclientes,$IDBisabuela) }}</p>
            <p class="text-center text-xs" title="{{ GetVidaCompleta($agclientes,$IDBisabuela) }}">{{ GetVida($agclientes,$IDBisabuela) }}</p>
            {{-- <span class="editar"><x-editar-persona :agclientes='$agclientes' :countries='$countries' :id='$IDBisabuela'/></span> --}}
            <span class="editar"><x-editar-persona-i-v2 :agclientes='$agclientes' :countries='$countries' :id='$IDBisabuela'/></span>
            <x-cargar-doc :agclientes='$agclientes' :id='$IDBisabuela'/>
            <x-ver-doc :agclientes='$agclientes' :id='$IDBisabuela'/>
        </div>

        {{-- matrimonio --}}
        <div class="caja_matrimonio" style="top: 350px; left: 370px;">
            @if ($idB)
                <p class="text-center text-sm font-bold">Matrimonio</p>
            @endif
            <p class="text-center text-xs">{{ GetLugarMatr($agclientes,$IDBisabuelo) }}</p>
            <p class="text-center text-xs">{{ GetFechaMatr($agclientes,$IDBisabuelo) }}</p>
        </div>
        {{-- flechas --}}
        <img class="img_triple_flecha" src="{{ asset('img\flechas\triple_flecha.png') }}" alt="flechas" style="top: 240px; left: 460px;">
        <img class="img_flecha_curva" src="{{ asset('img\flechas\flecha_curva.png') }}" alt="flechas" style="top: 370px; left: 310px;">

        {{-- ABUELO --}}
        <div class="caja_persona" style="top: 420px; left: 50px;">
            @php
                $IDAbuelo = GetIDHijo($IDBisabuelo);
                $idA = GetID($agclientes,$IDAbuelo);
            @endphp

            @if ($idA)
                <h1 class="text-center font-bold text-sm pt-1 ctvSefar" title="Editar">
                    <a href="{{ route('crud.agclientes.edit', $idA ) }}">{{ GetPersona($IDAbuelo) }}</a>
                </h1>
            @else
                <h1 class="text-center font-bold text-sm pt-1 text-red" title="Añadir">
                    <a href="{{ route('crud.agclientes.create') }}">{{ GetPersona($IDAbuelo) }}</a>
                </h1>
            @endif

            <p class="text-center text-sm ctrSefar">{{ GetNombres($agclientes,$IDAbuelo) }}</p>
            <p class="text-center text-sm ctrSefar">{{ GetApellidos($agclientes,$IDAbuelo) }}</p>
            @if ($idA)
                <p class="text-center text-xs">Lugar de nacimiento:</p>
            @endif
            <p class="text-center text-xs">{{ GetLugarNac($agclientes,$IDAbuelo) }}</p>
            <p class="text-center text-xs" title="{{ GetVidaCompleta($agclientes,$IDAbuelo) }}">{{ GetVida($agclientes,$IDAbuelo) }}</p>
            {{-- <span class="editar"><x-editar-persona :agclientes='$agclientes' :countries='$countries' :id='$IDAbuelo'/></span> --}}
            <span class="editar"><x-editar-persona-i-v2 :agclientes='$agclientes' :countries='$countries' :id='$IDAbuelo'/></span>
            <x-cargar-doc :agclientes='$agclientes' :id='$IDAbuelo'/>
            <x-ver-doc :agclientes='$agclientes' :id='$IDAbuelo'/>
        </div>

        {{-- ABUELA --}}
        <div class="caja_persona" style="top: 420px; left: 670px;">
            @php
                $pasoConyugueA = $IDAbuelo % 2 ? -1 : 1;
                $IDAbuela = $IDAbuelo + $pasoConyugueA;
                $idAa = GetID($agclientes,$IDAbuela);
            @endphp

            @if ($idAa)
                <h1 class="text-center font-bold text-sm pt-1 ctvSefar" title="Editar">
                    <a href="{{ route('crud.agclientes.edit', $idAa ) }}">{{ GetPersona($IDAbuela) }}</a>
                </h1>
            @else
                <h1 class="text-center font-bold text-sm pt-1 text-red" title="Añadir">
                    <a href="{{ route('crud.agclientes.create') }}">{{ GetPersona($IDAbuela) }}</a>
                </h1>
            @endif

            <p class="text-center text-sm ctrSefar">{{ GetNombres($agclientes,$IDAbuela) }}</p>
            <p class="text-center text-sm ctrSefar">{{ GetApellidos($agclientes,$IDAbuela) }}</p>
            @if ($idAa)
                <p class="text-center text-xs">Lugar de nacimiento:</p>
            @endif
            <p class="text-center text-xs">{{ GetLugarNac($agclientes,$IDAbuela) }}</p>
            <p class="text-center text-xs" title="{{ GetVidaCompleta($agclientes,$IDAbuela) }}">{{ GetVida($agclientes,$IDAbuela) }}</p>
            {{-- <span class="editar"><x-editar-persona :agclientes='$agclientes' :countries='$countries' :id='$IDAbuela'/></span> --}}
            <span class="editar"><x-editar-persona-i-v2 :agclientes='$agclientes' :countries='$countries' :id='$IDAbuela'/></span>
            <x-cargar-doc :agclientes='$agclientes' :id='$IDAbuela'/>
            <x-ver-doc :agclientes='$agclientes' :id='$IDAbuela'/>
        </div>

        {{-- matrimonio --}}
        <div class="caja_matrimonio" style="top: 550px; left: 370px;">
            @if ($idA)
                <p class="text-center text-sm font-bold">Matrimonio</p>
            @endif
            <p class="text-center text-xs">{{ GetLugarMatr($agclientes,$IDAbuelo) }}</p>
            <p class="text-center text-xs">{{ GetFechaMatr($agclientes,$IDAbuelo) }}</p>
        </div>
        {{-- flechas --}}
        <img class="img_triple_flecha" src="{{ asset('img\flechas\triple_flecha.png') }}" alt="flechas" style="top: 440px; left: 460px;">
        <img class="img_flecha_curva" src="{{ asset('img\flechas\flecha_curva.png') }}" alt="flechas" style="top: 570px; left: 310px;">

        {{-- PADRE --}}
        <div class="caja_persona" style="top: 620px; left: 50px;">
            @php
                $IDPadre = GetIDHijo($IDAbuelo);
                $idP = GetID($agclientes,$IDPadre);
            @endphp

            @if ($idP)
                <h1 class="text-center font-bold text-sm pt-1 ctvSefar" title="Editar">
                    <a href="{{ route('crud.agclientes.edit', $idP ) }}">{{ GetPersona($IDPadre) }}</a>
                </h1>
            @else
                <h1 class="text-center font-bold text-sm pt-1 text-red" title="Añadir">
                    <a href="{{ route('crud.agclientes.create') }}">{{ GetPersona($IDPadre) }}</a>
                </h1>
            @endif

            <p class="text-center text-sm ctrSefar">{{ GetNombres($agclientes,$IDPadre) }}</p>
            <p class="text-center text-sm ctrSefar">{{ GetApellidos($agclientes,$IDPadre) }}</p>
            @if ($idP)
                <p class="text-center text-xs">Lugar de nacimiento:</p>
            @endif
            <p class="text-center text-xs">{{ GetLugarNac($agclientes,$IDPadre) }}</p>
            <p class="text-center text-xs" title="{{ GetVidaCompleta($agclientes,$IDPadre) }}">{{ GetVida($agclientes,$IDPadre) }}</p>
            {{-- <span class="editar"><x-editar-persona :agclientes='$agclientes' :countries='$countries' :id='$IDPadre'/></span> --}}
            <span class="editar"><x-editar-persona-i-v2 :agclientes='$agclientes' :countries='$countries' :id='$IDPadre'/></span>
            <x-cargar-doc :agclientes='$agclientes' :id='$IDPadre'/>
            <x-ver-doc :agclientes='$agclientes' :id='$IDPadre'/>
        </div>

        {{-- MADRE --}}
        <div class="caja_persona" style="top: 620px; left: 670px;">
            @php
                $pasoConyugueP = $IDPadre % 2 ? -1 : 1;
                $IDMadre = $IDPadre + $pasoConyugueP;
                $idM = GetID($agclientes,$IDMadre);
            @endphp

            @if ($idM)
                <h1 class="text-center font-bold text-sm pt-1 ctvSefar" title="Editar">
                    <a href="{{ route('crud.agclientes.edit', $idM ) }}">{{ GetPersona($IDMadre) }}</a>
                </h1>
            @else
                <h1 class="text-center font-bold text-sm pt-1 text-red" title="Añadir">
                    <a href="{{ route('crud.agclientes.create') }}">{{ GetPersona($IDMadre) }}</a>
                </h1>
            @endif

            <p class="text-center text-sm ctrSefar">{{ GetNombres($agclientes,$IDMadre) }}</p>
            <p class="text-center text-sm ctrSefar">{{ GetApellidos($agclientes,$IDMadre) }}</p>
            @if ($idM)
                <p class="text-center text-xs">Lugar de nacimiento:</p>
            @endif
            <p class="text-center text-xs">{{ GetLugarNac($agclientes,$IDMadre) }}</p>
            <p class="text-center text-xs" title="{{ GetVidaCompleta($agclientes,$IDMadre) }}">{{ GetVida($agclientes,$IDMadre) }}</p>
            {{-- <span class="editar"><x-editar-persona :agclientes='$agclientes' :countries='$countries' :id='$IDMadre'/></span> --}}
            <span class="editar"><x-editar-persona-i-v2 :agclientes='$agclientes' :countries='$countries' :id='$IDMadre'/></span>
            <x-cargar-doc :agclientes='$agclientes' :id='$IDMadre'/>
            <x-ver-doc :agclientes='$agclientes' :id='$IDMadre'/>
        </div>

        {{-- matrimonio --}}
        <div class="caja_matrimonio" style="top: 750px; left: 370px;">
            @if ($idP)
                <p class="text-center text-sm font-bold">Matrimonio</p>
            @endif
            <p class="text-center text-xs">{{ GetLugarMatr($agclientes,$IDPadre) }}</p>
            <p class="text-center text-xs">{{ GetFechaMatr($agclientes,$IDPadre) }}</p>
        </div>
        {{-- flechas --}}
        <img class="img_triple_flecha" src="{{ asset('img\flechas\triple_flecha.png') }}" alt="flechas" style="top: 640px; left: 460px;">
        <img class="img_flecha_curva" src="{{ asset('img\flechas\flecha_curva.png') }}" alt="flechas" style="top: 770px; left: 310px;">

        {{-- SOLICITANTE --}}
        <div class="caja_persona" style="top: 820px; left: 50px;">
            @php
                $IDSolicitante = GetIDHijo($IDPadre);
                $idS = GetID($agclientes,$IDSolicitante);
            @endphp

            @if ($idS)
                <h1 class="text-center font-bold text-sm pt-1 ctvSefar" title="Editar">
                    <a href="{{ route('crud.agclientes.edit', $idS ) }}">{{ GetPersona($IDSolicitante) }}</a>
                </h1>
            @else
                <h1 class="text-center font-bold text-sm pt-1 text-red" title="Añadir">
                    <a href="{{ route('crud.agclientes.create') }}">{{ GetPersona($IDSolicitante) }}</a>
                </h1>
            @endif

            <p class="text-center text-sm ctrSefar">{{ GetNombres($agclientes,$IDSolicitante) }}</p>
            <p class="text-center text-sm ctrSefar">{{ GetApellidos($agclientes,$IDSolicitante) }}</p>
            @if ($idS)
                <p class="text-center text-xs">Lugar de nacimiento:</p>
            @endif
            <p class="text-center text-xs">{{ GetLugarNac($agclientes,$IDSolicitante) }}</p>
            <p class="text-center text-xs" title="{{ GetVidaCompleta($agclientes,$IDSolicitante) }}">{{ GetVida($agclientes,$IDSolicitante) }}</p>
            {{-- <span class="editar"><x-editar-persona :agclientes='$agclientes' :countries='$countries' :id='$IDSolicitante'/></span> --}}
            <span class="editar"><x-editar-persona-i-v2 :agclientes='$agclientes' :countries='$countries' :id='$IDSolicitante'/></span>
            <x-cargar-doc :agclientes='$agclientes' :id='$IDSolicitante'/>
            <x-ver-doc :agclientes='$agclientes' :id='$IDSolicitante'/>
        </div>

        {{-- CÓNYUGUE --}}
        <div class="caja_persona" style="top: 820px; left: 670px;">
            @php
                $IDConyugue = null;
                $idC = GetID($agclientes,$IDConyugue);
            @endphp

            @if ($idC)
                <h1 class="text-center font-bold text-sm pt-1 ctvSefar" title="Editar">
                    <a href="{{ route('crud.agclientes.edit', $idC ) }}">{{ GetPersona($IDConyugue) }}</a>
                </h1>
            @else
                <h1 class="text-center font-bold text-sm pt-1 text-red" title="Añadir">
                    <a href="{{ route('crud.agclientes.create') }}">{{ GetPersona($IDConyugue) }}</a>
                </h1>
            @endif

            <p class="text-center text-sm ctrSefar">{{ GetNombres($agclientes,$IDConyugue) }}</p>
            <p class="text-center text-sm ctrSefar">{{ GetApellidos($agclientes,$IDConyugue) }}</p>
            @if ($idC)
                <p class="text-center text-xs">Lugar de nacimiento:</p>
            @endif
            <p class="text-center text-xs">{{ GetLugarNac($agclientes,$IDConyugue) }}</p>
            <p class="text-center text-xs" title="{{ GetVidaCompleta($agclientes,$IDConyugue) }}">{{ GetVida($agclientes,$IDConyugue) }}</p>
            {{-- <span class="editar"><x-editar-persona :agclientes='$agclientes' :id='$IDConyugue'/></span> --}}
            {{-- <span class="editar"><x-editar-persona-i-v2 :agclientes='$agclientes' :id='$IDConyugue'/></span> --}}
            {{-- <x-cargar-doc :agclientes='$agclientes' :countries='$countries' :id='$IDConyugue'/> --}}
            {{-- <x-ver-doc :agclientes='$agclientes' :id='$IDConyugue'/> --}}
        </div>

        {{-- matrimonio --}}
        <div class="caja_matrimonio" style="top: 950px; left: 370px;">
            @if ($idS)
                <p class="text-center text-sm font-bold">Matrimonio</p>
            @endif
            <p class="text-center text-xs">{{ GetLugarMatr($agclientes,$IDSolicitante) }}</p>
            <p class="text-center text-xs">{{ GetFechaMatr($agclientes,$IDSolicitante) }}</p>
        </div>
        {{-- flechas --}}
        <img class="img_triple_flecha" src="{{ asset('img\flechas\triple_flecha.png') }}" alt="flechas" style="top: 840px; left: 460px;">
    </div>
</div>

@for ($i = 1; $i <= 31; $i++)
   <x-editar-persona-ii-v2 :agclientes='$agclientes' :countries='$countries' :id='$i'/>
@endfor
