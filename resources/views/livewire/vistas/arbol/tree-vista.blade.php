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
                        @dump($IDFamiliar)
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

    <div style="height:135rem" class="container relative overflow-x-scroll">
        <div class="tree-chart" width="100%">
            {{-- <!-- *** PIVOTE O PRINCIPAL*** -->
            <!-- Cliente -->
            <div class="caja_per" style="top: 225px; left: 10px; ">
                <span class="encabezado">Cliente</span>
                <span class="nombres">{{ GetNombres($agclientes,1) }}</span>
                <span class="apellidos">{{ GetApellidos($agclientes,1) }}</span>
                <span class="texto">Lugar de nacimiento</span>
                <span class="nacimiento">{{ GetLugarNac($agclientes,1) }}</span>
                <span class="vida" title="{{ GetVidaCompleta($agclientes,1) }}">{{ GetVida($agclientes,1) }}</span>
            </div>
    
            <!-- *** PADRES *** -->
            <!-- Padre -->
            <div class="caja_per" style="top: 85px; left: 280px;">
                <span class="encabezado"><img src="images/personas/padre.png" width="20" height="20" class="m-img">Padre</span>
                <span class="nombres">Guillermo</span>
                <span class="apellidos">Bazó Barrios</span>
                <span class="vida">1943 / ----</span>
                <span class="pie">
                    <img src="images/pivotear.png" width="30" height="30" title="Establecer como principal">
                    <img src="images/informacion.png" width="30" height="30" title="Información detallada...">
                </span>
            </div>
            <div class="link father-branch" style="opacity: 1 !important; left: 260px; top: 145px; width: 20px; height: 120px;">
                <span class="first"></span>
                <span class="second"></span>
            </div>
            <!-- Madre -->
            <div class="caja_per" style="top:365px; left: 280px;">
                <span class="encabezado"><img src="images/personas/madre.png" width="20" height="20" class="m-img">Madre</span>
                <span class="nombres">Rosa Elena</span>
                <span class="apellidos">Canelón Ledezma</span>
                <span class="vida">1942 / ----</span>
                <span class="pie">
                    <img src="images/pivotear.png" width="30" height="30" title="Establecer como principal">
                    <img src="images/informacion.png" width="30" height="30" title="Información detallada...">
                </span>
            </div>
            <div class="link mother-branch" style="opacity: 1 !important; left: 260px; top: 305px; width: 20px; height: 120px;">
                <span class="first"></span>
                <span class="second"></span>
            </div>
    
            <!-- *** ABUELOS *** -->
            <!-- Abuelo Paterno -->
            <div class="caja_abuelos" style="top: 25px; left: 550px;">
                <span class="encabezado_abl"><img src="images/personas/abuelo.png" width="15" height="15" class="m-img-abl">Abuelo Paterno</span>
                <span class="nom-abuelo">César Alfonso</span>
                <span class="ape-abuelo">Bazó Rodríguez</span>
                <span class="vid-abuelo">1900 / 1977</span>
                <span class="pie-abuelo">
                    <img src="images/pivotear.png" width="25" height="25" title="Establecer como principal">
                    <img src="images/informacion.png" width="25" height="25" title="Información detallada...">
                </span>
            </div>
            <div class="link father-branch" style="opacity: 1 !important; left: 530px; top: 75px; width: 20px; height: 50px;">
                <span class="first"></span>
                <span class="second"></span>
            </div>
            <!-- Abuela Paterna -->
            <div class="caja_abuelos" style="top: 165px; left: 550px;">
                <span class="encabezado_abl"><img src="images/personas/abuela.png" width="15" height="15" class="m-img-abl">Abuela Paterna</span>
                <span class="nom-abuelo">Carmen Cecilia</span>
                <span class="ape-abuelo">Barrios</span>
                <span class="vid-abuelo">1915 / 1998</span>
                <span class="pie-abuelo">
                    <img src="images/pivotear.png" width="25" height="25" title="Establecer como principal">
                    <img src="images/informacion.png" width="25" height="25" title="Información detallada...">
                </span>
            </div>
            <div class="link mother-branch" style="opacity: 1 !important; left: 530px; top: 165px; width: 20px; height: 50px;">
                <span class="first"></span>
                <span class="second"></span>
            </div>
            <!-- Abuelo Materno -->
            <div class="caja_abuelos" style="top: 305px; left: 550px;">
                <span class="encabezado_abl"><img src="images/personas/abuelo.png" width="15" height="15" class="m-img-abl">Abuelo Materno</span>
                <span class="nom-abuelo">Pedro Jesús</span>
                <span class="ape-abuelo">Cenelón</span>
                <span class="vid-abuelo">1904 / 1959</span>
                <span class="pie-abuelo">
                    <img src="images/pivotear.png" width="25" height="25" title="Establecer como principal">
                    <img src="images/informacion.png" width="25" height="25" title="Información detallada...">
                </span>
            </div>
                <div class="link father-branch" style="opacity: 1 !important; left: 530px; top: 355px; width: 20px; height: 50px;">
                <span class="first"></span>
                <span class="second"></span>
            </div>
            <!-- Abuela Materna -->
            <div class="caja_abuelos" style="top: 445px; left: 550px;">
                <span class="encabezado_abl"><img src="images/personas/abuela.png" width="15" height="15" class="m-img-abl">Abuela Materna</span>
                <span class="nom-abuelo">Juana María</span>
                <span class="ape-abuelo">Ledezma Azogue</span>
                <span class="vid-abuelo">1917 / 1964</span>
                <span class="pie-abuelo">
                    <img src="images/pivotear.png" width="25" height="25" title="Establecer como principal">
                    <img src="images/informacion.png" width="25" height="25" title="Información detallada...">
                </span>
            </div>
            <div class="link mother-branch" style="opacity: 1 !important; left: 530px; top: 445px; width: 20px; height: 50px;">
                <span class="first"></span>
                <span class="second"></span>
            </div>
    
            <!-- *** BISABUELOS *** -->
            <!-- Bisabuelo PP -->
            <div class="caja_bisabuelos" style="top: 10px; left: 820px;">
                <span class="encabezado_bis"><img src="images/personas/abuelo.png" width="13" height="13" class="m-img-bis">Bisabuelo PP</span>
                <span class="nom-bisabuelo">Alfonso</span>
                <span class="ape-bisabuelo">Bazó Jugo</span>
                <span class="vid-bisabuelo">18XX / 1912</span>
                <button class="m-img-b-bis">
                    <img src="images/pivotear.png" width="28" height="28" title="Establecer como principal">
                </button>
                <img src="images/informacion.png" width="28" height="28" class="m-img-i-bis" title="Información detallada...">
            </div>
            <div class="link father-branch" style="opacity: 1 !important; left: 800px; top: 40px; width: 20px; height: 30px;">
                <span class="first"></span>
                <span class="second"></span>
            </div>
            <!-- Bisabuela PP -->
            <div class="caja_bisabuelos" style="top: 80px; left: 820px;">
                <span class="encabezado_bis"><img src="images/personas/abuela.png" width="13" height="13" class="m-img-bis">Bisabuela PP</span>
                <span class="nom-bisabuelo">Soledad</span>
                <span class="ape-bisabuelo">Rodríguez Trilla</span>
                <span class="vid-bisabuelo">18XX / 1937</span>
                <button class="m-img-b-bis">
                    <img src="images/pivotear.png" width="28" height="28" title="Establecer como principal">
                </button>
                <img src="images/informacion.png" width="28" height="28" class="m-img-i-bis" title="Información detallada...">
            </div>
            <div class="link mother-branch" style="opacity: 1 !important; left: 800px; top: 80px; width: 20px; height: 30px;">
                <span class="first"></span>
                <span class="second"></span>
            </div>
            <!-- Bisabuelo PM -->
            <div class="caja_bisabuelos" style="top: 150px; left: 820px;">
                <span class="encabezado_bis"><img src="images/personas/abuelo.png" width="13" height="13" class="m-img-bis">Bisabuelo PM</span>
                <span class="nom-bisabuelo">Rodolfo</span>
                <span class="ape-bisabuelo">Lespe</span>
                <span class="vid-bisabuelo">18XX / 19XX</span>
                <button class="m-img-b-bis">
                    <img src="images/pivotear.png" width="28" height="28" title="Establecer como principal">
                </button>
                <img src="images/informacion.png" width="28" height="28" class="m-img-i-bis" title="Información detallada...">
            </div>
            <div class="link father-branch" style="opacity: 1 !important; left: 800px; top: 320px; width: 20px; height: 30px;">
                <span class="first"></span>
                <span class="second"></span>
            </div>
            <!-- Bisabuela PM -->
            <div class="caja_bisabuelos" style="top: 220px; left: 820px;">
                <span class="encabezado_bis"><img src="images/personas/abuela.png" width="13" height="13" class="m-img-bis">Bisabuela PM</span>
                <span class="nom-bisabuelo">Ursula Eladia</span>
                <span class="ape-bisabuelo">Barrios</span>
                <span class="vid-bisabuelo">1887 / 1976</span>
                <button class="m-img-b-bis">
                    <img src="images/pivotear.png" width="28" height="28" title="Establecer como principal">
                </button>
                <img src="images/informacion.png" width="28" height="28" class="m-img-i-bis" title="Información detallada...">
            </div>
            <div class="link mother-branch" style="opacity: 1 !important; left: 800px; top: 360px; width: 20px; height: 30px;">
                <span class="first"></span>
                <span class="second"></span>
            </div>
            <!-- Bisabuelo MP -->
            <div class="caja_bisabuelos" style="top: 290px; left: 820px;">
                <span class="encabezado_bis"><img src="images/personas/abuelo.png" width="13" height="13" class="m-img-bis">Bisabuelo MP</span>
                <span class="nom-bisabuelo">Nicolas</span>
                <span class="ape-bisabuelo">Morante</span>
                <span class="vid-bisabuelo">18XX / 19XX</span>
                <button class="m-img-b-bis">
                    <img src="images/pivotear.png" width="28" height="28" title="Establecer como principal">
                </button>
                <img src="images/informacion.png" width="28" height="28" class="m-img-i-bis" title="Información detallada...">
            </div>
            <div class="link father-branch" style="opacity: 1 !important; left: 800px; top: 460px; width: 20px; height: 30px;">
                <span class="first"></span>
                <span class="second"></span>
            </div>
            <!-- Bisabuela MP -->
            <div class="caja_bisabuelos" style="top: 360px; left: 820px;">
                <span class="encabezado_bis"><img src="images/personas/abuela.png" width="13" height="13" class="m-img-bis">Bisabuela MP</span>
                <span class="nom-bisabuelo">María Del Rosario</span>
                <span class="ape-bisabuelo">Canelón</span>
                <span class="vid-bisabuelo">18XX / 19XX</span>
                <button class="m-img-b-bis">
                    <img src="images/pivotear.png" width="28" height="28" title="Establecer como principal">
                </button>
                <img src="images/informacion.png" width="28" height="28" class="m-img-i-bis" title="Información detallada...">
            </div>
            <div class="link mother-branch" style="opacity: 1 !important; left: 800px; top: 500px; width: 20px; height: 30px;">
                <span class="first"></span>
                <span class="second"></span>
            </div>
            <!-- Bisabuelo MM -->
            <div class="caja_bisabuelos" style="top: 430px; left: 820px;">
                <span class="encabezado_bis"><img src="images/personas/abuelo.png" width="13" height="13" class="m-img-bis">Bisabuelo MM</span>
                <span class="nom-bisabuelo">Ramón María</span>
                <span class="ape-bisabuelo">Ledezma Crespo</span>
                <span class="vid-bisabuelo">1882 / 1932</span>
                <button class="m-img-b-bis">
                    <img src="images/pivotear.png" width="28" height="28" title="Establecer como principal">
                </button>
                <img src="images/informacion.png" width="28" height="28" class="m-img-i-bis" title="Información detallada...">
            </div>
            <div class="link father-branch" style="opacity: 1 !important; left: 800px; top: 180px; width: 20px; height: 30px;">
                <span class="first"></span>
                <span class="second"></span>
            </div>
            <!-- Bisabuela MM -->
            <div class="caja_bisabuelos" style="top: 500px; left: 820px;">
                <span class="encabezado_bis"><img src="images/personas/abuela.png" width="13" height="13" class="m-img-bis">Bisabuela MM</span>
                <span class="nom-bisabuelo">Carmen Dionisia</span>
                <span class="ape-bisabuelo">Azogue</span>
                <span class="vid-bisabuelo">1898 / 1987</span>
                <button class="m-img-b-bis">
                    <img src="images/pivotear.png" width="28" height="28" title="Establecer como principal">
                </button>
                <img src="images/informacion.png" width="28" height="28" class="m-img-i-bis" title="Información detallada...">
            </div>
            <div class="link mother-branch" style="opacity: 1 !important; left: 800px; top: 220px; width: 20px; height: 30px;">
                <span class="first"></span>
                <span class="second"></span>
            </div>
    
    
            <!-- *** TATARABUELOS *** -->
            <!-- Tatarabuelo PPP -->
            <div class="caja_tatarabuelos" style="top: 10px; left: 1090px;">
                <span class="nom-tatarabuelos">José Federico</span>
                <span class="ape-tatarabuelos">Bazó</span>
                <button class="simple">
                    <img src="images/pivotear.png" width="23" height="23" title="Establecer como principal" class="m-img-b">
                </button>
                <img src="images/informacion.png" width="22" height="22" class="m-img-i" title="Información detallada...">
            </div>
            <div class="link father-branch" style="opacity: 1 !important; left: 1070px; top: 22.5px; width: 20px; height: 12.5px;">
                <span class="first"></span>
                <span class="second"></span>
            </div>
            <!-- Tatarabuela PPP -->
            <div class="caja_tatarabuelos" style="top: 45px; left: 1090px;">
                <span class="nom-tatarabuelos">Estefania</span>
                <span class="ape-tatarabuelos">Jugo</span>
                <button class="simple">
                    <img src="images/pivotear.png" width="23" height="23" title="Establecer como principal" class="m-img-b">
                </button>
                <img src="images/informacion.png" width="22" height="22" class="m-img-i" title="Información detallada...">
            </div>
            <div class="link mother-branch" style="opacity: 1 !important; left: 1070px; top: 45px; width: 20px; height: 12.5px;">
                <span class="first"></span>
                <span class="second"></span>
            </div>
            <!-- Tatarabuelo PPM -->
            <div class="caja_tatarabuelos" style="top: 80px; left: 1090px;">
                <span class="nom-tatarabuelos">Desconocido</span>
                <span class="ape-tatarabuelos"></span>
                <button class="simple">
                    <img src="images/pivotear.png" width="23" height="23" title="Establecer como principal" class="m-img-b">
                </button>
                <img src="images/informacion.png" width="22" height="22" class="m-img-i" title="Información detallada...">
            </div>
            <div class="link father-branch" style="opacity: 1 !important; left: 1070px; top: 92.5px; width: 20px; height: 12.5px;">
                <span class="first"></span>
                <span class="second"></span>
            </div>
            <!-- Tatarabuela PPM -->
            <div class="caja_tatarabuelos" style="top: 115px; left: 1090px;">
                <span class="nom-tatarabuelos">Desconocido</span>
                <span class="ape-tatarabuelos"></span>
                <button class="simple">
                    <img src="images/pivotear.png" width="23" height="23" title="Establecer como principal" class="m-img-b">
                </button>
                <img src="images/informacion.png" width="22" height="22" class="m-img-i" title="Información detallada...">
            </div>
            <div class="link mother-branch" style="opacity: 1 !important; left: 1070px; top: 115px; width: 20px; height: 12.5px;">
                <span class="first"></span>
                <span class="second"></span>
            </div>
    
            <!-- Tatarabuelo PMP -->
            <div class="caja_tatarabuelos" style="top: 150px; left: 1090px;">
                <span class="nom-tatarabuelos">Desconocido</span>
                <span class="ape-tatarabuelos"></span>
                <button class="simple">
                    <img src="images/pivotear.png" width="23" height="23" title="Establecer como principal" class="m-img-b">
                </button>
                <img src="images/informacion.png" width="22" height="22" class="m-img-i" title="Información detallada...">
            </div>
            <div class="link father-branch" style="opacity: 1 !important; left: 1070px; top: 163px; width: 20px; height: 12.5px;">
                <span class="first"></span>
                <span class="second"></span>
            </div>
            <!-- Tatarabuela PMP -->
            <div class="caja_tatarabuelos" style="top: 185px; left: 1090px;">
                <span class="nom-tatarabuelos">Desconocido</span>
                <span class="ape-tatarabuelos"></span>
                <button class="simple">
                    <img src="images/pivotear.png" width="23" height="23" title="Establecer como principal" class="m-img-b">
                </button>
                <img src="images/informacion.png" width="22" height="22" class="m-img-i" title="Información detallada...">
            </div>
            <div class="link mother-branch" style="opacity: 1 !important; left: 1070px; top: 185px; width: 20px; height: 12.5px;">
                <span class="first"></span>
                <span class="second"></span>
            </div>
            <!-- Tatarabuelo PMM -->
            <div class="caja_tatarabuelos" style="top: 220px; left: 1090px;">
                <span class="nom-tatarabuelos">Desconocido</span>
                <span class="ape-tatarabuelos"></span>
                <button class="simple">
                    <img src="images/pivotear.png" width="23" height="23" title="Establecer como principal" class="m-img-b">
                </button>
                <img src="images/informacion.png" width="22" height="22" class="m-img-i" title="Información detallada...">
            </div>
            <div class="link father-branch" style="opacity: 1 !important; left: 1070px; top: 233px; width: 20px; height: 12.5px;">
                <span class="first"></span>
                <span class="second"></span>
            </div>
            <!-- Tatarabuela PMM -->
            <div class="caja_tatarabuelos" style="top: 255px; left: 1090px;">
                <span class="nom-tatarabuelos">Desconocido</span>
                <span class="ape-tatarabuelos"></span>
                <button class="simple">
                    <img src="images/pivotear.png" width="23" height="23" title="Establecer como principal" class="m-img-b">
                </button>
                <img src="images/informacion.png" width="22" height="22" class="m-img-i" title="Información detallada...">
            </div>
            <div class="link mother-branch" style="opacity: 1 !important; left: 1070px; top: 255px; width: 20px; height: 12.5px;">
                <span class="first"></span>
                <span class="second"></span>
            </div>
    
            <!-- Tatarabuelo MPP -->
            <div class="caja_tatarabuelos" style="top: 290px; left: 1090px;">
                <span class="nom-tatarabuelos">Desconocido</span>
                <span class="ape-tatarabuelos"></span>
                <button class="simple">
                    <img src="images/pivotear.png" width="23" height="23" title="Establecer como principal" class="m-img-b">
                </button>
                <img src="images/informacion.png" width="22" height="22" class="m-img-i" title="Información detallada...">
            </div>
            <div class="link father-branch" style="opacity: 1 !important; left: 1070px; top: 303px; width: 20px; height: 12.5px;">
                <span class="first"></span>
                <span class="second"></span>
            </div>
            <!-- Tatarabuela MPP -->
            <div class="caja_tatarabuelos" style="top: 325px; left: 1090px;">
                <span class="nom-tatarabuelos">Desconocido</span>
                <span class="ape-tatarabuelos"></span>
                <button class="simple">
                    <img src="images/pivotear.png" width="23" height="23" title="Establecer como principal" class="m-img-b">
                </button>
                <img src="images/informacion.png" width="22" height="22" class="m-img-i" title="Información detallada...">
            </div>
            <div class="link mother-branch" style="opacity: 1 !important; left: 1070px; top: 325px; width: 20px; height: 12.5px;">
                <span class="first"></span>
                <span class="second"></span>
            </div>
            <!-- Tatarabuelo MPM -->
            <div class="caja_tatarabuelos" style="top: 360px; left: 1090px;">
                <span class="nom-tatarabuelos">Desconocido</span>
                <span class="ape-tatarabuelos"></span>
                <button class="simple">
                    <img src="images/pivotear.png" width="23" height="23" title="Establecer como principal" class="m-img-b">
                </button>
                <img src="images/informacion.png" width="22" height="22" class="m-img-i" title="Información detallada...">
            </div>
            <div class="link father-branch" style="opacity: 1 !important; left: 1070px; top: 372.5px; width: 20px; height: 12.5px;">
                <span class="first"></span>
                <span class="second"></span>
            </div>
            <!-- Tatarabuela MPM -->
            <div class="caja_tatarabuelos" style="top: 395px; left: 1090px;">
                <span class="nom-tatarabuelos">Desconocido</span>
                <span class="ape-tatarabuelos"></span>
                <button class="simple">
                    <img src="images/pivotear.png" width="23" height="23" title="Establecer como principal" class="m-img-b">
                </button>
                <img src="images/informacion.png" width="22" height="22" class="m-img-i" title="Información detallada...">
            </div>
            <div class="link mother-branch" style="opacity: 1 !important; left: 1070px; top: 395px; width: 20px; height: 12.5px;">
                <span class="first"></span>
                <span class="second"></span>
            </div>
    
            <!-- Tatarabuelo MMP -->
            <div class="caja_tatarabuelos" style="top: 430px; left: 1090px;">
                <span class="nom-tatarabuelos">Tomás</span>
                <span class="ape-tatarabuelos">Ledezma</span>
                <button class="simple">
                    <img src="images/pivotear.png" width="23" height="23" title="Establecer como principal" class="m-img-b">
                </button>
                <img src="images/informacion.png" width="22" height="22" class="m-img-i" title="Información detallada...">
            </div>
            <div class="link father-branch" style="opacity: 1 !important; left: 1070px; top: 442.5px; width: 20px; height: 12.5px;">
                <span class="first"></span>
                <span class="second"></span>
            </div>
            <!-- Tatarabuela MMP -->
            <div class="caja_tatarabuelos" style="top: 465px; left: 1090px;">
                <span class="nom-tatarabuelos">Rafaela</span>
                <span class="ape-tatarabuelos">Crespo</span>
                <button class="simple">
                    <img src="images/pivotear.png" width="23" height="23" title="Establecer como principal" class="m-img-b">
                </button>
                <img src="images/informacion.png" width="22" height="22" class="m-img-i" title="Información detallada...">
            </div>
            <div class="link mother-branch" style="opacity: 1 !important; left: 1070px; top:465px; width: 20px; height: 12.5px;">
                <span class="first"></span>
                <span class="second"></span>
            </div>
            <!-- Tatarabuelo MMM -->
            <div class="caja_tatarabuelos" style="top: 500px; left: 1090px;">
                <span class="nom-tatarabuelos">Carlos</span>
                <span class="ape-tatarabuelos">Christianze</span>
                <button class="simple">
                    <img src="images/pivotear.png" width="23" height="23" title="Establecer como principal" class="m-img-b">
                </button>
                <img src="images/informacion.png" width="22" height="22" class="m-img-i" title="Información detallada...">
            </div>
            <div class="link father-branch" style="opacity: 1 !important; left: 1070px; top: 512.5px; width: 20px; height: 12.5px;">
                <span class="first"></span>
                <span class="second"></span>
            </div>
            <!-- Tatarabuela MMM -->
            <div class="caja_tatarabuelos" style="top: 535px; left: 1090px;">
                <span class="nom-tatarabuelos">Teresa</span>
                <span class="ape-tatarabuelos">Azogue</span>
                <button class="simple">
                    <img src="images/pivotear.png" width="23" height="23" title="Establecer como principal" class="m-img-b">
                </button>
                <img src="images/informacion.png" width="22" height="22" class="m-img-i" title="Información detallada...">
            </div>
            <div class="link mother-branch" style="opacity: 1 !important; left: 1070px; top: 535px; width: 20px; height: 12.5px;">
                <span class="first"></span>
                <span class="second"></span>
            </div> --}}

            {{-- ********************************************************** --}}

            <!-- *** CLIENTE *** -->
            <div class="caja_per" style="top: 985px; left: 10px; ">
                <span class="encabezado">{{ GetPersona(1) }}</span>
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
                <span class="encabezado">{{ GetPersona($i) }}</span>
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
                <span class="encabezado">{{ GetPersona($i) }}</span>
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
                <span class="encabezado">{{ GetPersona($i) }}</span>
                <span class="nombres">{{ GetNombres($agclientes,$i) }}</span>
                <span class="apellidos">{{ GetApellidos($agclientes,$i) }}</span>
                <span class="nacimiento">{{ $mostraLN = GetLugarNac($agclientes,$i) }}</span>
                @if ($mostraLN)
                    <span class="texto">Lugar de nacimiento</span>    
                @endif
                <span class="vida" title="{{ GetVidaCompleta($agclientes,$i) }}">{{ GetVida($agclientes,$i) }}</span>
            </div>  
            @endfor

            <!-- *** TATARABUELOS *** -->
            @for ($i = 16; $i <=31; $i++)
            <div class="caja_per" style="top: {{ 10 + ($i-16)*130 }}px; left: 705px; ">
                <span class="encabezado">{{ GetPersona($i) }}</span>
                <span class="nombres">{{ GetNombres($agclientes,$i) }}</span>
                <span class="apellidos">{{ GetApellidos($agclientes,$i) }}</span>
                <span class="nacimiento">{{ $mostraLN = GetLugarNac($agclientes,$i) }}</span>
                @if ($mostraLN)
                    <span class="texto">Lugar de nacimiento</span>    
                @endif
                <span class="vida" title="{{ GetVidaCompleta($agclientes,$i) }}">{{ GetVida($agclientes,$i) }}</span>
            </div>  
            @endfor
        </div>
    </div>
</div>