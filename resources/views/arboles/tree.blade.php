@extends('adminlte::page')

@section('title', 'Vista Tree')

@section('content_header')

@stop

@section('content')


<x-app-layout>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <script src="//cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <button id="back-to-top"><i class="fa-solid fa-arrow-up"></i></button>
        @if (session('refresh'))
        <script>
            window.location.reload();
        </script>
    @endif
    @if(session("exito"))
        <script type="text/javascript">
            Swal.fire({
                icon: 'success',
                title: 'Contrato firmado',
                html: 'A continuación, puede continuar con el llenado del arbol',
                showConfirmButton: false,
                timer: 6000
            });
        </script>
    @endif
    @php $boxheight = 120; @endphp
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                <div class="p-2 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="lg:text-center">
                        <h2
                            class="text-sm font-bold cbgSefar tracking-wide pt-2 rounded-lg opacity-75 flex h-8 justify-center items-center">
                            Cliente:
                            @if(sizeof($cliente)>0)
                            {{ $cliente[0]["nombres"] . ' ' . $cliente[0]["apellidos"] . ' / ' . $cliente[0]["passport"] }}
                            {{ ' / ' . $cliente[0]["servicio"] }}
                            @else
                            @foreach ($columnasparatabla[0] as $persona)
                            {{$persona["Nombres"] . ' ' . $persona['Apellidos'] . ' / ' . $persona['IDCliente']}}
                            @endforeach
                            @endif
                        </h2>
                        <p class="mt-2 text-lg leading-8 font-extrabold tracking-tight text-gray-900 sm:text-4xl">
                            ÁRBOL GENEALÓGICO
                        </p>
                    </div>
                </div>

                <div class="container">
                    <div class="flex justify-between">
                        <div class="px-4 py-2 m-2">
                        <style>
                            .downloadgedcom{
                                background-color: rgb(22,43,27);
                            }
                            .downloadgedcom:hover{
                                background-color: rgb(247,176,52);
                            }
                        </style>

                        @can('descargarGedcom')

                        <div style="display:flex;">
                        <!--
                            <div class="px-4 py-2 m-2">
                                {{-- FAMILIARES --}}
                                <div class="justify-center">
                                    <label for="downloadgedcom" class="px-3 block text-sm font-medium text-gray-700"
                                        title="Descargar Gedcom">Descargar Gedcom</label>
                                    <a href="{{route('getGedcomCliente', $columnasparatabla[0][0])}}" class="csrSefar inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-600">
                                        <b>Descargar Gedcom</b>
                                    </a>
                                </div>
                            </div>
                        -->
                            <div class="px-4 py-2 m-2">
                                {{-- FAMILIARES --}}
                                <div class="justify-center">
                                    <label for="downloadgedcom" class="px-3 block text-sm font-medium text-gray-700"
                                        title="Descargar Gedcom">Descargar Excel</label>
                                    <a href="{{route('getExcelCliente', $columnasparatabla[0][0])}}" class="csrSefar inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-600">
                                        <b>Descargar Excel</b>
                                    </a>
                                </div>
                            </div>
                        </div>
                        @endcan

                        <div style="display: flex;">
                            <div class="px-4 py-2 m-2">
                                {{-- FAMILIARES --}}
                                <div class="justify-center">
                                    <label for="change_person" class="px-3 block text-sm font-medium text-gray-700"
                                        title="Ir a">Ir a</label>
                                    <select id="change_person" class="change_person" style="height: 36px; border-radius: 10px; font-size: 16px; padding: 0px 10px;">
                                        <option value="" selected disabled>Selecciona una persona</option>
                                        @foreach ($columnasparatabla  as $key1 => $columna)
                                            @foreach ($columna as $key2 => $persona)
                                                @if ($persona["showbtn"]==2)
                                                <option value="{{ $persona['id'] }}">{{$persona["Nombres"] . ' ' . $persona["Apellidos"]}}
                                                    @if($checkBtn == "si")
                                                        @if ($key1+$generacionBase == 1)
                                                            @if ($key2 == 0)
                                                                (Padre)
                                                            @else
                                                                (Madre)
                                                            @endif
                                                        @else
                                                            ({{$parentescos[$key1-2+$generacionBase][$persona['PersonaIDNew']]}})
                                                        @endif
                                                    @else
                                                        @if ($key1 == 0)
                                                            (Cliente)
                                                        @elseif ($key1 == 1)
                                                            @if ($key2 == 0)
                                                                (Padre)
                                                            @else
                                                                (Madre)
                                                            @endif
                                                        @else
                                                            @if(isset($parentescos[$key1 - 2 + $generacionBase][$persona['PersonaIDNew']]))
                                                                ({{ $parentescos[$key1 - 2 + $generacionBase][$persona['PersonaIDNew']] }})
                                                            @else
                                                                <script>
                                                                    window.location.reload();
                                                                </script>
                                                            @endif
                                                        @endif
                                                    @endif
                                                </option>
                                                @endif
                                            @endforeach
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="px-4 py-2 m-2">
                                <div class="justify-center">
                                    <label for="change_person" class="px-3 block text-sm font-medium text-gray-700"
                                        title="Zoom">Zoom</label>
                                    <button id="zoomIn" style="width: 36px; height: 36px; border-radius: 10px;" class="csrSefar"><i class="fa-solid fa-plus"></i></button>
                                    <button id="zoomOut" style="width: 36px; height: 36px; border-radius: 10px;" class="csrSefar"><i class="fa-solid fa-minus"></i></button>
                                </div>
                            </div>
                            @if ($checkBtn == "si")
                            <div class="px-4 py-2 m-2">
                                <div class="justify-center">
                                    <label for="change_person" class="px-3 block text-sm font-medium text-gray-700"
                                        title="Zoom">Regresar a Cliente</label>
                                    <button onclick="window.location.href='/tree/{{$columnasparatabla[0][0]["IDCliente"]}}'" style="height: 36px; border-radius: 10px; padding: 0px 10px;" class="csrSefar">Regresar a Cliente</button>
                                </div>
                            </div>
                            @endif
                            @if(auth()->user()->roles[0]->id != 5)
                            <div class="px-4 py-2 m-2">
                                <div class="justify-center">
                                    <label for="change_person" class="px-3 block text-sm font-medium text-gray-700"
                                        title="Zoom">Ir al COS</label>
                                    <button onclick="window.location.href='/users/{{$user->id}}/edit/'" style="height: 36px; border-radius: 10px; padding: 0px 10px;" class="csrSefar">Ir al COS</button>
                                </div>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>

                <div style="width: 100%; height: 80vh; overflow: auto;" id="containertree">
                    <div class="treecont_minimized" id="zoomableContent" style="position:relative;">
                        <div id="mylines" class="mylines"></div>
                        <div class="arbolflex">
                            <div style="width:20px">
                                <div style="width:20px">
                                </div>
                            </div>

                            {!! $htmlGenerado !!}

                            <div style="width:50px">
                                <div style="width:50px">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modaladdfamiliar" >
        <div class="contentmodaladdfamiliar">
            <div class="formtitle">
                <div class="flex w-full h-auto justify-center items-center" style="border-bottom: 1px rgba(0, 0, 0, 0.30) solid;">
                    <div class="flex w-10/12 h-auto py-3 justify-center items-center text-lg font-bold tituloform" style="color:rgba(55, 65, 81, 1);">
                        Añadir Familiar
                    </div>
                    <button class="flex w-1/12 h-auto justify-center cursor-pointer cerrarmodal" type="button">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#374151" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-x"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                    </button>
                </div>
            </div>
            <form action="{{route('agclientesnew.store')}}" method="POST" data-hs-cf-bound="true">
                @csrf
                <input name="Origen" type="hidden" value="arbol">
                <input name="Sexo" id="sexaddform" type="hidden">
                <input name="IDCliente" type="hidden" value="{{$columnasparatabla[0][0]["IDCliente"]}}" >
                <input name="id_hijo" id="id_hijo" type="hidden">
                <div class="container">
                    <div class="md:flex ms:flex-wrap">
                        <div class="px-1 py-2 m-2 flex-1">
                            <div>
                                <label for="Nombres" class="block text-sm font-medium text-gray-700">Nombres</label>
                                <input value="" type="text" name="Nombres" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                <div data-lastpass-icon-root="" style="position: relative !important; height: 0px !important; width: 0px !important; float: left !important;"></div>
                            </div>
                        </div>
                        <div class="px-1 py-2 m-2 flex-1">
                            <div>
                                <label for="Apellidos" class="block text-sm font-medium text-gray-700">Apellidos</label>
                                <input value="" type="text" name="Apellidos" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                            </div>
                        </div>
                    </div>
                    <div class="md:flex ms:flex-wrap">
                        <div class="px-1 py-2 m-2 flex-1">
                            <div>
                                <label for="NPasaporte" class="block text-sm font-medium text-gray-700">Pasaporte</label>
                                <input value="" type="text" name="NPasaporte" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                            </div>
                        </div>
                        <div class="px-1 py-2 m-2 flex-1">
                            <div>
                                <label for="PaisPasaporte" class="block text-sm font-medium text-gray-700">Pais de Doc. Id</label>
                                <select name="PaisPasaporte" autocomplete="country" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                    <option></option>
                                    <option>Argentina</option>
                                    <option>Australia</option>
                                    <option>Bolivia</option>
                                    <option>Brasil</option>
                                    <option>Bélgica</option>
                                    <option>Canadá</option>
                                    <option>Chile</option>
                                    <option>Colombia</option>
                                    <option>Costa Rica</option>
                                    <option>Cuba</option>
                                    <option>Curazao</option>
                                    <option>Ecuador</option>
                                    <option>EEUU</option>
                                    <option>El Salvador</option>
                                    <option>Emiratos Arabes Unidos</option>
                                    <option>España</option>
                                    <option>Francia</option>
                                    <option>Holanda</option>
                                    <option>Inglaterra</option>
                                    <option>Italia</option>
                                    <option>Líbano</option>
                                    <option>México</option>
                                    <option>Nicaragua</option>
                                    <option>Panamá</option>
                                    <option>Perú</option>
                                    <option>Puerto Rico</option>
                                    <option>República Dominicana</option>
                                    <option>Suecia</option>
                                    <option>Venezuela</option>
                                    <option>Honduras</option>
                                    <option>Rusia</option>
                                    <option>Alemania</option>
                                    <option>Gales</option>
                                    <option>Portugal</option>
                                    <option>Bulgaria</option>
                                    <option>Japón</option>
                                    <option>Polonia</option>
                                    <option>India</option>
                                    <option>Suiza</option>
                                    <option>Irán</option>
                                    <option>Uruguay</option>
                                    <option>Guatemala</option>
                                    <option>Argelia</option>
                                    <option>Siria</option>
                                    <option>Israel</option>
                                    <option>Kazajistán</option>
                                    <option>Jamaica</option>
                                </select>
                            </div>
                        </div>
                        <div class="px-1 py-2 m-2 flex-1">
                            <div>
                                <label for="NDocIdent" class="block text-sm font-medium text-gray-700">Doc. Identidad</label>
                                <input value="" type="text" name="NDocIdent" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                            </div>
                        </div>
                        <div class="px-1 py-2 m-2 flex-1">
                            <div>
                                <label for="PaisDocIdent" class="block text-sm font-medium text-gray-700">Pais de Doc. Id</label>
                                <select name="PaisDocIdent" autocomplete="country" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                    <option></option>
                                    <option>Argentina</option>
                                    <option>Australia</option>
                                    <option>Bolivia</option>
                                    <option>Brasil</option>
                                    <option>Bélgica</option>
                                    <option>Canadá</option>
                                    <option>Chile</option>
                                    <option>Colombia</option>
                                    <option>Costa Rica</option>
                                    <option>Cuba</option>
                                    <option>Curazao</option>
                                    <option>Ecuador</option>
                                    <option>EEUU</option>
                                    <option>El Salvador</option>
                                    <option>Emiratos Arabes Unidos</option>
                                    <option>España</option>
                                    <option>Francia</option>
                                    <option>Holanda</option>
                                    <option>Inglaterra</option>
                                    <option>Italia</option>
                                    <option>Líbano</option>
                                    <option>México</option>
                                    <option>Nicaragua</option>
                                    <option>Panamá</option>
                                    <option>Perú</option>
                                    <option>Puerto Rico</option>
                                    <option>República Dominicana</option>
                                    <option>Suecia</option>
                                    <option>Venezuela</option>
                                    <option>Honduras</option>
                                    <option>Rusia</option>
                                    <option>Alemania</option>
                                    <option>Gales</option>
                                    <option>Portugal</option>
                                    <option>Bulgaria</option>
                                    <option>Japón</option>
                                    <option>Polonia</option>
                                    <option>India</option>
                                    <option>Suiza</option>
                                    <option>Irán</option>
                                    <option>Uruguay</option>
                                    <option>Guatemala</option>
                                    <option>Argelia</option>
                                    <option>Siria</option>
                                    <option>Israel</option>
                                    <option>Kazajistán</option>
                                    <option>Jamaica</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="md:flex ms:flex-wrap">
                        <div class="px-1 py-2 m-2 flex-1/2">
                            <div>
                                <label for="AnhoNac" class="block text-sm font-medium text-gray-700" title="Año de nacimiento">Año Nac.</label>
                                <input value="" min="0" max="3000" type="number" name="AnhoNac" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                            </div>
                        </div>
                        <div class="px-1 py-2 m-2 flex-1/2">
                            <div>
                                <label for="MesNac" class="block text-sm font-medium text-gray-700" title="Mes de nacimiento">Mes Nac.</label>
                                <input value="" min="1" max="12" type="number" name="MesNac" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                            </div>
                        </div>
                        <div class="px-1 py-2 m-2 flex-1/2">
                            <div>
                                <label for="DiaNac" class="block text-sm font-medium text-gray-700" title="Día de nacimiento">Día Nac.</label>
                                <input value="" min="1" max="31" type="number" name="DiaNac" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                            </div>
                        </div>
                        <div class="px-1 py-2 m-2 flex-1">
                            <div>
                                <label for="LugarNac" class="block text-sm font-medium text-gray-700" title="Lugar de nacimiento">Lugar Nac.</label>
                                <input value="" type="text" name="LugarNac" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                            </div>
                        </div>
                        <div class="px-1 py-2 m-2 flex-1">
                            <div>
                                <label for="PaisNac" class="block text-sm font-medium text-gray-700" title="País de nacimiento">País Nac.</label>
                                <select name="PaisNac" autocomplete="country" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                    <option></option>
                                    <option>Argentina</option>
                                    <option>Australia</option>
                                    <option>Bolivia</option>
                                    <option>Brasil</option>
                                    <option>Bélgica</option>
                                    <option>Canadá</option>
                                    <option>Chile</option>
                                    <option>Colombia</option>
                                    <option>Costa Rica</option>
                                    <option>Cuba</option>
                                    <option>Curazao</option>
                                    <option>Ecuador</option>
                                    <option>EEUU</option>
                                    <option>El Salvador</option>
                                    <option>Emiratos Arabes Unidos</option>
                                    <option>España</option>
                                    <option>Francia</option>
                                    <option>Holanda</option>
                                    <option>Inglaterra</option>
                                    <option>Italia</option>
                                    <option>Líbano</option>
                                    <option>México</option>
                                    <option>Nicaragua</option>
                                    <option>Panamá</option>
                                    <option>Perú</option>
                                    <option>Puerto Rico</option>
                                    <option>República Dominicana</option>
                                    <option>Suecia</option>
                                    <option>Venezuela</option>

                                    <option>Honduras</option>
                                    <option>Rusia</option>
                                    <option>Alemania</option>
                                    <option>Gales</option>
                                    <option>Portugal</option>
                                    <option>Bulgaria</option>
                                    <option>Japón</option>
                                    <option>Polonia</option>
                                    <option>India</option>
                                    <option>Suiza</option>
                                    <option>Irán</option>
                                    <option>Uruguay</option>
                                    <option>Guatemala</option>
                                    <option>Argelia</option>
                                    <option>Siria</option>
                                    <option>Israel</option>
                                    <option>Kazajistán</option>
                                    <option>Jamaica</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="md:flex ms:flex-wrap">
                        <div class="px-1 py-2 m-2 flex-1/2">
                            <div>
                                <label for="AnhoBtzo" class="block text-sm font-medium text-gray-700" title="Año de bautizo">Año Btzo.</label>
                                <input value="" min="0" max="3000" type="number" name="AnhoBtzo" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                            </div>
                        </div>
                        <div class="px-1 py-2 m-2 flex-1/2">
                            <div>
                                <label for="MesBtzo" class="block text-sm font-medium text-gray-700" title="Mes de bautizo">Mes Btzo.</label>
                                <input value="" min="1" max="12" type="number" name="MesBtzo" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                            </div>
                        </div>
                        <div class="px-1 py-2 m-2 flex-1/2">
                            <div>
                                <label for="DiaBtzo" class="block text-sm font-medium text-gray-700" title="Día de bautizo">Día Btzo.</label>
                                <input value="" min="1" max="31" type="number" name="DiaBtzo" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                            </div>
                        </div>
                        <div class="px-1 py-2 m-2 flex-1">
                            <div>
                                <label for="LugarBtzo" class="block text-sm font-medium text-gray-700" title="Lugar de bautizo">Lugar Btzo.</label>
                                <input value="" type="text" name="LugarBtzo" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                            </div>
                        </div>
                        <div class="px-1 py-2 m-2 flex-1">
                            <div>
                                <label for="PaisBtzo" class="block text-sm font-medium text-gray-700" title="País de bautizo">País Btzo.</label>
                                <select name="PaisBtzo" autocomplete="country" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                    <option></option>
                                    <option>Argentina</option>
                                    <option>Australia</option>
                                    <option>Bolivia</option>
                                    <option>Brasil</option>
                                    <option>Bélgica</option>
                                    <option>Canadá</option>
                                    <option>Chile</option>
                                    <option>Colombia</option>
                                    <option>Costa Rica</option>
                                    <option>Cuba</option>
                                    <option>Curazao</option>
                                    <option>Ecuador</option>
                                    <option>EEUU</option>
                                    <option>El Salvador</option>
                                    <option>Emiratos Arabes Unidos</option>
                                    <option>España</option>
                                    <option>Francia</option>
                                    <option>Holanda</option>
                                    <option>Inglaterra</option>
                                    <option>Italia</option>
                                    <option>Líbano</option>
                                    <option>México</option>
                                    <option>Nicaragua</option>
                                    <option>Panamá</option>
                                    <option>Perú</option>
                                    <option>Puerto Rico</option>
                                    <option>República Dominicana</option>
                                    <option>Suecia</option>
                                    <option>Venezuela</option>

                                    <option>Honduras</option>
                                    <option>Rusia</option>
                                    <option>Alemania</option>
                                    <option>Gales</option>
                                    <option>Portugal</option>
                                    <option>Bulgaria</option>
                                    <option>Japón</option>
                                    <option>Polonia</option>
                                    <option>India</option>
                                    <option>Suiza</option>
                                    <option>Irán</option>
                                    <option>Uruguay</option>
                                    <option>Guatemala</option>
                                    <option>Argelia</option>
                                    <option>Siria</option>
                                    <option>Israel</option>
                                    <option>Kazajistán</option>
                                    <option>Jamaica</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="md:flex ms:flex-wrap">
                        <div class="px-1 py-2 m-2 flex-1/2">
                            <div>
                                <label for="AnhoMatr" class="block text-sm font-medium text-gray-700" title="Año de matrimonio">Año Matr.</label>
                                <input value="" min="0" max="3000" type="number" name="AnhoMatr" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                            </div>
                        </div>
                        <div class="px-1 py-2 m-2 flex-1/2">
                            <div>
                                <label for="MesMatr" class="block text-sm font-medium text-gray-700" title="Mes de matrimonio">Mes Matr.</label>
                                <input value="" min="1" max="12" type="number" name="MesMatr" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                            </div>
                        </div>
                        <div class="px-1 py-2 m-2 flex-1/2">
                            <div>
                                <label for="DiaMatr" class="block text-sm font-medium text-gray-700" title="Día de matrimonio">Día Matr.</label>
                                <input value="" min="1" max="31" type="number" name="DiaMatr" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                            </div>
                        </div>
                        <div class="px-1 py-2 m-2 flex-1">
                            <div>
                                <label for="LugarMatr" class="block text-sm font-medium text-gray-700" title="Lugar de matrimonio">Lugar Matr.</label>
                                <input value="" type="text" name="LugarMatr" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                            </div>
                        </div>
                        <div class="px-1 py-2 m-2 flex-1">
                            <div>
                                <label for="PaisMatr" class="block text-sm font-medium text-gray-700" title="País de matrimonio">País Matr.</label>
                                <select name="PaisMatr" autocomplete="country" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                    <option></option>
                                    <option>Argentina</option>
                                    <option>Australia</option>
                                    <option>Bolivia</option>
                                    <option>Brasil</option>
                                    <option>Bélgica</option>
                                    <option>Canadá</option>
                                    <option>Chile</option>
                                    <option>Colombia</option>
                                    <option>Costa Rica</option>
                                    <option>Cuba</option>
                                    <option>Curazao</option>
                                    <option>Ecuador</option>
                                    <option>EEUU</option>
                                    <option>El Salvador</option>
                                    <option>Emiratos Arabes Unidos</option>
                                    <option>España</option>
                                    <option>Francia</option>
                                    <option>Holanda</option>
                                    <option>Inglaterra</option>
                                    <option>Italia</option>
                                    <option>Líbano</option>
                                    <option>México</option>
                                    <option>Nicaragua</option>
                                    <option>Panamá</option>
                                    <option>Perú</option>
                                    <option>Puerto Rico</option>
                                    <option>República Dominicana</option>
                                    <option>Suecia</option>
                                    <option>Venezuela</option>

                                    <option>Honduras</option>
                                    <option>Rusia</option>
                                    <option>Alemania</option>
                                    <option>Gales</option>
                                    <option>Portugal</option>
                                    <option>Bulgaria</option>
                                    <option>Japón</option>
                                    <option>Polonia</option>
                                    <option>India</option>
                                    <option>Suiza</option>
                                    <option>Irán</option>
                                    <option>Uruguay</option>
                                    <option>Guatemala</option>
                                    <option>Argelia</option>
                                    <option>Siria</option>
                                    <option>Israel</option>
                                    <option>Kazajistán</option>
                                    <option>Jamaica</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="md:flex ms:flex-wrap">
                        <div class="px-1 py-2 m-2 flex-1/2">
                            <div>
                                <label for="AnhoDef" class="block text-sm font-medium text-gray-700" title="Año de defunción">Año Def.</label>
                                <input value="" min="0" max="3000" type="number" name="AnhoDef" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                            </div>
                        </div>
                        <div class="px-1 py-2 m-2 flex-1/2">
                            <div>
                                <label for="MesDef" class="block text-sm font-medium text-gray-700" title="Mes de defunción">Mes Def.</label>
                                <input value="" min="1" max="12" type="number" name="MesDef" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                            </div>
                        </div>
                        <div class="px-1 py-2 m-2 flex-1/2">
                            <div>
                                <label for="DiaDef" class="block text-sm font-medium text-gray-700" title="Día de defunción">Día Def.</label>
                                <input value="" min="1" max="31" type="number" name="DiaDef" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                            </div>
                        </div>
                        <div class="px-1 py-2 m-2 flex-1">
                            <div>
                                <label for="LugarDef" class="block text-sm font-medium text-gray-700" title="Lugar de defunción">Lugar Def.</label>
                                <input value="" type="text" name="LugarDef" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                            </div>
                        </div>
                        <div class="px-1 py-2 m-2 flex-1">
                            <div>
                                <label for="PaisDef" class="block text-sm font-medium text-gray-700" title="País de defunción">País Def.</label>
                                <select name="PaisDef" autocomplete="country" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                    <option></option>
                                    <option>Argentina</option>
                                    <option>Australia</option>
                                    <option>Bolivia</option>
                                    <option>Brasil</option>
                                    <option>Bélgica</option>
                                    <option>Canadá</option>
                                    <option>Chile</option>
                                    <option>Colombia</option>
                                    <option>Costa Rica</option>
                                    <option>Cuba</option>
                                    <option>Curazao</option>
                                    <option>Ecuador</option>
                                    <option>EEUU</option>
                                    <option>El Salvador</option>
                                    <option>Emiratos Arabes Unidos</option>
                                    <option>España</option>
                                    <option>Francia</option>
                                    <option>Holanda</option>
                                    <option>Inglaterra</option>
                                    <option>Italia</option>
                                    <option>Líbano</option>
                                    <option>México</option>
                                    <option>Nicaragua</option>
                                    <option>Panamá</option>
                                    <option>Perú</option>
                                    <option>Puerto Rico</option>
                                    <option>República Dominicana</option>
                                    <option>Suecia</option>
                                    <option>Venezuela</option>
                                    <option>Honduras</option>
                                    <option>Rusia</option>
                                    <option>Alemania</option>
                                    <option>Gales</option>
                                    <option>Portugal</option>
                                    <option>Bulgaria</option>
                                    <option>Japón</option>
                                    <option>Polonia</option>
                                    <option>India</option>
                                    <option>Suiza</option>
                                    <option>Irán</option>
                                    <option>Uruguay</option>
                                    <option>Guatemala</option>
                                    <option>Argelia</option>
                                    <option>Siria</option>
                                    <option>Israel</option>
                                    <option>Kazajistán</option>
                                    <option>Jamaica</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    @if(auth()->user() && auth()->user()->hasRole(['Administrador', 'Genealogista', 'Documentalista']))
                    <div class="md:flex ms:flex-wrap">
                        <div class="px-1 py-2 m-2 flex-1">
                            <div>
                                <label for="Observaciones" class="block text-sm font-medium text-gray-700" title="Observaciones">Observaciones</label>
                                <textarea name="Observaciones" rows="3" class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 mt-1 block w-full sm:text-sm border-gray-300 rounded-md" placeholder="Observaciones"></textarea>
                            </div>
                        </div>
                    </div>
                    @endif
                    <div class="px-4 py-3 w-full text-right sm:px-6">
                        <button type="button" class="cerrarmodal cfrSefar inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            Cancelar
                        </button>
                        <button type="submit" class="cfrSefar inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            Añadir persona
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="modaleditfamiliar" >
        <div class="contentmodaladdfamiliar">
            <div class="formtitle">
                <div class="flex w-full h-auto justify-center items-center" style="border-bottom: 1px rgba(0, 0, 0, 0.30) solid;">
                    <div class="flex w-10/12 h-auto py-3 justify-center items-center text-lg font-bold tituloform" style="color:rgba(55, 65, 81, 1);">
                        Editar Familiar
                    </div>
                    <button class="flex w-1/12 h-auto justify-center cursor-pointer cerrarmodal" type="button">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#374151" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-x"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                    </button>
                </div>
            </div>
            <form action="{{route('agclientesnew.update')}}" method="POST" data-hs-cf-bound="true">
                @csrf
                <input name="id" id="editid" type="hidden">
                <div class="container">
                    <div class="md:flex ms:flex-wrap">
                        <div class="px-1 py-2 m-2 flex-1">
                            <div>
                                <label for="Nombres" class="block text-sm font-medium text-gray-700">Nombres</label>
                                <input value="" id="editnombres" type="text" name="Nombres" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                            </div>
                        </div>
                        <div class="px-1 py-2 m-2 flex-1">
                            <div>
                                <label for="Apellidos"  class="block text-sm font-medium text-gray-700">Apellidos</label>
                                <input value="" id="editApellidos" type="text" name="Apellidos" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                            </div>
                        </div>
                    </div>
                    @php
                        $isClient = auth()->user()->role === 'Cliente'; // Asegúrate de que 'Cliente' coincide con el rol real
                    @endphp
                    <div class="md:flex ms:flex-wrap">
                        <div class="px-1 py-2 m-2 flex-1">
                            <div>
                                <label for="NPasaporte" class="block text-sm font-medium text-gray-700">Pasaporte</label>
                                <input value="" id="editNPasaporte" type="text" @if($isClient) readonly @endif name="NPasaporte" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                            </div>
                        </div>
                        <div class="px-1 py-2 m-2 flex-1">
                            <div>
                                <label for="PaisPasaporte" class="block text-sm font-medium text-gray-700">Pais de Doc. Id</label>
                                <select name="PaisPasaporte" id="editPaisPasaporte" autocomplete="country" @if($isClient) readonly @endif class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                    <option></option>
                                    <option>Argentina</option>
                                    <option>Australia</option>
                                    <option>Bolivia</option>
                                    <option>Brasil</option>
                                    <option>Bélgica</option>
                                    <option>Canadá</option>
                                    <option>Chile</option>
                                    <option>Colombia</option>
                                    <option>Costa Rica</option>
                                    <option>Cuba</option>
                                    <option>Curazao</option>
                                    <option>Ecuador</option>
                                    <option>EEUU</option>
                                    <option>El Salvador</option>
                                    <option>Emiratos Arabes Unidos</option>
                                    <option>España</option>
                                    <option>Francia</option>
                                    <option>Holanda</option>
                                    <option>Inglaterra</option>
                                    <option>Italia</option>
                                    <option>Líbano</option>
                                    <option>México</option>
                                    <option>Nicaragua</option>
                                    <option>Panamá</option>
                                    <option>Perú</option>
                                    <option>Puerto Rico</option>
                                    <option>República Dominicana</option>
                                    <option>Suecia</option>
                                    <option>Venezuela</option>
                                    <option>Honduras</option>
                                    <option>Rusia</option>
                                    <option>Alemania</option>
                                    <option>Gales</option>
                                    <option>Portugal</option>
                                    <option>Bulgaria</option>
                                    <option>Japón</option>
                                    <option>Polonia</option>
                                    <option>India</option>
                                    <option>Suiza</option>
                                    <option>Irán</option>
                                    <option>Uruguay</option>
                                    <option>Guatemala</option>
                                    <option>Argelia</option>
                                    <option>Siria</option>
                                    <option>Israel</option>
                                    <option>Kazajistán</option>
                                    <option>Jamaica</option>
                                </select>
                            </div>
                        </div>
                        <div class="px-1 py-2 m-2 flex-1">
                            <div>
                                <label for="NDocIdent" class="block text-sm font-medium text-gray-700">Doc. Identidad</label>
                                <input value="" id="editNDocIdent" type="text" @if($isClient) readonly @endif name="NDocIdent" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                            </div>
                        </div>
                        <div class="px-1 py-2 m-2 flex-1">
                            <div>
                                <label for="PaisDocIdent" class="block text-sm font-medium text-gray-700">Pais de Doc. Id</label>
                                <select name="PaisDocIdent" id="editPaisDocIdent" @if($isClient) readonly @endif autocomplete="country" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                    <option></option>
                                    <option>Argentina</option>
                                    <option>Australia</option>
                                    <option>Bolivia</option>
                                    <option>Brasil</option>
                                    <option>Bélgica</option>
                                    <option>Canadá</option>
                                    <option>Chile</option>
                                    <option>Colombia</option>
                                    <option>Costa Rica</option>
                                    <option>Cuba</option>
                                    <option>Curazao</option>
                                    <option>Ecuador</option>
                                    <option>EEUU</option>
                                    <option>El Salvador</option>
                                    <option>Emiratos Arabes Unidos</option>
                                    <option>España</option>
                                    <option>Francia</option>
                                    <option>Holanda</option>
                                    <option>Inglaterra</option>
                                    <option>Italia</option>
                                    <option>Líbano</option>
                                    <option>México</option>
                                    <option>Nicaragua</option>
                                    <option>Panamá</option>
                                    <option>Perú</option>
                                    <option>Puerto Rico</option>
                                    <option>República Dominicana</option>
                                    <option>Suecia</option>
                                    <option>Venezuela</option>
                                    <option>Honduras</option>
                                    <option>Rusia</option>
                                    <option>Alemania</option>
                                    <option>Gales</option>
                                    <option>Portugal</option>
                                    <option>Bulgaria</option>
                                    <option>Japón</option>
                                    <option>Polonia</option>
                                    <option>India</option>
                                    <option>Suiza</option>
                                    <option>Irán</option>
                                    <option>Uruguay</option>
                                    <option>Guatemala</option>
                                    <option>Argelia</option>
                                    <option>Siria</option>
                                    <option>Israel</option>
                                    <option>Kazajistán</option>
                                    <option>Jamaica</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="md:flex ms:flex-wrap">
                        <div class="px-1 py-2 m-2 flex-1/2">
                            <div>
                                <label for="AnhoNac"  class="block text-sm font-medium text-gray-700" title="Año de nacimiento">Año Nac.</label>
                                <input value="" id="editAnhoNac" min="0" max="3000" type="number" name="AnhoNac" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                            </div>
                        </div>
                        <div class="px-1 py-2 m-2 flex-1/2">
                            <div>
                                <label for="MesNac" class="block text-sm font-medium text-gray-700" title="Mes de nacimiento">Mes Nac.</label>
                                <input value="" id="editMesNac" min="1" max="12" type="number" name="MesNac" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                            </div>
                        </div>
                        <div class="px-1 py-2 m-2 flex-1/2">
                            <div>
                                <label for="DiaNac" class="block text-sm font-medium text-gray-700" title="Día de nacimiento">Día Nac.</label>
                                <input value="" id="editDiaNac" min="1" max="31" type="number" name="DiaNac" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                            </div>
                        </div>
                        <div class="px-1 py-2 m-2 flex-1">
                            <div>
                                <label for="LugarNac" class="block text-sm font-medium text-gray-700" title="Lugar de nacimiento">Lugar Nac.</label>
                                <input value="" id="editLugarNac" type="text" name="LugarNac" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                            </div>
                        </div>
                        <div class="px-1 py-2 m-2 flex-1">
                            <div>
                                <label for="PaisNac" class="block text-sm font-medium text-gray-700" title="País de nacimiento">País Nac.</label>
                                <select name="PaisNac" id="editPaisNac" autocomplete="country" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                    <option></option>
                                    <option>Argentina</option>
                                    <option>Australia</option>
                                    <option>Bolivia</option>
                                    <option>Brasil</option>
                                    <option>Bélgica</option>
                                    <option>Canadá</option>
                                    <option>Chile</option>
                                    <option>Colombia</option>
                                    <option>Costa Rica</option>
                                    <option>Cuba</option>
                                    <option>Curazao</option>
                                    <option>Ecuador</option>
                                    <option>EEUU</option>
                                    <option>El Salvador</option>
                                    <option>Emiratos Arabes Unidos</option>
                                    <option>España</option>
                                    <option>Francia</option>
                                    <option>Holanda</option>
                                    <option>Inglaterra</option>
                                    <option>Italia</option>
                                    <option>Líbano</option>
                                    <option>México</option>
                                    <option>Nicaragua</option>
                                    <option>Panamá</option>
                                    <option>Perú</option>
                                    <option>Puerto Rico</option>
                                    <option>República Dominicana</option>
                                    <option>Suecia</option>
                                    <option>Venezuela</option>
                                    <option>Honduras</option>
                                    <option>Rusia</option>
                                    <option>Alemania</option>
                                    <option>Gales</option>
                                    <option>Portugal</option>
                                    <option>Bulgaria</option>
                                    <option>Japón</option>
                                    <option>Polonia</option>
                                    <option>India</option>
                                    <option>Suiza</option>
                                    <option>Irán</option>
                                    <option>Uruguay</option>
                                    <option>Guatemala</option>
                                    <option>Argelia</option>
                                    <option>Siria</option>
                                    <option>Israel</option>
                                    <option>Kazajistán</option>
                                    <option>Jamaica</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="md:flex ms:flex-wrap">
                        <div class="px-1 py-2 m-2 flex-1/2">
                            <div>
                                <label for="AnhoBtzo" class="block text-sm font-medium text-gray-700" title="Año de bautizo">Año Btzo.</label>
                                <input value="" id="editAnhoBtzo" min="0" max="3000" type="number" name="AnhoBtzo" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                            </div>
                        </div>
                        <div class="px-1 py-2 m-2 flex-1/2">
                            <div>
                                <label for="MesBtzo" class="block text-sm font-medium text-gray-700" title="Mes de bautizo">Mes Btzo.</label>
                                <input value="" id="editMesBtzo" min="1" max="12" type="number" name="MesBtzo" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                            </div>
                        </div>
                        <div class="px-1 py-2 m-2 flex-1/2">
                            <div>
                                <label for="DiaBtzo" class="block text-sm font-medium text-gray-700" title="Día de bautizo">Día Btzo.</label>
                                <input value="" id="editDiaBtzo" min="1" max="31" type="number" name="DiaBtzo" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                            </div>
                        </div>
                        <div class="px-1 py-2 m-2 flex-1">
                            <div>
                                <label for="LugarBtzo" class="block text-sm font-medium text-gray-700" title="Lugar de bautizo">Lugar Btzo.</label>
                                <input value="" id="editLugarBtzo" type="text" name="LugarBtzo" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                            </div>
                        </div>
                        <div class="px-1 py-2 m-2 flex-1">
                            <div>
                                <label for="PaisBtzo" class="block text-sm font-medium text-gray-700" title="País de bautizo">País Btzo.</label>
                                <select name="PaisBtzo" id="editPaisBtzo" autocomplete="country" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                    <option></option>
                                    <option>Argentina</option>
                                    <option>Australia</option>
                                    <option>Bolivia</option>
                                    <option>Brasil</option>
                                    <option>Bélgica</option>
                                    <option>Canadá</option>
                                    <option>Chile</option>
                                    <option>Colombia</option>
                                    <option>Costa Rica</option>
                                    <option>Cuba</option>
                                    <option>Curazao</option>
                                    <option>Ecuador</option>
                                    <option>EEUU</option>
                                    <option>El Salvador</option>
                                    <option>Emiratos Arabes Unidos</option>
                                    <option>España</option>
                                    <option>Francia</option>
                                    <option>Holanda</option>
                                    <option>Inglaterra</option>
                                    <option>Italia</option>
                                    <option>Líbano</option>
                                    <option>México</option>
                                    <option>Nicaragua</option>
                                    <option>Panamá</option>
                                    <option>Perú</option>
                                    <option>Puerto Rico</option>
                                    <option>República Dominicana</option>
                                    <option>Suecia</option>
                                    <option>Venezuela</option>

                                    <option>Honduras</option>
                                    <option>Rusia</option>
                                    <option>Alemania</option>
                                    <option>Gales</option>
                                    <option>Portugal</option>
                                    <option>Bulgaria</option>
                                    <option>Japón</option>
                                    <option>Polonia</option>
                                    <option>India</option>
                                    <option>Suiza</option>
                                    <option>Irán</option>
                                    <option>Uruguay</option>
                                    <option>Guatemala</option>
                                    <option>Argelia</option>
                                    <option>Siria</option>
                                    <option>Israel</option>
                                    <option>Kazajistán</option>
                                    <option>Jamaica</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="md:flex ms:flex-wrap">
                        <div class="px-1 py-2 m-2 flex-1/2">
                            <div>
                                <label for="AnhoMatr" class="block text-sm font-medium text-gray-700" title="Año de matrimonio">Año Matr.</label>
                                <input value="" id="editAnhoMatr" min="0" max="3000" type="number" name="AnhoMatr" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                            </div>
                        </div>
                        <div class="px-1 py-2 m-2 flex-1/2">
                            <div>
                                <label for="MesMatr" class="block text-sm font-medium text-gray-700" title="Mes de matrimonio">Mes Matr.</label>
                                <input value="" id="editMesMatr" min="1" max="12" type="number" name="MesMatr" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                            </div>
                        </div>
                        <div class="px-1 py-2 m-2 flex-1/2">
                            <div>
                                <label for="DiaMatr" class="block text-sm font-medium text-gray-700" title="Día de matrimonio">Día Matr.</label>
                                <input value="" id="editDiaMatr" min="1" max="31" type="number" name="DiaMatr" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                            </div>
                        </div>
                        <div class="px-1 py-2 m-2 flex-1">
                            <div>
                                <label for="LugarMatr" class="block text-sm font-medium text-gray-700" title="Lugar de matrimonio">Lugar Matr.</label>
                                <input value="" id="editLugarMatr" type="text" name="LugarMatr" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                            </div>
                        </div>
                        <div class="px-1 py-2 m-2 flex-1">
                            <div>
                                <label for="PaisMatr" class="block text-sm font-medium text-gray-700" title="País de matrimonio">País Matr.</label>
                                <select name="PaisMatr" id="editPaisMatr" autocomplete="country" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                    <option></option>
                                    <option>Argentina</option>
                                    <option>Australia</option>
                                    <option>Bolivia</option>
                                    <option>Brasil</option>
                                    <option>Bélgica</option>
                                    <option>Canadá</option>
                                    <option>Chile</option>
                                    <option>Colombia</option>
                                    <option>Costa Rica</option>
                                    <option>Cuba</option>
                                    <option>Curazao</option>
                                    <option>Ecuador</option>
                                    <option>EEUU</option>
                                    <option>El Salvador</option>
                                    <option>Emiratos Arabes Unidos</option>
                                    <option>España</option>
                                    <option>Francia</option>
                                    <option>Holanda</option>
                                    <option>Inglaterra</option>
                                    <option>Italia</option>
                                    <option>Líbano</option>
                                    <option>México</option>
                                    <option>Nicaragua</option>
                                    <option>Panamá</option>
                                    <option>Perú</option>
                                    <option>Puerto Rico</option>
                                    <option>República Dominicana</option>
                                    <option>Suecia</option>
                                    <option>Venezuela</option>

                                    <option>Honduras</option>
                                    <option>Rusia</option>
                                    <option>Alemania</option>
                                    <option>Gales</option>
                                    <option>Portugal</option>
                                    <option>Bulgaria</option>
                                    <option>Japón</option>
                                    <option>Polonia</option>
                                    <option>India</option>
                                    <option>Suiza</option>
                                    <option>Irán</option>
                                    <option>Uruguay</option>
                                    <option>Guatemala</option>
                                    <option>Argelia</option>
                                    <option>Siria</option>
                                    <option>Israel</option>
                                    <option>Kazajistán</option>
                                    <option>Jamaica</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="md:flex ms:flex-wrap">
                        <div class="px-1 py-2 m-2 flex-1/2">
                            <div>
                                <label for="AnhoDef" class="block text-sm font-medium text-gray-700" title="Año de defunción">Año Def.</label>
                                <input value="" id="editAnhoDef" min="0" max="3000" type="number" name="AnhoDef" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                            </div>
                        </div>
                        <div class="px-1 py-2 m-2 flex-1/2">
                            <div>
                                <label for="MesDef" class="block text-sm font-medium text-gray-700" title="Mes de defunción">Mes Def.</label>
                                <input value="" id="editMesDef"  min="1" max="12" type="number" name="MesDef" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                            </div>
                        </div>
                        <div class="px-1 py-2 m-2 flex-1/2">
                            <div>
                                <label for="DiaDef" class="block text-sm font-medium text-gray-700" title="Día de defunción">Día Def.</label>
                                <input value="" id="editDiaDef" min="1" max="31" type="number" name="DiaDef" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                            </div>
                        </div>
                        <div class="px-1 py-2 m-2 flex-1">
                            <div>
                                <label for="LugarDef" class="block text-sm font-medium text-gray-700" title="Lugar de defunción">Lugar Def.</label>
                                <input value="" id="editLugarDef" type="text" name="LugarDef" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                            </div>
                        </div>
                        <div class="px-1 py-2 m-2 flex-1">
                            <div>
                                <label for="PaisDef" class="block text-sm font-medium text-gray-700" title="País de defunción">País Def.</label>
                                <select id="editPaisDef" name="PaisDef" autocomplete="country" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                    <option></option>
                                    <option>Argentina</option>
                                    <option>Australia</option>
                                    <option>Bolivia</option>
                                    <option>Brasil</option>
                                    <option>Bélgica</option>
                                    <option>Canadá</option>
                                    <option>Chile</option>
                                    <option>Colombia</option>
                                    <option>Costa Rica</option>
                                    <option>Cuba</option>
                                    <option>Curazao</option>
                                    <option>Ecuador</option>
                                    <option>EEUU</option>
                                    <option>El Salvador</option>
                                    <option>Emiratos Arabes Unidos</option>
                                    <option>España</option>
                                    <option>Francia</option>
                                    <option>Holanda</option>
                                    <option>Inglaterra</option>
                                    <option>Italia</option>
                                    <option>Líbano</option>
                                    <option>México</option>
                                    <option>Nicaragua</option>
                                    <option>Panamá</option>
                                    <option>Perú</option>
                                    <option>Puerto Rico</option>
                                    <option>República Dominicana</option>
                                    <option>Suecia</option>
                                    <option>Venezuela</option>
                                    <option>Honduras</option>
                                    <option>Rusia</option>
                                    <option>Alemania</option>
                                    <option>Gales</option>
                                    <option>Portugal</option>
                                    <option>Bulgaria</option>
                                    <option>Japón</option>
                                    <option>Polonia</option>
                                    <option>India</option>
                                    <option>Suiza</option>
                                    <option>Irán</option>
                                    <option>Uruguay</option>
                                    <option>Guatemala</option>
                                    <option>Argelia</option>
                                    <option>Siria</option>
                                    <option>Israel</option>
                                    <option>Kazajistán</option>
                                    <option>Jamaica</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    @if(auth()->user() && auth()->user()->hasRole(['Administrador', 'Genealogista', 'Documentalista']))
                    <div class="md:flex ms:flex-wrap">
                        <div class="px-1 py-2 m-2 flex-1">
                            <div>
                                <label for="Observaciones" class="block text-sm font-medium text-gray-700" title="Observaciones">Observaciones</label>
                                <textarea name="Observaciones" id="editObservaciones" rows="3" class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 mt-1 block w-full sm:text-sm border-gray-300 rounded-md" placeholder="Observaciones"></textarea>
                            </div>
                        </div>
                    </div>
                    @endif
                    <div class="px-4 py-3 w-full text-right sm:px-6">
                        <button type="button" class="cerrarmodal cfrSefar inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            Cancelar
                        </button>
                        <button type="submit" class="cfrSefar inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            Editar persona
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="modalverarchivos" >
        <div class="contentmodaladdfamiliar">
            <div class="formtitle">
                <div class="flex w-full h-auto justify-center items-center" style="border-bottom: 1px rgba(0, 0, 0, 0.30) solid;">
                    <div class="flex w-10/12 h-auto py-3 justify-center items-center text-lg font-bold tituloform" style="color:rgba(55, 65, 81, 1);">
                        Archivos
                    </div>
                    <button class="flex w-1/12 h-auto justify-center cursor-pointer cerrarmodal" type="button">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#374151" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-x"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                    </button>
                </div>
            </div>
            <input type="hidden" id="f_IDCliente" value="{{$columnasparatabla[0][0]["IDCliente"]}}"/>
            <input type="hidden" id="f_IDPersonaNew">
            <div class="contentfiles">

            </div>
            <div class="px-4 py-3 w-full text-right sm:px-6">
                <button type="button" id="addNewFile" class="cfrSefar inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Agregar Archivo
                </button>
            </div>
        </div>
    </div>

    <div class="modaladdarchivo" >
        <div class="contentmodaladdfamiliar">
            <div class="formtitle">
                <div class="flex w-full h-auto justify-center items-center" style="border-bottom: 1px rgba(0, 0, 0, 0.30) solid;">
                    <div class="flex w-10/12 h-auto py-3 justify-center items-center text-lg font-bold tituloform" style="color:rgba(55, 65, 81, 1);">
                        Añadir Archivo
                    </div>
                    <button class="flex w-1/12 h-auto justify-center cursor-pointer cerrarmodalarchivo" type="button">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#374151" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-x"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                    </button>
                </div>
            </div>
            <form id="subirArchivo" action="{{route('storefile')}}" method="POST" enctype="multipart/form-data">
                @csrf
                <input name="IDPersonaNew" id="up_IDPersonaNew" type="hidden">
                <input name="IDCliente" id="up_IDCliente" type="hidden" >
                <div class="container">
                    <div class="md:flex ms:flex-wrap">
                        <div class="px-1 py-2 m-2 flex-1">
                            <div>
                                <label for="archivo" class="block text-sm font-medium text-gray-700">Archivo</label>
                                <input type="file" name="archivo" id="add_archivo" class="mt-1 block text-sm text-gray-900 border border-gray-300 rounded-md shadow-sm w-full cursor-pointer focus:outline-none focus:border-indigo-500 focus:ring-indigo-500" style="height: 38px;">
                            </div>
                        </div>
                        <div class="px-1 py-2 m-2 flex-1">
                            <div>
                                <label for="tipo" class="block text-sm font-medium text-gray-700" title="Tipo">Tipo</label>
                                <select name="tipo" id="add_tipo" autocomplete="country" class="mt-1 block py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm w-full focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                    <option></option>
                                    @foreach ($tipoarchivos as $tipo)
                                    <option value="{{$tipo["tipo"]}}">{{$tipo["tipo"]}}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="md:flex ms:flex-wrap">
                        <div class="px-1 py-2 m-2 flex-1">
                            <div>
                                <label for="Notas" class="block text-sm font-medium text-gray-700" title="Notas">Notas</label>
                                <textarea name="notas" id="add_notas" rows="3" class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 mt-1 block w-full sm:text-sm border-gray-300 rounded-md" placeholder="Observaciones"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="px-4 py-3 w-full text-right sm:px-6">
                        <button type="button" class="cerrarmodalarchivo cfrSefar inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            Cancelar
                        </button>
                        <button id="btnEnviarArchivo" class="cfrSefar inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            Añadir Archivo
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="modaleditararchivo" >
        <div class="contentmodaladdfamiliar">
            <div class="formtitle">
                <div class="flex w-full h-auto justify-center items-center" style="border-bottom: 1px rgba(0, 0, 0, 0.30) solid;">
                    <div class="flex w-10/12 h-auto py-3 justify-center items-center text-lg font-bold tituloform" style="color:rgba(55, 65, 81, 1);">
                        Editar Archivo
                    </div>
                    <button class="flex w-1/12 h-auto justify-center cursor-pointer cerrarmodalarchivoedit" type="button">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#374151" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-x"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                    </button>
                </div>
            </div>
            <form id="editarArchivo" action="{{route('getfileupdate')}}" method="POST" enctype="multipart/form-data">
                @csrf
                <input name="IDPersonaNew" id="ed_IDPersonaNew" type="hidden">
                <input name="IDCliente" id="ed_IDCliente" type="hidden" >
                <input name="id" id="edit_id" type="hidden">
                <div class="container">
                    <div class="md:flex ms:flex-wrap">
                        <div class="px-1 py-2 m-2 flex-1">
                            <div>
                                <label for="archivo" class="block text-sm font-medium text-gray-700">Archivo</label>
                                <input type="text" readonly name="archivo" id="edit_archivo" class="mt-1 block text-sm text-gray-900 border border-gray-300 rounded-md shadow-sm w-full cursor-pointer focus:outline-none focus:border-indigo-500 focus:ring-indigo-500" style="height: 38px;">
                            </div>
                        </div>
                        <div class="px-1 py-2 m-2 flex-1">
                            <div>
                                <label for="tipo" class="block text-sm font-medium text-gray-700" title="Tipo">Tipo</label>
                                <select name="tipo" id="edit_tipo" autocomplete="country" class="mt-1 block py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm w-full focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                    <option></option>
                                    @foreach ($tipoarchivos as $tipo)
                                    <option value="{{$tipo["tipo"]}}">{{$tipo["tipo"]}}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="md:flex ms:flex-wrap">
                        <div class="px-1 py-2 m-2 flex-1">
                            <div>
                                <label for="Notas" class="block text-sm font-medium text-gray-700" title="Notas">Notas</label>
                                <textarea name="notas" id="edit_notas" rows="3" class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 mt-1 block w-full sm:text-sm border-gray-300 rounded-md" placeholder="Observaciones"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="px-4 py-3 w-full text-right sm:px-6">
                        <button type="button" class="cerrarmodalarchivoedit cfrSefar inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            Cancelar
                        </button>
                        <button id="btnEnviarArchivo" class="cfrSefar inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            Editar Archivo
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
@stop

