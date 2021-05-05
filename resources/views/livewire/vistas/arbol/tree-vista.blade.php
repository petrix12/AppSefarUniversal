<div>
    <div class="p-2 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="lg:text-center">
            <h2 class="text-sm font-bold ctaSefar cfvSefar tracking-wide pt-2 rounded-lg opacity-75 flex h-8 justify-center items-center">
                Cliente: {{ $agclientes[0]->Nombres.', '.$agclientes[0]->Apellidos.' / '.$agclientes[0]->IDCliente}}
            </h2>
            <p class="mt-2 text-lg leading-8 font-extrabold tracking-tight text-gray-900 sm:text-4xl">
                ÁRBOL GENEALÓGICO (VISTA HORIZONTAL) 
            </p>
        </div>
    </div>
    <div class="container overflow-x-scroll">
        <div class="flex justify-between">
            <div class="px-4 py-2 m-2">
                {{-- ÁRBOL EXPANDIDO O COMPACTO --}}
                <div class="text-left">
                    <label for="Modo" class="px-3 block text-sm font-medium text-gray-700" title="Indicar línea genealógica">Modo</label>
                    <select wire:model="Modo" name="Modo"class="w-44 mt-1 block py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        <option value="0">Expandido</option>
                        <option value="1">Compacto</option>
                    </select>
                </div>
            </div>
            <div class="px-4 py-2 m-2">
                {{-- FAMILIARES --}}
                <div class="justify-center">
                    <label for="Familiares" class="px-3 block text-sm font-medium text-gray-700" title="Familiares en el proceso">Familiares</label>
                    <select wire:model="IDFamiliar" style="width:450px" name="Familiares" class="w-44 mt-1 block py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        <option value="{{ null }}">-</option>
                        @foreach ($families as $family)
                            <option value="{{ $family->IDFamiliar }}">{{ $family->Familiar.' - '.$family->Parentesco }}</option>
                        @endforeach
                    </select>
                    @if($IDFamiliar)
                    <div class="pt-2">
                        <div class="px-4 py-3 bg-gray-50 text-left sm:px-6">
                            <a href="{{ route('arboles.tree.index', $IDFamiliar) }}" target="_blank" class="cfrSefar inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                Ir a familiar seleccionado
                            </a>
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
    
    {{-- MODO EXPANDIDO --}}
    @if ($Modo == 0)
    <div style="height:135rem" class="container relative overflow-x-scroll">
        <div class="tree-chart" width="100%">
            <!-- *** CLIENTE *** -->
            <div class="caja_per" style="top: 985px; left: 10px; ">
                <span class="encabezado" title="{{ GetDatosMatrimonio($agclientes,1) }}">{{ GetPersona(1) }}</span>
                <span class="nombres">{{ GetNombres($agclientes,1) }}</span>
                <span class="apellidos">{{ GetApellidos($agclientes,1) }}</span>
                <span class="nacimiento">{{ $mostraLN = GetLugarNac($agclientes,1) }}</span>
                @if ($mostraLN)
                    <span class="texto">Lugar de nacimiento</span>    
                @endif
                <span class="vida" title="{{ GetVidaCompleta($agclientes,1) }}">{{ GetVida($agclientes,1) }}</span>
            </div>

            <!-- *** PADRES *** -->
            @for ($i = 2; $i <= 3; $i++)
            <div class="caja_per" style="top: {{ 465 + ($i-2)*1040 }}px; left: 78px; ">
                <span class="encabezado" title="{{ GetDatosMatrimonio($agclientes,$i) }}">{{ GetPersona($i) }}</span>
                <span class="nombres">{{ GetNombres($agclientes,$i) }}</span>
                <span class="apellidos">{{ GetApellidos($agclientes,$i) }}</span>
                <span class="nacimiento">{{ $mostraLN = GetLugarNac($agclientes,$i) }}</span>
                @if ($mostraLN)
                    <span class="texto">Lugar de nacimiento</span>    
                @endif
                <span class="vida" title="{{ GetVidaCompleta($agclientes,$i) }}">{{ GetVida($agclientes,$i) }}</span>
            </div>
            <div class="link father-branch" style="opacity: 1 !important; left: 80px; top: {{ 584 + ($i -2)*520 }}px; width: 70px; height: 402px;">
                <span class="first"></span>
                <span class="second"></span>
            </div>
            @endfor

            <!-- *** ABUELOS *** -->
            @for ($i = 4; $i <=7; $i++)
            <div class="caja_per" style="top: {{ 205 + ($i-4)*520 }}px; left: 280px; ">
                <span class="encabezado" title="{{ GetDatosMatrimonio($agclientes,$i) }}">{{ GetPersona($i) }}</span>
                <span class="nombres">{{ GetNombres($agclientes,$i) }}</span>
                <span class="apellidos">{{ GetApellidos($agclientes,$i) }}</span>
                <span class="nacimiento">{{ $mostraLN = GetLugarNac($agclientes,$i) }}</span>
                @if ($mostraLN)
                    <span class="texto">Lugar de nacimiento</span>    
                @endif
                <span class="vida" title="{{ GetVidaCompleta($agclientes,$i) }}">{{ GetVida($agclientes,$i) }}</span>
            </div>
            <div class="link father-branch" style="opacity: 1 !important; left: 290px; top: {{ ($i <= 5) ? (324 + ($i-4)*260) : (844 + ($i-4)*260) }}px; width: 50px; height: 142px;">
                <span class="first"></span>
                <span class="second"></span>
            </div>
            @endfor

            <!-- *** BISABUELOS *** -->
            @for ($i = 8; $i <=15; $i++)
            <div class="caja_per" style="top: {{ 75 + ($i-8)*260 }}px; left: 400px; ">
                <span class="encabezado" title="{{ GetDatosMatrimonio($agclientes,$i) }}">{{ GetPersona($i) }}</span>
                <span class="nombres">{{ GetNombres($agclientes,$i) }}</span>
                <span class="apellidos">{{ GetApellidos($agclientes,$i) }}</span>
                <span class="nacimiento">{{ $mostraLN = GetLugarNac($agclientes,$i) }}</span>
                @if ($mostraLN)
                    <span class="texto">Lugar de nacimiento</span>    
                @endif
                <span class="vida" title="{{ GetVidaCompleta($agclientes,$i) }}">{{ GetVida($agclientes,$i) }}</span>
            </div>
            @if ($i % 2 == 0)
            <div class="link father-branch" style="opacity: 1 !important; left: 320px; top: {{135 + ($i-8)*260}}px; width: 80px; height: 71px;">
                <span class="first"></span>
                <span class="second"></span>
            </div>
            <div class="link mother-branch" style="opacity: 1 !important; left: 320px; top: {{324 + ($i-8)*260}}px; width: 80px; height: 71px;">
                <span class="first"></span>
                <span class="second"></span>
            </div>
            @endif
            @endfor

            <!-- *** TATARABUELOS *** -->
            @for ($i = 16; $i <=31; $i++)
            <div class="caja_per" style="top: {{ 10 + ($i-16)*130 }}px; left: 705px; ">
                <span class="encabezado" title="{{ GetDatosMatrimonio($agclientes,$i) }}">{{ GetPersona($i) }}</span>
                <span class="nombres">{{ GetNombres($agclientes,$i) }}</span>
                <span class="apellidos">{{ GetApellidos($agclientes,$i) }}</span>
                <span class="nacimiento">{{ $mostraLN = GetLugarNac($agclientes,$i) }}</span>
                @if ($mostraLN)
                    <span class="texto">Lugar de nacimiento</span>    
                @endif
                <span class="vida" title="{{ GetVidaCompleta($agclientes,$i) }}">{{ GetVida($agclientes,$i) }}</span>
            </div>
            @if ($i % 2 == 0)
            <div class="link father-branch" style="opacity: 1 !important; left: 670px; top: {{70 + ($i-16)*130}}px; width: 35px; height: 60px;">
                <span class="first"></span>
                <span class="second"></span>
            </div>
            <div class="link mother-branch" style="opacity: 1 !important; left: 670px; top: {{140 + ($i-16)*130}}px; width: 35px; height: 60px;">
                <span class="first"></span>
                <span class="second"></span>
            </div>
            @endif
            @endfor
        </div>
    </div>
    @endif

    {{-- MODO COMPACTO --}}
    @if ($Modo == 1)
    <div style="height:37rem" class="container relative overflow-x-scroll">
        <div class="tree-chart" width="100%">
            <!-- *** CLIENTE *** -->
            <div class="caja_per" style="top: 225px; left: 10px; ">
                <span class="encabezado" title="{{ GetDatosMatrimonio($agclientes,1) }}">{{ GetPersona(1) }}</span>
                <span class="nombres">{{ GetNombres($agclientes,1) }}</span>
                <span class="apellidos">{{ GetApellidos($agclientes,1) }}</span>
                <span class="nacimiento">{{ $mostraLN = GetLugarNac($agclientes,1) }}</span>
                @if ($mostraLN)
                    <span class="texto">Lugar de nacimiento</span>    
                @endif
                <span class="vida" title="{{ GetVidaCompleta($agclientes,1) }}">{{ GetVida($agclientes,1) }}</span>
                <button onclick="document.getElementById('verDocumentos').showModal()"><span class="folder"><i class="far fa-folder-open"></i></span></button>
                {{-- VENTANA MODAL DOCUMENTOS --}}
                <dialog id="verDocumentos" class="container h-auto w-11/12 md:w-1/2 p-5 bg-white rounded-md ">    
                    <div class="flex flex-col w-full h-auto ">
                        <!-- Título -->
                        <div class="flex w-full h-auto justify-center items-center">
                            <div class="flex w-10/12 h-auto py-3 justify-center items-center text-lg font-bold">
                                Documentos {{ GetNombres($agclientes,1) }} {{ GetApellidos($agclientes,1) }}
                            </div>
                            <div onclick="document.getElementById('verDocumentos').close();" class="flex w-1/12 h-auto justify-center cursor-pointer">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#000000" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-x"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                            </div>
                        </div>
                        <!-- Contenido-->
                        <div class="flex w-full h-auto py-10 px-2 justify-center items-center bg-gray-200 rounded text-center text-gray-500">
                            <a href="{{ asset('doc/P104828027/Cliente/Acuerdo Meza Gil Jesús Enrique.pdf') }}" target="_blank">Prueba</a>
                            
                        </div>
                        <div class="px-4 py-3 bg-gray-50 text-right sm:px-6">
                            <button type="submit" class="cfrSefar inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                Aceptar
                            </button>
                            <button onclick="document.getElementById('verDocumentos').close();" class="cfgSefar inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                Cancelar
                            </button>
                        </div>
                    </div>
                </dialog>
            </div>

            <!-- *** PADRES *** -->
            @for ($i = 2; $i <= 3; $i++)
            <div class="caja_per" style="top: {{ 85 + ($i-2)*280 }}px; left: 100px; ">
                <span class="encabezado" title="{{ GetDatosMatrimonio($agclientes,$i) }}">{{ GetPersona($i) }}</span>
                <span class="nombres">{{ GetNombres($agclientes,$i) }}</span>
                <span class="apellidos">{{ GetApellidos($agclientes,$i) }}</span>
                <span class="nacimiento">{{ $mostraLN = GetLugarNac($agclientes,$i) }}</span>
                @if ($mostraLN)
                    <span class="texto">Lugar de nacimiento</span>
                @endif
                <span class="vida" title="{{ GetVidaCompleta($agclientes,$i) }}">{{ GetVida($agclientes,$i) }}</span>
            </div>
            @endfor
            <div class="link father-branch" style="opacity: 1 !important; left: 80px; top: 145px; width: 20px; height: 120px;">
                <span class="first"></span>
                <span class="second"></span>
            </div>
            <div class="link mother-branch" style="opacity: 1 !important; left: 80px; top: 305px; width: 20px; height: 120px;">
                <span class="first"></span>
                <span class="second"></span>
            </div>

            <!-- *** ABUELOS *** -->
            @for ($i = 4; $i <=7; $i++)
            <div class="caja_abuelos" style="top: {{ 25 + ($i-4)*140 }}px; left: 390px; ">
                <span class="encabezado_abl" title="{{ GetDatosMatrimonio($agclientes,$i) }}">{{ GetPersona($i) }}</span>
                <span class="nom-abuelo">{{ GetNombres($agclientes,$i) }}</span>
                <span class="ape-abuelo">{{ GetApellidos($agclientes,$i) }}</span>
                <span class="nac-abuelo">{{ $mostraLN = GetLugarNac($agclientes,$i) }}</span>
                @if ($mostraLN)
                    <span class="text-abuelo">Lugar de nacimiento</span>    
                @endif
                <span class="vid-abuelo" title="{{ GetVidaCompleta($agclientes,$i) }}">{{ GetVida($agclientes,$i) }}</span>
            </div>
            @if ($i % 2 == 0)
            <div class="link father-branch" style="opacity: 1 !important; left: 370px; top: {{ 75 + ($i-4)*140 }}px; width: 20px; height: 50px;">
                <span class="first"></span>
                <span class="second"></span>
            </div>
            <div class="link mother-branch" style="opacity: 1 !important; left: 370px; top: {{ 165 + ($i-4)*140 }}px; width: 20px; height: 50px;">
                <span class="first"></span>
                <span class="second"></span>
            </div>
            @endif
            @endfor

            <!-- *** BISABUELOS *** -->
            @for ($i = 8; $i <=15; $i++)
            <div class="caja_bisabuelos" style="top: {{ 10 + ($i-8)*70 }}px; left: 660px; ">
                <span class="encabezado_bis" title="{{ GetDatosMatrimonio($agclientes,$i) }}">{{ GetPersona($i) }}</span>
                <span class="nom-bisabuelo">{{ GetNombres($agclientes,$i) }}</span>
                <span class="ape-bisabuelo" title="Lugar de nacimiento: {{  GetLugarNac($agclientes,$i) }}">{{ GetApellidos($agclientes,$i) }}</span>
                <span class="vid-bisabuelo" title="{{ GetVidaCompleta($agclientes,$i) }}">{{ GetVida($agclientes,$i) }}</span>
            </div>
            @if ($i % 2 == 0)
            <div class="link father-branch" style="opacity: 1 !important; left: 640px; top: {{40 + ($i-8)*70}}px; width: 20px; height: 30px;">
                <span class="first"></span>
                <span class="second"></span>
            </div>
            <div class="link mother-branch" style="opacity: 1 !important; left: 640px; top: {{80 + ($i-8)*70}}px; width: 20px; height: 30px;">
                <span class="first"></span>
                <span class="second"></span>
            </div>
            @endif
            @endfor

            <!-- *** TATARABUELOS *** -->
            @for ($i = 16; $i <=31; $i++)
            <div class="caja_tatarabuelos" style="top: {{ 10 + ($i-16)*35 }}px; left: 930px;" title="{{ $vida = GetVidaCompleta($agclientes,$i) }}">
                <span class="nom-tatarabuelos" title="{{ GetPersona($i) }} - {{ GetDatosMatrimonio($agclientes,$i) }}">{{ GetNombres($agclientes,$i) }}</span>
                <span class="ape-tatarabuelos" title="{{ $vida }}">{{ GetApellidos($agclientes,$i) }}</span>
                {{-- <span class="vida" title="{{ GetVidaCompleta($agclientes,$i) }}">{{ GetVida($agclientes,$i) }}</span> --}}
            </div>
            @if ($i % 2 == 0)
            <div class="link father-branch" style="opacity: 1 !important; left: 910px; top: {{22 + ($i-16)*35}}px; width: 20px; height: 12.5px;">
                <span class="first"></span>
                <span class="second"></span>
            </div>
            <div class="link mother-branch" style="opacity: 1 !important; left: 910px; top: {{45 + ($i-16)*35}}px; width: 20px; height: 12.5px;">
                <span class="first"></span>
                <span class="second"></span>
            </div>
            @endif
            @endfor
        </div>
    </div>
    @endif
</div>