@section('css')
    <link rel="stylesheet" href="{{ asset('css\tree.css') }}">
    <link rel="stylesheet" href="{{ asset('css\cdn_tailwind.css') }}">
    <link rel="stylesheet" href="{{ asset('css\sefar.css') }}">
    <style>
        #back-to-top {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background-color: rgb(6, 194, 204);
            border: none;
            padding: 10px 20px;
            border-radius: 50%;
            color: white;
            font-size: 35px;
            cursor: pointer;
            width: 56px;
            height: 56px;
            transition: box-shadow 0.3s ease-in-out, background-color 0.3s ease-in-out;
            display: flex;
            justify-content: center; /* Centrar horizontalmente */
            align-items: center; /* Centrar verticalmente */
            z-index: 6555;
        }

        #back-to-top:hover {
            background-color: rgb(4, 150, 158); /* Color al pasar el cursor */
            box-shadow: 0 0 25px rgb(6, 194, 204); /* Intensificar el glow al hacer hover */
        }
        .glow-effect {
            box-shadow: 0 0 15px rgb(6, 194, 204);
            transition: box-shadow 0.5s ease-in-out;
        }
        .fontwhite{
            color: rgba(55, 65, 81, 1);
        }
        .tablafiles {
            width: 100%;
        }
        .rowfile{
            border-radius: 5px;
            border: rgba(0, 0, 0, 0.3) solid 2px;
            padding: 15px 10px;
            margin: 10px 0px;
        }
        .contentfiles {
            padding: 15px 30px;
            overflow-y: auto;
            max-height: 58vh;
            min-height: 58vh;
            color: rgba(55, 65, 81, 1);
        }
        #mylines {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
            pointer-events: none;
        }
        .modaleditfamiliar, .modaladdfamiliar, .modalverarchivos, .modaladdarchivo, .modaleditararchivo{
            position: fixed;
            z-index: 100000;
            top: 0;
            left: 0;
            background-color: rgba(0, 0, 0, 0.50);
            height: 100%;
            width: 100%;
            display: flex;
            align-items: center;
            align-content: center;
            justify-content: center;
            display: none;
        }
        .contentmodaladdfamiliar{
            height: 80vh;
            width: 70vw;
            overflow-y: auto;
            color: white;
            border-radius: 20px;
            background-color: white;
            margin: auto;
        }
        .addbtntext{
            font-size: 0.7rem;
        }
        .cliente {
            display: inline-flex;
            flex-direction: column;
            justify-content: space-around; /* Distribuye el espacio alrededor de los elementos */
            align-items: center; /* Alinea los elementos al centro horizontalmente */
        }
        .treecont_minimized{
            padding: 30px 20px;
            margin-bottom: 20px;
            width: 100%;
            height: 100%;
            position: relative;
        }

        .tooltip {
            visibility: hidden;
            width: 200px;
            background-color: #f9f9f9;
            color: #000;
            text-align: left;
            border-radius: 6px;
            padding: 10px;
            position: absolute;
            z-index: 1;
            box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1);
            opacity: 0;
            transition: opacity 0.3s;
        }

        .arbolflex {
            display: flex;
            height: 100%;
            z-index: 2;
        }

        .editperson, .filebtn, .copydata {
            background-color: #093143;
            color: white;
            border-radius: 10px;
            padding: 2px 10px;
            border: 1px solid #093143 !important;
            transition: all 0.3s ease;
            font-size: 0.7rem;
        }

        .editperson:hover, .filebtn:hover, .copydata:hover {
            color: #093143 !important;
            background-color: rgb(6, 194, 204)!important;
        }

        .zoomableContent{
            -webkit-font-smoothing: subpixel-antialiased;
        }

        .addbtn {
            width: 20px;
            height: 1.3rem;
            font-size: 20px;
            background-color: #093143;
            border: 2px solid #093143 !important;
            border-radius: 200px;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 0;
            margin: 0px 5px 0px 0px;
            line-height: 1;
            color: white;
            transition: all 0.3s ease;
        }

        .addbtn:hover {
            color: #093143 !important;
            background-color: rgb(6, 194, 204)!important;
        }

        .cajapernew {
            border: 1px solid rgb(22, 43, 27);
            width: 16rem;
            height: 1.3rem;
            border-radius: 5px;
            padding: 0;
            overflow: hidden;
            z-index: 1;
            text-align: center;
        }

        .cajabtn {
            width: 16rem;
            padding: 0;
            overflow: hidden;
            z-index: 1;
            text-align: left;
            display: flex;
            align-content: center;
            align-items: center;
        }

        .cajapernew_min{
            background-color: white !important;
            position: relative;
            border: 1px solid rgb(22, 43, 27);
            width: 18rem;
            height: auto;
            border-radius: 5px;
            padding: 0;
            overflow: hidden;
            z-index: 1;
            text-align: center;

        }

        .cajaperemptynew_min{
            position: relative;
            width: 16rem;
            border-radius: 5px;
            padding: 0;
            overflow: hidden;
            z-index: 1;
            text-align: center;
        }

        .cajapernew_min p {
            font-size: 0.67rem;
        }

        .cajapernew_min .encabezadonew_min {
            transition: all 0.3s ease;
        }

        .cajapernew_min:hover .encabezadonew_min {
            color: #093143 !important;
            background-color: rgb(6, 194, 204)!important;
        }

        .cajapernew_min {
            will-change: transform;
            transition: all 0.3s ease;
        }

        .mr1{
            margin-right: 5px;
        }

        .cajabtn_min{
            width: 14rem;
            padding: 0;
            overflow: hidden;
            z-index: 1;
            text-align: left;
            display: flex;
            align-content: center;
            align-items: center;
        }

        .lineas{
            width: 30px!important;
        }

        .miniinfo {
            width: 100%;
            height: 100%;
            font-size: 0.75rem;
            line-height: 0.85rem;
            align-content: center;
        }

        input, textarea {
            color: #093143 !important;
        }

        .continfo{
            text-align: center;
            padding: 5px;
        }

        .encabezadonew {
            text-align: center;
            font-weight: bold;
            background-color: #093143 !important;
            color: rgba(255, 255, 255, .9);
            font-size: 0.9rem;
        }
        .encabezadonew_min {
            text-align: center;
            font-weight: bold;
            background-color: #093143 !important;
            color: rgba(255, 255, 255, .9);
            font-size: 0.78rem;
            height: auto;
        }

        .nombres,.apellidos {
            text-align: center;
            font-weight: bold;
        }

        dialog[open] {
            animation: appear .15s cubic-bezier(0, 1.8, 1, 1.8);
        }

        dialog::backdrop {
            background: linear-gradient(45deg, rgba(121, 22, 15, 0.5), rgba(63, 61, 61, 0.5));
            backdrop-filter: blur(3px);
        }

        @keyframes appear {
            from {
                opacity: 0;
                transform: translateX(-3rem);
            }

            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
    </style>
@stop

@section('js')
<script
  src="https://code.jquery.com/jquery-3.7.1.min.js"
  integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo="
  crossorigin="anonymous"></script>
<script>
    const container = document.getElementById('containertree');
    const zoomableContent = document.getElementById('zoomableContent');

    const containerTree = document.getElementById('containertree');
    let zoomLevel = 1;

    let isDragging = false;
    let startX, startY, scrollLeft, scrollTop;

    // Al hacer clic y arrastrar
    container.addEventListener('mousedown', (e) => {
        isDragging = true;
        container.style.cursor = 'grabbing';
        startX = e.pageX - container.offsetLeft;
        startY = e.pageY - container.offsetTop;
        scrollLeft = container.scrollLeft;
        scrollTop = container.scrollTop;
    });

    container.addEventListener('mouseleave', () => {
        isDragging = false;
        container.style.cursor = 'grab';
    });

    container.addEventListener('mouseup', () => {
        isDragging = false;
        container.style.cursor = 'grab';
    });

    container.addEventListener('mousemove', (e) => {
        if (!isDragging) return;
        e.preventDefault();
        const x = e.pageX - container.offsetLeft;
        const y = e.pageY - container.offsetTop;
        const walkX = (x - startX) * 1; // Ajusta la sensibilidad del desplazamiento
        const walkY = (y - startY) * 1;
        container.scrollLeft = scrollLeft - walkX;
        container.scrollTop = scrollTop - walkY;
    });

    function adjustZoom() {
        zoomableContent.style.zoom = zoomLevel;

        if (containerTree.scrollWidth <= containerTree.clientWidth) {
            document.getElementById('zoomOut').disabled = true;
        } else {
            document.getElementById('zoomOut').disabled = false;
        }
    }

    document.getElementById('zoomIn').addEventListener('click', function() {
        zoomLevel += 0.1;
        adjustZoom();
    });

    document.getElementById('zoomOut').addEventListener('click', function() {
        if (zoomLevel > 0.1) { // Evita hacer zoom out más allá del límite
            zoomLevel -= 0.1;
            adjustZoom();
        }
    });

    $(document).ready(function() {
        reloadlines();

        $(window).scroll(function() {
            if ($(this).scrollTop() > 10) { // Mostrar si el scroll es mayor a 100px
                $('#back-to-top').fadeIn();
            } else {
                $('#back-to-top').fadeOut();
            }
        });

        // Animación suave para volver al tope al hacer clic en el botón
        $('#back-to-top').click(function() {
            $('html, body').animate({scrollTop: 0}, 800); // Desplazamiento suave en 800ms
            return false;
        });
    });

    function copydata(elementId) {
        const dataElement = document.getElementById(elementId);

        if (dataElement) {
            let datosFormateados = dataElement.innerText;

            datosFormateados = datosFormateados.replace(/(\s)+/g, ' ').trim();
            datosFormateados = datosFormateados.replace(/\|/g, ' ');
            datosFormateados = datosFormateados.replace(/^\s+/gm, '');

            navigator.clipboard.writeText(datosFormateados)
                .then(() => {
                    Swal.fire({
                        icon: 'success',
                        title: '¡Copiado!',
                        text: 'Los datos se han copiado al portapapeles.',
                    });
                })
                .catch(err => {
                    Swal.fire({
                        icon: 'error',
                        title: 'Oops...',
                        text: 'No se pudieron copiar los datos.',
                    });
                });
        } else {
            Swal.fire({
                icon: 'warning',
                title: 'Oops...',
                text: 'No se encontraron datos para copiar.',
            });
        }
    }

    function callEdit(Nombres, Apellidos, AnhoNac, MesNac, DiaNac, LugarNac, PaisNac, AnhoBtzo, MesBtzo, DiaBtzo, LugarBtzo, PaisBtzo, AnhoMatr, MesMatr, DiaMatr, LugarMatr, PaisMatr, AnhoDef, MesDef, DiaDef, LugarDef, PaisDef, Observaciones, id, NPasaporte, PaisPasaporte, NDocIdent, PaisDocIdent){
        $("#editid").val(id);
        $("#editnombres").val(Nombres);
        $("#editApellidos").val(Apellidos);
        $("#editAnhoNac").val(AnhoNac);
        $("#editMesNac").val(MesNac);
        $("#editDiaNac").val(DiaNac);
        $("#editLugarNac").val(LugarNac);
        $("#editPaisNac").val(PaisNac);
        $("#editAnhoBtzo").val(AnhoBtzo);
        $("#editMesBtzo").val(MesBtzo);
        $("#editDiaBtzo").val(DiaBtzo);
        $("#editLugarBtzo").val(LugarBtzo);
        $("#editPaisBtzo").val(PaisBtzo);
        $("#editAnhoMatr").val(AnhoMatr);
        $("#editMesMatr").val(MesMatr);
        $("#editDiaMatr").val(DiaMatr);
        $("#editLugarMatr").val(LugarMatr);
        $("#editPaisMatr").val(PaisMatr);
        $("#editAnhoDef").val(AnhoDef);
        $("#editMesDef").val(MesDef);
        $("#editDiaDef").val(DiaDef);
        $("#editLugarDef").val(LugarDef);
        $("#editPaisDef").val(PaisDef);
        $("#editObservaciones").val(Observaciones);
        $("#editNPasaporte").val(NPasaporte);
        $("#editPaisPasaporte").val(PaisPasaporte);
        $("#editNDocIdent").val(NDocIdent);
        $("#editPaisDocIdent").val(PaisDocIdent);
        $(".modaleditfamiliar").show();
    }

    function gotofamiliar(id) {
        zoomLevel = 1;
        zoomableContent.style.zoom = zoomLevel;
        document.getElementById('zoomOut').disabled = false;

        var classid = ".min_persona_id_" + id;
        var element = document.querySelector(classid);

        if (element) {
            // Añadir el efecto de "glow"
            element.classList.add("glow-effect");

            // Centrar el elemento en la vista
            element.scrollIntoView({
                behavior: "smooth", // Desplazamiento suave
                block: "center",    // Centrar el elemento verticalmente en la vista
                inline: "center"    // Centrar el elemento horizontalmente en la vista (opcional)
            });

            // Remover el "glow" después de un tiempo (opcional)
            setTimeout(function() {
                element.classList.remove("glow-effect");
            }, 3000); // Elimina el glow después de 2 segundos (puedes ajustar el tiempo)
        }
    }


    $(".change_person").on("change", function(e){
        gotofamiliar(this.value);
    });

    $(".cerrarmodal").click(function(){
        $(".modaladdfamiliar").hide();
        $(".modaleditfamiliar").hide();
        $(".modalverarchivos").hide();
    });

    $(".cerrarmodalarchivo").click(function(){
        $(".modaladdarchivo").hide();
        $(".modaleditararchivo").hide();
        var cliente = $("#up_IDCliente").val();
        var familiar = $("#up_IDPersonaNew").val();
        callFiles(cliente, familiar);
    });

    $(".cerrarmodalarchivoedit").click(function(){
        $(".modaladdarchivo").hide();
        $(".modaleditararchivo").hide();
        var cliente = $("#ed_IDCliente").val();
        var familiar = $("#ed_IDPersonaNew").val();
        callFiles(cliente, familiar);
    });

    $("#addNewFile").click(function(){
        $("#up_IDPersonaNew").val($("#f_IDPersonaNew").val());
        $("#up_IDCliente").val($("#f_IDCliente").val());
        $("#add_archivo").val("");
        $("#add_tipo").val("");
        $("#add_notas").val("");
        $(".modalverarchivos").hide();
        $(".modaladdarchivo").show();
    })

    $('#btnEnviarArchivo').click(function(event) {
        // Validar campos
        var archivoInput = $('#add_archivo');
        var tipoSelect = $('#add_tipo');

        if (archivoInput.val() === '' || tipoSelect.val() === '') {
            event.preventDefault(); // Prevenir el envío
            alert('Por favor, selecciona un archivo y un tipo de archivo.');
        } else {
            // Si la validación es exitosa, enviar el formulario
            $('#subirArchivo').submit();
        }
    });

    $('#btnEditarArchivo').click(function(event) {
        // Validar campos
        var tipoSelect = $('#edit_tipo');

        if (tipoSelect.val() === '') {
            event.preventDefault(); // Prevenir el envío
            alert('Por favor, selecciona un tipo de archivo.');
        } else {
            // Si la validación es exitosa, enviar el formulario
            $('#editarArchivo').submit();
        }
    });

    $(".addbtn").click(function(){
        $('input[type="text"], input[type="number"], textarea').val('');
        $('select').prop('selectedIndex', 0);
        $(".modaladdfamiliar").show();
        const contentModal = document.querySelector('.contentmodaladdfamiliar');
        contentModal.scrollTop = 0;

        datos = $(this).attr('id');

        splited = datos.split("_");

        $("#sexaddform").val(splited[0]);
        $("#id_hijo").val(splited[1]);
    });

    function cambioTipo(selectElement) {
        const id = selectElement.id.replace("ch_", "");

        const valorSeleccionado = selectElement.value;

        var csrfToken = $('meta[name="csrf-token"]').attr('content');

        $.ajax({
            url: "{{ route('updatefiletype') }}",
            method: "POST",
            data: {
                id : id,
                tipo : valorSeleccionado
            },
            headers: {
                'X-CSRF-TOKEN': csrfToken
            },
            success: function(response) {
                console.log(response);
            },
            error: function(xhr) {
                // Manejar errores aquí
            }
        });
    }

    function callFiles(cliente, familiar){
        var csrfToken = $('meta[name="csrf-token"]').attr('content');

        $.ajax({
            url: "{{ route('getclientfiles') }}",
            method: "POST",
            data: {
                clienteid : cliente,
                familiarid : familiar
            },
            headers: {
                'X-CSRF-TOKEN': csrfToken
            },
            success: function(response) {
                $("#f_IDCliente").val(cliente);
                $("#f_IDPersonaNew").val(familiar);

                var archivos = response.archivos;
                var tipoarchivos = response.tipodearchivos;
                html = "";
                if (archivos.length > 0){


                    archivos.forEach(function(archivo) {
                        html += "<div class='rowfile'>"
                        html += "<table class='tablafiles'>";
                        html += "<tr>";
                        html += "<td class='fontwhite' style='width: 40%'>" + archivo.file + "</td>";
                        html += "<td class='fontwhite' style='width: 30%'>";
                        html += "<select style='width:90%' id='ch_"+archivo.id+"' onchange='cambioTipo(this)'>";

                        tipoarchivos.forEach(function(tipoarchivo) {
                            let selected = archivo.tipo === tipoarchivo.tipo ? "selected" : "";
                            html += "<option value='" + tipoarchivo.tipo + "' " + selected + ">" + tipoarchivo.tipo + "</option>";
                        });

                        html += "</select>";
                        html += "</td>";
                        html += "<td class='fontwhite' style='width: 30%'><button class='filebtn mr1' onclick='verArchivo(\"" + archivo.location+"/"+ archivo.file + "\")'>Ver archivo</button>";

                        <?php
                            if (auth()->user()->roles->pluck('name')[0] == "Administrador" || auth()->user()->roles->pluck('name')[0] == "Genealogista" || auth()->user()->roles->pluck('name')[0] == "Documentalista"){
                        ?>
                            html+="<button class='filebtn mr1' onclick='editarArchivo(" + archivo.id + ")'>Editar</button>";
                            html+="<button class='filebtn' onclick='borrararchivo(" + archivo.id + ")'>Borrar</button>";
                        <?php
                            }
                        ?>

                        html += "</td>";
                        html += "</tr>";
                        html += "</table>";
                        html += "</div>"
                    });


                } else {
                    html += "<h3>No hay documentos registrados para esta persona.</h3>"
                }

                $(".contentfiles").html(html);

                $(".modalverarchivos").show();
            },
            error: function(xhr) {
                // Manejar errores aquí
            }
        });
    }

    function borrararchivo(fileId) {
        if (confirm('¿Estás seguro de que quieres eliminar este archivo?')) {
            $.ajax({
                url: '{{ route('deletefile') }}',
                method: 'POST',
                data: {
                    fileId: fileId,
                    _token: '{{ csrf_token() }}' // Asegúrate de tener el token CSRF en tu vista
                },
                success: function(response) {
                    callFiles($("#f_IDCliente").val(), $("#f_IDPersonaNew").val());
                },
                error: function(xhr) {
                    alert('Error al eliminar el archivo.');
                }
            });
        }
    }

    function editarArchivo(fileId){
        $.ajax({
            url: '{{ route('getfileedit') }}',
            method: 'POST',
            data: {
                fileId: fileId,
                _token: '{{ csrf_token() }}' // Asegúrate de tener el token CSRF en tu vista
            },
            success: function(response) {
                console.log(response);
                $("#edit_id").val(response["id"]);
                $("#edit_archivo").val(response["file"]);
                $("#edit_tipo").val(response["tipo"]);
                $("#edit_notas").val(response["notas"]);
                $("#ed_IDPersonaNew").val($("#f_IDPersonaNew").val());
                $("#ed_IDCliente").val($("#f_IDCliente").val());
                $(".modalverarchivos").hide();
                $(".modaleditararchivo").show();
            },
            error: function(xhr) {
                alert('Error al eliminar el archivo.');
            }
        });
    }



    function verArchivo(archivo){
        $.ajax({
            url: "{{ route('openfile') }}",
            method: 'POST',
            data: { path: archivo, _token: '{{ csrf_token() }}' },
            success: function(response) {
                if (response.url) {
                    window.open(response.url, '_blank');
                } else {
                    alert('Error al generar la URL del archivo.');
                }
            }
        });
    }

    function reloadlines(){
        $("#mylines").html("");
        const $cajasPersonas = $('.cajapernew_min');

        $cajasPersonas.each(function() {
            const $caja = $(this);

            const idPersona = $caja.attr('class').match(/min_persona_id_(\d+)/)[1];
            const idPadre = $caja.attr('class').match(/min_padre_id_(\d+|no)/)[1];
            const idMadre = $caja.attr('class').match(/min_madre_id_(\d+|no)/)[1];

            if (idPadre !== 'no') {
                dibujarLineaSVG($caja, $(`.min_persona_id_${idPadre}`));
            }

            if (idMadre !== 'no') {
                dibujarLineaSVG($caja, $(`.min_persona_id_${idMadre}`));
            }
        });

        const $botonesAgregar = $('.addbtn');

        $botonesAgregar.each(function() {
            const $boton = $(this);
            const botonClasses = $boton.attr('class').split(/\s+/);
            let idHijo = null;

            botonClasses.forEach(cls => {
                const match = cls.match(/(M|F)_(\d+)/);
                if (match) {
                    idHijo = match[2];
                }
            });

            if (idHijo) {
                const $cajaHijo = $(`.min_persona_id_${idHijo}`);
                if ($cajaHijo.length > 0) {
                    dibujarLineaSVG($boton, $cajaHijo);
                }
            }
        });
    }

    function dibujarLineaSVG($caja1, $caja2) {
        if ($caja2.length === 0) {
            return; // No dibujar si la caja relacionada no existe
        }

        const offset1 = $caja1.offset();
        const offset2 = $caja2.offset();

        const x1 = offset1.left + $caja1.outerWidth() / 2;
        const y1 = offset1.top + $caja1.outerHeight() / 2;
        const x2 = offset2.left + $caja2.outerWidth() / 2;
        const y2 = offset2.top + $caja2.outerHeight() / 2;

        const svgWidth = Math.abs(x2 - x1);
        const svgHeight = Math.abs(y2 - y1);
        const xOffset = Math.min(x1, x2) - $('#mylines').offset().left;
        const yOffset = Math.min(y1, y2) - $('#mylines').offset().top;

        const svg = `<svg class="linea_conexion" style="position:absolute; top:${yOffset}px; left:${xOffset}px; width:${svgWidth}px; height:${svgHeight}px;">
                        <line x1="${x1 < x2 ? 0 : svgWidth}" y1="${y1 < y2 ? 0 : svgHeight}" x2="${x1 < x2 ? svgWidth : 0}" y2="${y1 < y2 ? svgHeight : 0}" stroke="black" stroke-width="2" />
                    </svg>`;

        $('#mylines').append(svg);
    }

    $('#modeview').on('change', function(){
        if ($('#modeview').val() == '1' || $('#modeview').val() == 1){
            $('.treecont').show();
            $('.treecont_minimized').hide();
        } else {
            $('.treecont_minimized').show();
            $('.treecont').hide();
        }
    });
</script>
@stop
