@extends('adminlte::page')

@section('title', $user->name)

@section('content_header')
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

@stop

@section('content')

@php
    // Simulando un array de países (puedes llenarlo con todos los que necesites)
    $opcionesPersonas = [
        'Soporte IT', 'Crisanto Bello', 'Abel Tejeda', 'rrcastro@sefarvzla.com',
        // ...
        'Liliana Du Bois'
    ];
@endphp

<x-app-layout>
    <div>
        @if(auth()->user()->roles[0]->id != 5)
        <div class="flex flex-col">
            <div class="-my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
                <div class="py-2 align-middle inline-block min-w-full sm:px-6 lg:px-8">
                    {{-- Inicio --}}
                    <div class="bg-gray-50">
                        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:py-6 lg:px-8 lg:flex lg:items-center lg:justify-between">
                            <h2 class="text-3xl font-extrabold tracking-tight text-gray-900 sm:text-4xl">
                                <span class="ctvSefar block text-indigo-600">{{ __('Edit user') }}</span>
                            </h2>
                            <div class="mt-8 flex lg:mt-0 lg:flex-shrink-0">
                                <div class="inline-flex rounded-md shadow">
                                    <a href="{{ route('crud.users.index') }}" class="cfrSefar inline-flex items-center justify-center px-5 py-3 border border-transparent text-base font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                                        Volver a {{ __('Users list') }}
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    {{-- Fin --}}
                </div>
            </div>
        </div>
        @endif
        <div class="card p-4">
            <ul class="nav nav-tabs" id="formTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button style="color:black" class="nav-link active" id="mystatus-tab" data-bs-toggle="tab" data-bs-target="#mystatus" type="button" role="tab" aria-controls="mystatus" aria-selected="true">
                        @if(auth()->user()->roles[0]->id == 5)
                        Mi Estatus
                        @else
                        Estatus de Cliente
                        @endif
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button style="color:black" class="nav-link" id="personal-data-tab" data-bs-toggle="tab" data-bs-target="#personal_data" type="button" role="tab" aria-controls="personal_data" aria-selected="true">
                        Datos personales
                    </button>
                </li>
                @if(auth()->user()->roles[0]->id == 1)
                <li class="nav-item" role="presentation">
                    <button style="color:black" class="nav-link" id="adminchangepassword-tab" data-bs-toggle="tab" data-bs-target="#adminchangepassword" type="button" role="tab" aria-controls="adminchangepassword" aria-selected="true">
                        Contraseña
                    </button>
                </li>
                @elseif(auth()->user()->roles[0]->id == 5)
                <li class="nav-item" role="presentation">
                    <button style="color:black" class="nav-link" id="mypassword-tab" data-bs-toggle="tab" data-bs-target="#mypassword" type="button" role="tab" aria-controls="mypassword" aria-selected="true">
                        Cambiar mi Contraseña
                    </button>
                </li>
                @endif
                <li class="nav-item" role="presentation">
                    <button style="color:black" class="nav-link" id="familiars-tab" data-bs-toggle="tab" data-bs-target="#familiars" type="button" role="tab" aria-controls="familiars" aria-selected="false">
                        Familiares registrados
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button style="color:black" class="nav-link" id="payments-tab" data-bs-toggle="tab" data-bs-target="#payments" type="button" role="tab" aria-controls="payments" aria-selected="false">
                        Pagos realizados
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button style="color:black" class="nav-link" id="paymentspen-tab" data-bs-toggle="tab" data-bs-target="#paymentspen" type="button" role="tab" aria-controls="paymentspen" aria-selected="false">
                        Pagos pendientes
                    </button>
                </li>
                @if(auth()->user()->roles[0]->id == 5)
                <li class="nav-item">
                    <button style="color:black" class="nav-link" id="client-req-tab"
                            data-bs-toggle="tab" data-bs-target="#client-req"
                            type="button" role="tab" aria-controls="client-req" aria-selected="false">
                        Mis solicitudes de documentos
                    </button>
                </li>
                @else
                {{-- === TAB para ADMIN === --}}
                <li class="nav-item">
                    <button style="color:black" class="nav-link" id="admin-req-tab"
                            data-bs-toggle="tab" data-bs-target="#admin-req"
                            type="button" role="tab" aria-controls="admin-req" aria-selected="false">
                        Solicitudes de documentos
                    </button>
                </li>
                @endif
                <li class="nav-item" role="presentation">
                    <button style="color:black" class="nav-link" id="documents-tab" data-bs-toggle="tab" data-bs-target="#documents" type="button" role="tab" aria-controls="documents" aria-selected="false">
                        Archivos Cargados
                    </button>
                </li>
                @if(auth()->user()->roles[0]->id == 1 || auth()->user()->roles[0]->id == 2)
                <li class="nav-item" role="presentation">
                    <button style="color:black" class="nav-link" id="etiquetado-tab" data-bs-toggle="tab" data-bs-target="#etiquetado" type="button" role="tab" aria-controls="etiquetado" aria-selected="false">
                        Etiquetado
                    </button>
                </li>
                @endif
                @if(auth()->user()->roles[0]->id == 17 || auth()->user()->roles[0]->id == 1 || auth()->user()->roles[0]->id == 4 || auth()->user()->roles[0]->id == 16 || auth()->user()->roles[0]->id == 15)
                <li class="nav-item" role="presentation">
                    <button style="color:black" class="nav-link" id="negocios-tab" data-bs-toggle="tab" data-bs-target="#negocios" type="button" role="tab" aria-controls="negocios" aria-selected="false">
                        Negocios
                    </button>
                </li>
                @endif
            </ul>
            <style>
                /* Contenedor para el scroll horizontal */
                .progress-scroll-container {
                    overflow-x: auto;
                    white-space: nowrap;
                    width: 100%;
                    padding-bottom: 10px; /* Espacio para el scroll */
                    -webkit-overflow-scrolling: touch; /* Scroll suave en móviles */
                    height: 150px;
                }

                /* Contenedor principal del progreso */
                .progress-container {
                    display: inline-flex;
                    justify-content: space-between;
                    align-items: center;
                    min-width: 100%;
                    position: relative;
                    padding: 0 20px 50px 20px; /* Ajuste de padding */
                    box-sizing: border-box;
                }

                /* Líneas de progreso */
                .progress-line-full, .progress-line {
                    position: absolute;
                    height: 14px;
                    left: 20px;
                    right: 20px;
                    z-index: 0;
                    border-radius: 1000px;
                    margin: 0px 61px;
                }

                .progress-line-full {
                    background-color: #dfdfdf !important;
                }

                .progress-line {
                    background-color: #06C2CC !important;
                    z-index: 1;
                    transition: width 0.3s ease;
                    width: 0; /* Se sobrescribe con el style inline */
                }

                /* Pasos individuales */
                .progress-step {
                    width: 50px;
                    height: 50px;
                    background-color: #dfdfdf;
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    position: relative;
                    z-index: 2;
                    color: #fff;
                    font-size: 24px;
                    flex-shrink: 0;
                    margin: 0 35px; /* Espacio entre pasos */
                }

                .progress-step.active {
                    background-color: #1CE56D;
                    color: #ffffff;
                }

                /* Etiquetas de los pasos */
                .step-label {
                    position: absolute;
                    top: 58px;
                    font-size: 11.5px;
                    color: #333;
                    text-align: center;
                    width: 110px;
                    font-weight: bold;
                    line-height: 16px;
                    white-space: normal;
                }

                /* Título de la tarjeta */
                .card-title {
                    width: 100%;
                    font-weight: bold;
                    text-align: center;
                }
            </style>
            <div class="tab-content mt-4" id="formTabsContent">
                <!-- Primer Formulario -->

                <div class="tab-pane fade show active" id="mystatus" role="tabpanel" aria-labelledby="mystatus-tab">
                    @php
                        $errorNotAvailable = 0;
                    @endphp
                    @if (count($cosuser)>0)
                        @foreach ($cosuser as $co)
                            @if(array_key_exists($co["servicio"], $cos))
                                @php
                                    $errorNotAvailable = $errorNotAvailable + 1;
                                @endphp

                                <div class="card">
                                    <div class="card-header" style="text-align: center;">
                                        <center>
                                            <img class="img-fluid" style="max-width:100px;"
                                                src="/img/logonormal.png"
                                                alt="Logo Sefar">
                                        </center>
                                        <h2 class="card-title mb-4">Estatus de mi proceso: {!! $co["servicio"] !!}</h2>
                                        @php
                                            $gen = $co["currentStepGen"] ?? -1;
                                            $jur = $co["currentStepJur"] ?? -1;

                                            $pasoFinal = null;
                                            $numeroPaso = null;

                                            // Calcular paso solo si alguna línea ha comenzado
                                            if ($gen !== -1 || $jur !== -1) {
                                                $numeroPaso = 0;

                                                if ($gen !== -1) {
                                                    $numeroPaso += $gen;
                                                }

                                                if ($jur !== -1) {
                                                    $numeroPaso += $jur;
                                                }

                                                $numeroPaso += 1;

                                                // Buscar ese paso en array_cos
                                                foreach ($cos[$co["servicio"]] as $rama) {
                                                    foreach ($rama as $paso) {
                                                        if ($paso['paso'] === $numeroPaso) {
                                                            $pasoFinal = $paso;
                                                            break 2;
                                                        }
                                                    }
                                                }
                                            }
                                        @endphp
                                        @if($pasoFinal)
                                            <p class="pb-4">Estatus actual: <b>{{ $pasoFinal['nombre_corto'] }}</b></p>
                                        @else
                                            <p class="pb-4">Estatus actual: <b>No iniciado</b></p>
                                        @endif

                                        <p class="pb-4">{!! $pasoFinal["promesa"] !!}</p>

                                        @if (isset($co["warning"]))
                                            <div class="alert alert-warning fade show small py-4" role="alert">
                                                <i class="fas fa-exclamation-triangle me-2" style="font-size: 30px"></i><br><br>
                                                {!! $co["warning"] !!}
                                            </div>
                                        @endif
                                    </div>

                                    <div style="text-align: center; border-bottom: #DEE2E6 solid 1px;height: 220px; overflow: hidden; ">
                                        <div id="carouselExampleSlidesOnly" class="carousel slide" data-ride="carousel">
                                            <div class="carousel-inner">
                                                @php
                                                    $flag = true;
                                                @endphp
                                                @foreach($imageUrls as $key => $url)

                                                    <div class="carousel-item {{ $flag ? 'active' : '' }}">
                                                        <img
                                                            class="d-block w-100"
                                                            src="{{$url}}"
                                                            alt="First slide"
                                                            style="
                                                                object-fit: cover;
                                                                height: 220px;
                                                                width: 100%;
                                                            "
                                                        />
                                                    </div>

                                                    @php
                                                        $flag = false;
                                                    @endphp
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>

                                    @if(sizeof($pasoFinal["textos_adicionales"]) > 0)
                                        <div style="text-align: center; border-bottom: #DEE2E6 solid 1px; background: rgba(0,0,0,0.05);" class="pb-4">
                                            <h4 class="mb-4"><b>Información Adicional</b></h4>
                                            <div class="accordion accordion-flush" id="accordionFlushExample" style="max-width: 800px; margin: 0 auto;">
                                                @foreach($pasoFinal["textos_adicionales"] as $index => $item)
                                                    <div class="accordion-item">
                                                        <h2 class="accordion-header" id="flush-heading-{{ $index }}">
                                                            <button class="accordion-button collapsed" type="button"
                                                                    data-bs-toggle="collapse"
                                                                    data-bs-target="#flush-collapse-{{ $index }}"
                                                                    aria-expanded="false"
                                                                    aria-controls="flush-collapse-{{ $index }}">
                                                                {{ $item['nombre'] ?? '' }}
                                                            </button>
                                                        </h2>
                                                        <div id="flush-collapse-{{ $index }}"
                                                            class="accordion-collapse collapse"
                                                            aria-labelledby="flush-heading-{{ $index }}"
                                                            data-bs-parent="#textosAdicionalesAccordion">
                                                            <div class="accordion-body">
                                                                {!! $item['texto'] ?? '' !!}
                                                            </div>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif

                                    <div class="card-body text-center" style="text-align: center; border-bottom: #DEE2E6 solid 1px; background: rgba(0,0,0,0.05);">

                                        <h4 class="mb-4 mt-4"><b>Progreso Genealógico</b></h4>
                                        <div class="progress-scroll-container mb-4">
                                            <div class="progress-container" id="progressContainerGen">
                                                <div class="progress-line-full"></div>
                                                <div class="progress-line" style="width: {{ $co['progressPercentageGen'] }}%;"></div>

                                                @foreach ($cos[$co['servicio']]["genealogico"] as $step)
                                                    <div class="progress-step {{ $co['currentStepGen']+1 >= $step['paso'] ? 'active' : '' }}" data-step="{{ $step['paso'] }}">
                                                        <i class="fas fa-check-circle"></i>
                                                        <span class="step-label">{{ $step['nombre_corto'] }}</span>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>

                                        <h4 class="mb-4"><b>Progreso Jurídico</b></h4>
                                        <div class="progress-scroll-container">
                                            <div class="progress-container" id="progressContainerJur">
                                                <div class="progress-line-full"></div>
                                                <div class="progress-line" style="width: {{ $co['progressPercentageJur'] }}%;"></div>

                                                @foreach ($cos[$co['servicio']]["juridico"] as $step)
                                                    <div class="progress-step {{ $co['currentStepGen'] + $co['currentStepJur']+1 >= $step['paso'] ? 'active' : '' }}" data-step="{{ $step['paso'] }}">
                                                        <i class="fas fa-check-circle"></i>
                                                        <span class="step-label">{{ $step['nombre_corto'] }}</span>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>

                                    @if(isset($pasoFinal["ctas"]) && sizeof($pasoFinal["ctas"])>1)
                                    <div class="card-body text-center" style="text-align: center; border-bottom: #DEE2E6 solid 1px; background: rgba(0,0,0,0.05);">
                                        <h2 class="card-title mb-4">Contrata nuestros servicios adicionales y<br><b>asegura tu ciudadania europea:</b></h2>
                                        @foreach ($pasoFinal["ctas"] as $cta)
                                            <a href="{{$cta['url']}}" target="_blank" class="cfrSefar inline-flex items-center justify-center px-3 py-2 border border-transparent text-base font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 my-2">
                                                {{$cta['text']}}
                                            </a>
                                        @endforeach
                                    </div>
                                    @endif
                                </div>
                            @endif
                        @endforeach
                    @endif

                    @if ($errorNotAvailable==0)
                        <div class="alert alert-info text-center my-5 p-5" role="alert" style="max-width: 700px; margin: 0 auto; background-color: rgba(0,0,0,0.05); border: 1px solid #b6e0fe; border-radius: 15px;">
                            <center>
                                <img class="img-fluid" style="max-width:100px;"
                                        src="/img/logonormal.png"
                                        alt="Logo Sefar">
                                <h4 class="alert-heading"><b>¡Muy pronto disponible!</b></h4>
                                <p class="mt-3">Estamos trabajando para que puedas visualizar el estatus de tu proceso directamente desde esta plataforma.</p>
                                <hr>
                                <p class="mb-0">Te avisaremos tan pronto esté activo. Gracias por tu paciencia.</p>
                            </center>
                        </div>
                    @endif
                </div>

                <script>
                    document.addEventListener('DOMContentLoaded', function () {
                        const containers = document.querySelectorAll('.progress-scroll-container');

                        containers.forEach(container => {
                            const progressContainer = container.querySelector('.progress-container');
                            const activeSteps = container.querySelectorAll('.progress-step.active');

                            if (activeSteps.length > 0) {
                                const lastActiveStep = activeSteps[activeSteps.length - 1];

                                const containerWidth = container.clientWidth;
                                const stepRect = lastActiveStep.getBoundingClientRect();
                                const containerRect = container.getBoundingClientRect();
                                const stepCenter = stepRect.left - containerRect.left + stepRect.width / 2;
                                const scrollTo = stepCenter - containerWidth / 2 + container.scrollLeft;

                                container.scrollTo({
                                    left: scrollTo,
                                    behavior: 'smooth'
                                });
                            }
                        });
                    });
                </script>


                <div class="tab-pane fade show" id="personal_data" role="tabpanel" aria-labelledby="personal-data-tab">
                    <form id="datos-personales-form">
                        @csrf
                        <input type="hidden" id="id" name="id" value="{{$user->id}}" />
                        <h2 class="text-1xl font-extrabold tracking-tight text-gray-900 sm:text-2xl mt-4">
                            <span class="ctvSefar block text-indigo-600">Datos Personales</span>
                        </h2>
                        <div class="mt-3" style="display: flex; gap: 16px; flex-wrap: wrap;">
                            <div style="flex: 1;" class="mb-3">
                                <label for="nombre" class="block text-sm font-medium text-gray-700">Nombre</label>
                                <input type="text" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="nombres" name="nombres" value="{{ old('nombres', $user->nombres) }}" placeholder="Ingrese su nombre">
                            </div>
                            <div style="flex: 1;" class="mb-3">
                                <label for="apellido" class="block text-sm font-medium text-gray-700">Apellido</label>
                                <input type="text" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="apellidos" name="apellidos" value="{{ old('apellidos', $user->apellidos) }}" placeholder="Ingrese su apellido">
                            </div>
                        </div>
                        <div class="mt-2" style="display: flex; gap: 16px; flex-wrap: wrap;">
                            <div style="flex: 1;" class="mb-3">
                                <label for="date_of_birth" class="block text-sm font-medium text-gray-700">Fecha de Nacimiento</label>
                                <input type="date" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="date_of_birth" name="date_of_birth" value="{{ old('date_of_birth', \Carbon\Carbon::parse($user->date_of_birth)->format('Y-m-d')) }}" placeholder="Ingrese fecha de nacimiento">
                            </div>
                            <div style="flex: 1;" class="mb-3">
                                <label for="genero" class="block text-sm font-medium text-gray-700">Genero</label>
                                <select
                                    class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                    id="genero"
                                    name="genero">

                                    <option value="" {{ old('genero', $user->genero ?? '') === '' ? 'selected' : '' }}></option>
                                    <option value="FEMENINO / FEMALE"
                                        {{ old('genero', $user->genero ?? '') === 'FEMENINO' || old('genero', $user->genero ?? '') === 'FEMENINO / FEMALE' ? 'selected' : '' }}>
                                        FEMENINO / FEMALE
                                    </option>
                                    <option value="MASCULINO / MALE"
                                        {{ old('genero', $user->genero ?? '') === 'MASCULINO' || old('genero', $user->genero ?? '') === 'MASCULINO / MALE' ? 'selected' : '' }}>
                                        MASCULINO / MALE
                                    </option>
                                    <option value="OTROS / OTHERS"
                                        {{ in_array((string) old('genero', $user->genero ?? ''), ['OTRO', 'OTROS', 'OTROS / OTHERS', 'OTROS / OT']) ? 'selected' : '' }}>
                                        OTROS / OTHERS
                                    </option>
                                </select>
                            </div>
                        </div>
                        <div class="mt-2" style="display: flex; gap: 16px; flex-wrap: wrap;">
                            <div style="flex: 1;" class="mb-3">
                                <label for="veces_casado" class="block text-sm font-medium text-gray-700">Veces Casado</label>
                                <input
                                    type="number"
                                    class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                    id="veces_casado"
                                    name="veces_casado"
                                    value="{{ old('veces_casado', $user->veces_casado ?? '') }}"
                                    placeholder="Ingrese la cantidad de veces casado">
                            </div>

                            <div style="flex: 1;" class="mb-3">
                                <label for="edo_civil" class="block text-sm font-medium text-gray-700">Estado Civil</label>
                                <select
                                    class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                    id="edo_civil"
                                    name="edo_civil">
                                    <option value="" {{ old('edo_civil', $user->edo_civil ?? '') === '' ? 'selected' : '' }}></option>
                                    <option value="SOLTERO (A)" {{ old('edo_civil', $user->edo_civil ?? '') === 'SOLTERO (A)' ? 'selected' : '' }}>SOLTERO (A)</option>
                                    <option value="CASADO (A)" {{ old('edo_civil', $user->edo_civil ?? '') === 'CASADO (A)' ? 'selected' : '' }}>CASADO (A)</option>
                                    <option value="DIVORCIADO (A)" {{ old('edo_civil', $user->edo_civil ?? '') === 'DIVORCIADO (A)' ? 'selected' : '' }}>DIVORCIADO (A)</option>
                                    <option value="VIUDO (A)" {{ old('edo_civil', $user->edo_civil ?? '') === 'VIUDO (A)' ? 'selected' : '' }}>VIUDO (A)</option>
                                </select>
                            </div>
                        </div>
                        <div class="mt-2" style="display: flex; gap: 16px; flex-wrap: wrap;">
                            <div style="flex: 1;" class="mb-3">
                                <label for="email" class="block text-sm font-medium text-gray-700">Correo</label>
                                <input type="email" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="email" name="email" value="{{ old('email', $user->email) }}" placeholder="Ingrese su correo electrónico">
                            </div>
                            <div style="flex: 1;" class="mb-3">
                                <label for="telefono" class="block text-sm font-medium text-gray-700">Teléfono</label>
                                <input type="tel" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="phone" name="phone" value="{{ old('phone', $user->phone) }}" placeholder="Ingrese su número de teléfono">
                            </div>
                        </div>
                        @if(auth()->user()->roles[0]->id == 1 || auth()->user()->roles[0]->id == 15 || auth()->user()->roles[0]->id == 16)
                        <div class="mt-2" style="display: flex; gap: 16px; flex-wrap: wrap;">
                            <div style="flex: 1;" class="mb-3">
                                <label for="detalle_de_la_solicitud" class="block text-sm font-medium text-gray-700">Detalles de la solicitud</label>
                                <textarea
                                    class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                    id="detalle_de_la_solicitud"
                                    name="detalle_de_la_solicitud"
                                    rows="3"
                                    placeholder="Ingrese Detalles de la Solicitud"
                                >{{ old('detalle_de_la_solicitud', $user->detalle_de_la_solicitud) }}</textarea>
                            </div>
                        </div>
                        @endif
                        <div class="mt-2" style="display: flex; gap: 16px; flex-wrap: wrap;">
                            <div style="flex: 1;" class="mb-3">
                                <label for="pay" class="block text-sm font-medium text-gray-700">{{ __('Payment status') }} del registro</label>
                                @if(auth()->user()->roles[0]->id == 1)
                                    <select name="pay" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                        @if ($user->pay == 0)
                                            <option selected value=0>No ha pagado</option>
                                        @else
                                            <option value=0>No ha pagado</option>
                                        @endif

                                        @if ($user->pay == 1)
                                            <option selected value=1>Pagó</option>
                                        @else
                                            <option value=1>Pagó</option>
                                        @endif

                                        @if ($user->pay == 2)
                                            <option selected value=2>Pagó y completó información</option>
                                        @else
                                            <option value=2>Pagó y completó información</option>
                                        @endif

                                        @if ($user->pay == 3)
                                            <option selected value=3>Pagó pero no se registró en Hubspot</option>
                                        @else
                                            <option value=3>Pagó pero no se registró en Hubspot</option>
                                        @endif
                                    </select>
                                @else
                                    <p>
                                        @if ($user->pay == 0)
                                            No ha pagado
                                        @endif

                                        @if ($user->pay == 1)
                                            Pagó
                                        @endif

                                        @if ($user->pay == 2)
                                            Pagó y completó información
                                        @endif

                                        @if ($user->pay == 3)
                                            Pagó pero no se registró en Hubspot
                                        @endif
                                    </p>
                                @endif
                            </div>
                            <div style="flex: 1;" class="mb-3">
                                <label for="contrato" class="block text-sm font-medium text-gray-700">Servicio Principal</label>
                                @if(auth()->user()->roles[0]->id == 1)
                                <select name="servicio" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                    <option></option>
                                    @foreach ($servicios as $servicio)
                                        <option {{ $user->servicio == $servicio->id_hubspot ? 'selected' : '' }} > {{$servicio->id_hubspot}}</option>
                                    @endforeach
                                </select>
                                @else
                                    <p>
                                        {{$user->servicio}}
                                    </p>
                                @endif
                            </div>
                            <div style="flex: 1;" class="mb-3">
                                <label for="contrato" class="block text-sm font-medium text-gray-700">Contrato</label>
                                @if(auth()->user()->roles[0]->id == 1)
                                    <select name="contrato" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                        @if ($user->contrato == 0)
                                            <option selected value=0>No ha firmado contrato</option>
                                        @else
                                            <option value=0>No ha firmado contrato</option>
                                        @endif

                                        @if ($user->contrato == 1)
                                            <option selected value=1>Firmó Contrato</option>
                                        @else
                                            <option value=1>Firmó Contrato</option>
                                        @endif
                                    </select>
                                @else
                                    <p>
                                        @if ($user->pay == 0)
                                            No ha firmado contrato
                                        @endif

                                        @if ($user->pay == 1)
                                            Firmó Contrato
                                        @endif
                                    </p>
                                @endif
                            </div>
                        </div>

                        <h2 class="text-1xl font-extrabold tracking-tight text-gray-900 sm:text-2xl mt-4">
                            <span class="ctvSefar block text-indigo-600">
                            Datos del Pasaporte
                            </span>
                        </h2>

                        <div class="mt-3" style="display: flex; gap: 16px; flex-wrap: wrap;">
                            <div style="flex: 1;" class="mb-3">
                                <label for="pasaporte" class="block text-sm font-medium text-gray-700">Número de Pasaporte</label>
                                <input type="text" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="passport" name="passport" value="{{ old('passport', $user->passport) }}" placeholder="Ingrese su número de pasaporte">
                            </div>
                            <div style="flex: 1;" class="mb-3">
                                <label for="nombre_en_pasaporte" class="block text-sm font-medium text-gray-700">Nombre en Pasaporte</label>
                                <input
                                    type="text"
                                    class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                    id="nombre_en_pasaporte"
                                    name="nombre_en_pasaporte"
                                    value="{{ old('nombre_en_pasaporte', $user->nombre_en_pasaporte ?? '') }}"
                                    placeholder="Ingrese el nombre tal como aparece en el pasaporte">
                            </div>

                            <!-- Campo País de Expedición del Pasaporte -->
                            <div style="flex: 1;" class="mb-3">
                                <label for="pais_de_expedicion_del_pasaporte" class="block text-sm font-medium text-gray-700">País de Expedición del Pasaporte</label>
                                <input
                                    type="text"
                                    class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                    id="pais_de_expedicion_del_pasaporte"
                                    name="pais_de_expedicion_del_pasaporte"
                                    value="{{ old('pais_de_expedicion_del_pasaporte', $user->pais_de_expedicion_del_pasaporte ?? '') }}"
                                    placeholder="Ingrese el país de expedición del pasaporte">
                            </div>

                            <!-- Campo Fecha de Caducidad del Pasaporte -->
                            <div style="flex: 1;" class="mb-3">
                                <label for="fecha_de_caducidad_del_pasaporte" class="block text-sm font-medium text-gray-700">Fecha de Caducidad del Pasaporte</label>
                                <input
                                    type="date"
                                    class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                    id="fecha_de_caducidad_del_pasaporte"
                                    name="fecha_de_caducidad_del_pasaporte"
                                    value="{{ old('fecha_de_caducidad_del_pasaporte', \Carbon\Carbon::parse($user->fecha_de_caducidad_del_pasaporte ?? now())->format('Y-m-d')) }}"
                                    placeholder="Ingrese la fecha de caducidad del pasaporte">
                            </div>
                        </div>

                        <h2 class="text-1xl font-extrabold tracking-tight text-gray-900 sm:text-2xl mt-4">
                            <span class="ctvSefar block text-indigo-600">Direcciones</span>
                        </h2>
                        <div class="mt-3"  style="display: flex; gap: 16px; flex-wrap: wrap;">
                            <div style="flex: 1;" class="mb-3">
                                <label for="pais_de_residencia" class="block text-sm font-medium text-gray-700">Pais de Residencia</label>
                                <input type="text" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="pais_de_residencia" name="pais_de_residencia" value="{{ old('pais_de_residencia', $user->pais_de_residencia) }}" placeholder="Ingrese su Pais de Residencia">
                            </div>
                            <div style="flex: 1;" class="mb-3">
                                <label for="city" class="block text-sm font-medium text-gray-700">Ciudad de Residencia</label>
                                <input type="text" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="city" name="city" value="{{ old('city', $user->city) }}" placeholder="Ingrese su ciudad de Residencia">
                            </div>
                            <div style="flex: 1;" class="mb-3">
                                <label for="anos_en_residencia_actual" class="block text-sm font-medium text-gray-700">Años en Residencia Actual</label>
                                <input
                                    type="text"
                                    class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                    id="anos_en_residencia_actual"
                                    name="anos_en_residencia_actual"
                                    value="{{ old('anos_en_residencia_actual', $user->anos_en_residencia_actual ?? '') }}"
                                    placeholder="Ingrese los años en la residencia actual">
                            </div>
                        </div>
                        <div class="mt-3"  style="display: flex; gap: 16px; flex-wrap: wrap;">
                            <div style="flex: 1;" class="mb-3">
                                <label for="address" class="block text-sm font-medium text-gray-700">Direccion actual</label>
                                <input type="text" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="address" name="address" value="{{ old('address', $user->address) }}" placeholder="Ingrese su Dirección actual">
                            </div>
                        </div>

                        <div class="mt-3"  style="display: flex; gap: 16px; flex-wrap: wrap;">
                            <div style="flex: 1;" class="mb-3">
                                <label for="pais_de_nacimiento" class="block text-sm font-medium text-gray-700">Pais de Nacimiento</label>
                                <input type="text" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="pais_de_nacimiento" name="pais_de_nacimiento" value="{{ old('pais_de_nacimiento', $user->pais_de_nacimiento) }}" placeholder="Ingrese su País de Nacimiento">
                            </div>
                            <div style="flex: 1;" class="mb-3">
                                <label for="ciudad_de_nacimiento" class="block text-sm font-medium text-gray-700">Ciudad de Nacimiento</label>
                                <input type="text" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="ciudad_de_nacimiento" name="ciudad_de_nacimiento" value="{{ old('ciudad_de_nacimiento', $user->ciudad_de_nacimiento) }}" placeholder="Ingrese su Ciudad de Nacimiento">
                            </div>
                        </div>
                        <div class="mt-3"  style="display: flex; gap: 16px; flex-wrap: wrap;">
                            <div style="flex: 1;" class="mb-3">
                                <label for="direccion_en_el_pais_de_origen" class="block text-sm font-medium text-gray-700">Dirección en el País de Origen</label>
                                <input
                                    type="text"
                                    class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                    id="direccion_en_el_pais_de_origen"
                                    name="direccion_en_el_pais_de_origen"
                                    value="{{ old('direccion_en_el_pais_de_origen', $user->direccion_en_el_pais_de_origen ?? '') }}"
                                    placeholder="Ingrese la dirección en el país de origen">
                            </div>
                        </div>
                        <div class="mt-3"  style="display: flex; gap: 16px; flex-wrap: wrap;">
                            <div style="flex: 1;" class="mb-3">
                                <label for="paises_donde_ha_residido_en_los_ultimos_5_anos" class="block text-sm font-medium text-gray-700">Países donde ha residido en los últimos 5 años</label>
                                <textarea
                                    class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                    id="paises_donde_ha_residido_en_los_ultimos_5_anos"
                                    name="paises_donde_ha_residido_en_los_ultimos_5_anos"
                                    rows="3"
                                    placeholder="Ingrese los países donde ha residido en los últimos 5 años">{{ old('paises_donde_ha_residido_en_los_ultimos_5_anos', $user->paises_donde_ha_residido_en_los_ultimos_5_anos ?? '') }}</textarea>
                            </div>
                        </div>

                        @if(auth()->user()->roles[0]->id == 1 || auth()->user()->roles[0]->id == 15 || auth()->user()->roles[0]->id == 4 || auth()->user()->roles[0]->id == 17)

                            <h2 class="text-1xl font-extrabold tracking-tight text-gray-900 sm:text-2xl mt-4">
                                <span class="ctvSefar block text-indigo-600">Otros datos personales</span>
                            </h2>
                            <div class="mt-3"  style="display: flex; gap: 16px; flex-wrap: wrap;">
                                <div style="flex: 1;" class="mb-3">
                                    <label for="conyuge_interesado_en_proceso" class="block text-sm font-medium text-gray-700">Conyuge Interesado</label>
                                    <input
                                        type="checkbox"
                                        class="mt-1 py-2 px-3 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                        id="conyuge_interesado_en_proceso"
                                        name="conyuge_interesado_en_proceso"
                                        style="width: 33.6px!important; height: 33.6px!important;"
                                        value="1"
                                        {{ old('conyuge_interesado_en_proceso', $user->conyuge_interesado_en_proceso ? 'checked' : '') }}
                                    />
                                </div>
                                <div style="flex: 1;" class="mb-3">
                                    <label for="nombre_completo_del_conyuge" class="block text-sm font-medium text-gray-700">Nombre de Conyuge</label>
                                    <input type="text" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="nombre_completo_del_conyuge" name="nombre_completo_del_conyuge" value="{{ old('nombre_completo_del_conyuge', $user->nombre_completo_del_conyuge) }}" placeholder="Ingrese su Ciudad de Nacimiento">
                                </div>
                            </div>
                            <div class="mt-2" style="display: flex; gap: 16px; flex-wrap: wrap;">
                                <!-- Campo Partida de Nacimiento Simple -->
                                <div style="flex: 1;" class="mb-3">
                                    <label for="partida_de_nacimiento_simple" class="block text-sm font-medium text-gray-700">Partida de Nacimiento Simple</label>
                                    <input
                                        type="text"
                                        class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                        id="partida_de_nacimiento_simple"
                                        name="partida_de_nacimiento_simple"
                                        value="{{ old('partida_de_nacimiento_simple', $user->partida_de_nacimiento_simple ?? '') }}"
                                        placeholder="Ingrese información sobre la partida de nacimiento">
                                </div>
                            </div>
                            <div class="mt-2" style="display: flex; gap: 16px; flex-wrap: wrap;">
                                <!-- Campo Número de IBAN -->
                                <div style="flex: 1;" class="mb-3">
                                    <label for="no_de_iban" class="block text-sm font-medium text-gray-700">Número de IBAN</label>
                                    <input
                                        type="text"
                                        class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                        id="no_de_iban"
                                        name="no_de_iban"
                                        value="{{ old('no_de_iban', $user->no_de_iban ?? '') }}"
                                        placeholder="Ingrese el número de IBAN">
                                </div>

                                <!-- Campo Número de Identificación Nacional (NIF) -->
                                <div style="flex: 1;" class="mb-3">
                                    <label for="no_de_identificacion_nacional__nif_" class="block text-sm font-medium text-gray-700">Número de Identificación Nacional (NIF)</label>
                                    <input
                                        type="text"
                                        class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                        id="no_de_identificacion_nacional__nif_"
                                        name="no_de_identificacion_nacional__nif_"
                                        value="{{ old('no_de_identificacion_nacional__nif_', $user->no_de_identificacion_nacional__nif_ ?? '') }}"
                                        placeholder="Ingrese el número de identificación nacional (NIF)">
                                </div>
                            </div>

                            <div class="mt-2" style="display: flex; gap: 16px; flex-wrap: wrap;">
                                <!-- Campo Vínculo con Antepasados -->
                                <div style="flex: 1;" class="mb-3">
                                    <label class="block text-sm font-medium text-gray-700">Vínculo con Antepasados</label>
                                    @php
                                        $vinculosSeleccionados = explode(';', $user->vinculo_antepasados ?? '');
                                    @endphp
                                    <div class="mt-1">
                                        <label class="flex items-center">
                                            <input
                                                type="checkbox"
                                                name="vinculo_antepasados[]"
                                                value="Padre/Madre"
                                                {{ in_array('Padre/Madre', $vinculosSeleccionados) ? 'checked' : '' }}
                                                class="mr-2">
                                            Padre/Madre
                                        </label>
                                        <label class="flex items-center">
                                            <input
                                                type="checkbox"
                                                name="vinculo_antepasados[]"
                                                value="Abuelo(a)"
                                                {{ in_array('Abuelo(a)', $vinculosSeleccionados) ? 'checked' : '' }}
                                                class="mr-2">
                                            Abuelo(a)
                                        </label>
                                        <label class="flex items-center">
                                            <input
                                                type="checkbox"
                                                name="vinculo_antepasados[]"
                                                value="Bisabuelo(a)"
                                                {{ in_array('Bisabuelo(a)', $vinculosSeleccionados) ? 'checked' : '' }}
                                                class="mr-2">
                                            Bisabuelo(a)
                                        </label>
                                        <label class="flex items-center">
                                            <input
                                                type="checkbox"
                                                name="vinculo_antepasados[]"
                                                value="Tatarabuelo(a)"
                                                {{ in_array('Tatarabuelo(a)', $vinculosSeleccionados) ? 'checked' : '' }}
                                                class="mr-2">
                                            Tatarabuelo(a)
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <h2 class="text-1xl font-extrabold tracking-tight text-gray-900 sm:text-2xl mt-4">
                                <span class="ctvSefar block text-indigo-600">AIV</span>
                            </h2>
                            <div class="mt-3"  style="display: flex; gap: 16px; flex-wrap: wrap;">
                                <div style="flex: 1;" class="mb-3">
                                    <label for="n6__aiv_recibido_en_espana" class="block text-sm font-medium text-gray-700">Fecha AIV recibido en España</label>
                                    <input type="date" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="n6__aiv_recibido_en_espana" name="n6__aiv_recibido_en_espana" value="{{ old('n6__aiv_recibido_en_espana', $user->n6__aiv_recibido_en_espana) }}" placeholder="Fecha AIV Recibido en España">
                                </div>
                                <div style="flex: 1;" class="mb-3">
                                    <label for="n2__aiv_notificacion_aprobado" class="block text-sm font-medium text-gray-700">AIV Notificación Aprobado</label>
                                    <input type="text" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="n2__aiv_notificacion_aprobado" name="n2__aiv_notificacion_aprobado" value="{{ old('n2__aiv_notificacion_aprobado', $user->n2__aiv_notificacion_aprobado) }}" placeholder="Ingrese AIV Notificación Aprobado">
                                </div>
                            </div>

                            <h2 class="text-1xl font-extrabold tracking-tight text-gray-900 sm:text-2xl mt-4">
                                <span class="ctvSefar block text-indigo-600">AACS</span>
                            </h2>
                            <div class="mt-3"  style="display: flex; gap: 16px; flex-wrap: wrap;">
                                <div style="flex: 1;" class="mb-3">
                                    <label for="n1__aacs_introducido_asociacion" class="block text-sm font-medium text-gray-700">AACS Introducido Asociacion</label>
                                    <input type="text" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="n1__aacs_introducido_asociacion" name="n1__aacs_introducido_asociacion" value="{{ old('n1__aacs_introducido_asociacion', $user->n1__aacs_introducido_asociacion) }}" placeholder="Ingrese AACS INTRODUCIDO ASOCIACIÓN">
                                </div>
                            </div>
                            <div class="mt-3"  style="display: flex; gap: 16px; flex-wrap: wrap;">
                                <div style="flex: 1;" class="mb-3">
                                    <label for="n2__aacs_notificacion_aprobado" class="block text-sm font-medium text-gray-700">AACS Notificacion Aprobado</label>
                                    <input type="text" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="n2__aacs_notificacion_aprobado" name="n2__aacs_notificacion_aprobado" value="{{ old('n2__aacs_notificacion_aprobado', $user->n2__aacs_notificacion_aprobado) }}" placeholder="Ingrese AACS Notificación Aprobado">
                                </div>
                                <div style="flex: 1;" class="mb-3">
                                    <label for="n2__fecha_aacs_notificacion_aprobado" class="block text-sm font-medium text-gray-700">Fecha AACS Notificacion Aprobado</label>
                                    <input type="date" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="n2__fecha_aacs_notificacion_aprobado" name="n2__fecha_aacs_notificacion_aprobado" value="{{ old('n2__fecha_aacs_notificacion_aprobado', $user->n2__fecha_aacs_notificacion_aprobado) }}" placeholder="Ingrese Fecha AACS Notificacion Aprobado">
                                </div>
                            </div>
                            <div class="mt-3"  style="display: flex; gap: 16px; flex-wrap: wrap;">
                                <div style="flex: 1;" class="mb-3">
                                    <label for="n4__aacs_retirado_asociacion" class="block text-sm font-medium text-gray-700">AACS Retirado Asociacion</label>
                                    <input type="text" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="n4__aacs_retirado_asociacion" name="n4__aacs_retirado_asociacion" value="{{ old('n4__aacs_retirado_asociacion', $user->n4__aacs_retirado_asociacion) }}" placeholder="Ingrese aacs retirado asociacion">
                                </div>
                                <div style="flex: 1;" class="mb-3">
                                    <label for="n6__aacs_recibido_en_espana" class="block text-sm font-medium text-gray-700">AACS Recibido en España</label>
                                    <input type="text" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="n6__aacs_recibido_en_espana" name="n6__aacs_recibido_en_espana" value="{{ old('n6__aacs_recibido_en_espana', $user->n6__aacs_recibido_en_espana) }}" placeholder="AACS Recibido en España">
                                </div>
                            </div>

                            <h2 class="text-1xl font-extrabold tracking-tight text-gray-900 sm:text-2xl mt-4">
                                <span class="ctvSefar block text-indigo-600">CCSE</span>
                            </h2>
                            <div class="mt-3" style="display: flex; gap: 16px; flex-wrap: wrap;">
                                <div style="flex: 1;" class="mb-3">
                                    <label for="ccse_archivado_espana" class="block text-sm font-medium text-gray-700">CCSE Archivado España</label>
                                    <input type="text" value="{{ old('ccse_archivado_espana', $user->ccse_archivado_espana) }}" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="ccse_archivado_espana" name="ccse_archivado_espana" placeholder="CCSE Archivado España">
                                </div>
                                <div style="flex: 1;" class="mb-3">
                                    <label for="ccse_resultado" class="block text-sm font-medium text-gray-700">CCSE Resultado</label>
                                    <input type="text" value="{{ old('ccse_resultado', $user->ccse_resultado) }}" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="ccse_resultado" name="ccse_resultado">
                                </div>
                            </div>

                            <h2 class="text-1xl font-extrabold tracking-tight text-gray-900 sm:text-2xl mt-4">
                                <span class="ctvSefar block text-indigo-600">CIF</span>
                            </h2>
                            <div class="mt-3" style="display: flex; gap: 16px; flex-wrap: wrap;">
                                <div style="flex: 1;" class="mb-3">
                                    <label for="cif" class="block text-sm font-medium text-gray-700">CIF</label>
                                    <input type="text" value="{{ old('cif', $user->cif) }}" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="cif" name="cif" placeholder="CIF">
                                </div>
                            </div>

                            <h2 class="text-1xl font-extrabold tracking-tight text-gray-900 sm:text-2xl mt-4">
                                <span class="ctvSefar block text-indigo-600">Otros datos</span>
                            </h2>
                            <div class="mt-3" style="display: flex; gap: 16px; flex-wrap: wrap;">
                                <div style="flex: 1;" class="mb-3">
                                    <label for="n1__acta_notarial" class="block text-sm font-medium text-gray-700">Acta Notarial</label>
                                    <input type="text" value="{{ old('n1__acta_notarial', $user->n1__acta_notarial) }}" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="n1__acta_notarial" name="n1__acta_notarial" placeholder="Ingrese Acta Notarial">
                                </div>
                                <div style="flex: 1;" class="mb-3">
                                    <label for="n1__f__peticion_por_genealogia" class="block text-sm font-medium text-gray-700">Fecha Petición por Genealogía</label>
                                    <input type="date" value="{{ old('n1__f__peticion_por_genealogia', $user->n1__f__peticion_por_genealogia) }}" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="n1__f__peticion_por_genealogia" name="n1__f__peticion_por_genealogia">
                                </div>
                                <div style="flex: 1;" class="mb-3">
                                    <label for="n1__f__solicitado_por_genealogia" class="block text-sm font-medium text-gray-700">Fecha Solicitado por Genealogía</label>
                                    <input type="date" value="{{ old('n1__f__solicitado_por_genealogia', $user->n1__f__solicitado_por_genealogia) }}" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="n1__f__solicitado_por_genealogia" name="n1__f__solicitado_por_genealogia">
                                </div>
                            </div>

                            <div class="mt-3" style="display: flex; gap: 16px; flex-wrap: wrap;">
                                <div style="flex: 1;" class="mb-3">
                                    <label for="n2__f_solicitud_mayor_info" class="block text-sm font-medium text-gray-700">Fecha Solicitud Mayor Información</label>
                                    <input type="date" value="{{ old('n2__f_solicitud_mayor_info', $user->n2__f_solicitud_mayor_info) }}" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="n2__f_solicitud_mayor_info" name="n2__f_solicitud_mayor_info">
                                </div>
                                <div style="flex: 1;" class="mb-3">
                                    <label for="n2__f__de_solicitud_al_cliente" class="block text-sm font-medium text-gray-700">Fecha Solicitud al Cliente</label>
                                    <input type="date" value="{{ old('n2__f__de_solicitud_al_cliente', $user->n2__f__de_solicitud_al_cliente) }}" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="n2__f__de_solicitud_al_cliente" name="n2__f__de_solicitud_al_cliente">
                                </div>
                                <div style="flex: 1;" class="mb-3">
                                    <label for="n3__estatus_de_nacionalidad" class="block text-sm font-medium text-gray-700">Estatus de Nacionalidad</label>
                                    <select class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="n3__estatus_de_nacionalidad" name="n3__estatus_de_nacionalidad">
                                        <option value="" {{ old('n3__estatus_de_nacionalidad', $user->n3__estatus_de_nacionalidad ?? '') === '' ? 'selected' : '' }}></option>
                                        <option value="Concedida" {{ old('n3__estatus_de_nacionalidad', $user->n3__estatus_de_nacionalidad ?? '') === 'Concedida' ? 'selected' : '' }}>Concedida</option>
                                        <option value="En Tramitación" {{ old('n3__estatus_de_nacionalidad', $user->n3__estatus_de_nacionalidad ?? '') === 'En Tramitación' ? 'selected' : '' }}>En Tramitación</option>
                                    </select>
                                </div>
                            </div>

                            <div class="mt-3" style="display: flex; gap: 16px; flex-wrap: wrap;">
                                <div style="flex: 1;" class="mb-3">
                                    <label for="n3__f___recordatorio_filiacion" class="block text-sm font-medium text-gray-700">Fecha Recordatorio Filiación</label>
                                    <input type="date" value="{{ old('n3__f___recordatorio_filiacion', $user->n3__f___recordatorio_filiacion) }}" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="n3__f___recordatorio_filiacion" name="n3__f___recordatorio_filiacion">
                                </div>
                                <div style="flex: 1;" class="mb-3">
                                    <label for="n3__fcje_registro" class="block text-sm font-medium text-gray-700">FCJE Registro</label>
                                    <input type="date" value="{{ old('n3__fcje_registro', $user->n3__fcje_registro) }}" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="n3__fcje_registro" name="n3__fcje_registro">
                                </div>
                                <div style="flex: 1;" class="mb-3">
                                    <label for="n3__fecha_de_recordatorio" class="block text-sm font-medium text-gray-700">Fecha de Recordatorio</label>
                                    <input type="date" value="{{ old('n3__fecha_de_recordatorio', $user->n3__fecha_de_recordatorio) }}" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="n3__fecha_de_recordatorio" name="n3__fecha_de_recordatorio">
                                </div>
                            </div>

                            <div class="mt-3" style="display: flex; gap: 16px; flex-wrap: wrap;">
                                <div style="flex: 1;" class="mb-3">
                                    <label for="n4__aacs_retirado_asociacion" class="block text-sm font-medium text-gray-700">AACS Retirado Asociación</label>
                                    <input type="text" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="n4__aacs_retirado_asociacion" name="n4__aacs_retirado_asociacion" value="{{ old('n4__aacs_retirado_asociacion', $user->n4__aacs_retirado_asociacion ?? '') }}" placeholder="Ingrese AACS Retirado Asociación">
                                </div>
                                <div style="flex: 1;" class="mb-3">
                                    <label for="n4__f__entregado_genealogia" class="block text-sm font-medium text-gray-700">Fecha Entregado Genealogía</label>
                                    <input type="date" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="n4__f__entregado_genealogia" name="n4__f__entregado_genealogia" value="{{ old('n4__f__entregado_genealogia', $user->n4__f__entregado_genealogia ?? '') }}">
                                </div>
                                <div style="flex: 1;" class="mb-3">
                                    <label for="n4__f__enviada_a_genealogia" class="block text-sm font-medium text-gray-700">Fecha Enviada a Genealogía</label>
                                    <input type="date" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="n4__f__enviada_a_genealogia" name="n4__f__enviada_a_genealogia" value="{{ old('n4__f__enviada_a_genealogia', $user->n4__f__enviada_a_genealogia ?? '') }}">
                                </div>
                            </div>

                            <div class="mt-3" style="display: flex; gap: 16px; flex-wrap: wrap;">
                                <div style="flex: 1;" class="mb-3">
                                    <label for="n4__fcje_certifi__descargado" class="block text-sm font-medium text-gray-700">FCJE Certificado Descargado</label>
                                    <input type="date" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="n4__fcje_certifi__descargado" name="n4__fcje_certifi__descargado" value="{{ old('n4__fcje_certifi__descargado', $user->n4__fcje_certifi__descargado ?? '') }}">
                                </div>
                                <div style="flex: 1;" class="mb-3">
                                    <label for="n4__otros_nombres" class="block text-sm font-medium text-gray-700">Otros Nombres</label>
                                    <input type="text" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="n4__otros_nombres" name="n4__otros_nombres" value="{{ old('n4__otros_nombres', $user->n4__otros_nombres ?? '') }}" placeholder="Ingrese Otros Nombres">
                                </div>
                                <div style="flex: 1;" class="mb-3">
                                    <label for="n5__fecha_de_registro" class="block text-sm font-medium text-gray-700">Fecha de Registro</label>
                                    <input type="date" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="n5__fecha_de_registro" name="n5__fecha_de_registro" value="{{ old('n5__fecha_de_registro', $user->n5__fecha_de_registro ?? '') }}">
                                </div>
                            </div>


                            <div class="mt-3" style="display: flex; gap: 16px; flex-wrap: wrap;">
                                <div style="flex: 1;" class="mb-3">
                                    <label for="estado_de_datos_y_documentos_de_los_antepasados" class="block text-sm font-medium text-gray-700">Estado de datos y documentos de antepasados</label>
                                    <select class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="estado_de_datos_y_documentos_de_los_antepasados" name="estado_de_datos_y_documentos_de_los_antepasados">
                                        <option value="" {{ old('estado_de_datos_y_documentos_de_los_antepasados', $user->estado_de_datos_y_documentos_de_los_antepasados ?? '') === '' ? 'selected' : '' }}></option>
                                        <option value="Conoce los datos y tiene los documentos" {{ old('estado_de_datos_y_documentos_de_los_antepasados', $user->estado_de_datos_y_documentos_de_los_antepasados ?? '') === 'Conoce los datos y tiene los documentos' ? 'selected' : '' }}>Conoce los datos y tiene los documentos</option>
                                        <option value="Conoce los datos, pero no tiene los documentos" {{ old('estado_de_datos_y_documentos_de_los_antepasados', $user->estado_de_datos_y_documentos_de_los_antepasados ?? '') === 'Conoce los datos, pero no tiene los documentos' ? 'selected' : '' }}>Conoce los datos, pero no tiene los documentos</option>
                                        <option value="No conoce los datos" {{ old('estado_de_datos_y_documentos_de_los_antepasados', $user->estado_de_datos_y_documentos_de_los_antepasados ?? '') === 'No conoce los datos' ? 'selected' : '' }}>No conoce los datos</option>
                                    </select>
                                </div>
                            </div>

                        @endif

                        @if(auth()->user()->roles[0]->id == 1 || auth()->user()->roles[0]->id == 15 || auth()->user()->roles[0]->id == 4 || auth()->user()->roles[0]->id == 17)
                            <button type="button" id="guardar-datos" class="cfrSefar btn btn-primary mt-3">Guardar</button>
                        @endif
                    </form>
                </div>

                <div class="tab-pane fade" id="mypassword" role="tabpanel" aria-labelledby="mypassword-tab">
                    <form id="clientChangePasswordForm">
                        @csrf
                        <div class="mt-2" style="display: flex; gap: 16px; flex-wrap: wrap;">
                            <div style="flex: 1;" class="mb-3">
                                <label for="password" class="block text-sm font-medium text-gray-700">Contraseña</label>
                                <input class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="password" name="password" type="password" placeholder="Ingrese su contraseña">
                            </div>
                        </div>
                        <div class="mt-2" style="display: flex; gap: 16px; flex-wrap: wrap;">
                            <div style="flex: 1;" class="mb-3">
                                <label for="repeat_password" class="block text-sm font-medium text-gray-700">Repetir Contraseña</label>
                                <input class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="repeat_password" name="repeat_password" type="password" placeholder="Repite tu contraseña">
                            </div>
                        </div>
                        <button type="button" id="clientSubmitButton" class="cfrSefar btn btn-primary mt-3">Guardar</button>
                    </form>
                </div>

                <div class="tab-pane fade" id="adminchangepassword" role="tabpanel" aria-labelledby="adminchangepassword-tab">
                    <form id="adminChangePasswordForm">
                        @csrf
                        <input type="hidden" id="id" name="id" value="{{ $user->id }}">
                        <div class="mt-2" style="display: flex; gap: 16px; flex-wrap: wrap;">
                            <div style="flex: 1;" class="mb-3">
                                <label for="password" class="block text-sm font-medium text-gray-700">Contraseña</label>
                                <input class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="password" name="password" type="password" placeholder="Ingrese su contraseña">
                            </div>
                        </div>
                        <button type="button" id="submitButton" class="cfrSefar btn btn-primary mt-3">Guardar</button>
                    </form>
                </div>

                <div class="tab-pane fade" id="familiars" role="tabpanel" aria-labelledby="familiars-tab">

                    <center>
                        @if(auth()->user()->roles[0]->id == 5)
                        <a href="/tree/" class="cfrSefar mb-3 inline-flex items-center justify-center px-3 py-1 border border-transparent text-base font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                            Ir al Arbol
                        </a>
                        @else
                        <a href="/tree/{{$user->passport}}" class="cfrSefar mb-3 inline-flex items-center justify-center px-3 py-1 border border-transparent text-base font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                            Ir al Arbol
                        </a>
                        @endif
                    </center>

                    <table id="familiarsTable" class="min-w-full divide-y divide-gray-200 w-100">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col">Nombre</th>
                                <th scope="col">Parentesco</th>
                                <th scope="col">Generacion</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach ( $columnasparatabla as $generacion => $grupo )
                                @foreach ( $grupo as $persona)
                                    @if (isset($persona["showbtn"]) && $persona["showbtn"] == 2)
                                    <tr>
                                        <td>{{$persona["Nombres"]}} {{$persona["Apellidos"]}}</td>
                                        <td>{{$persona["parentesco"]}}</td>
                                        <td>{{$generacion+1}}</td>
                                    </tr>
                                    @endif
                                @endforeach
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="tab-pane fade" id="documents" role="tabpanel" aria-labelledby="documents-tab">
                    <table id="documentsTable" class="min-w-full divide-y divide-gray-200 w-100">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col">Nombre del Archivo</th>
                                <th scope="col">Ver Archivo</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach ( $archivos as $archivo )
                                <tr>
                                    <td>{{$archivo["file"]}}</td>
                                    <td>
                                        <a href="/viewfile/{{$archivo["id"]}}" target="_blank" class="btn btn-primary">
                                            <i class="fas fa-file"></i>
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="tab-pane fade" id="payments" role="tabpanel" aria-labelledby="payments-tab">

                    <table id="paymentsTable" class="min-w-full divide-y divide-gray-200 w-100">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col"># de Comprobante</th>
                                <th scope="col">Método de pago</th>
                                <th scope="col">Servicios contratados</th>
                                <th scope="col">Monto pagado</th>
                                <th scope="col">Ver Comprobante</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach ( $facturas as $num => $factura )
                                <tr>
                                    <td>{{$num + 1}}</td>
                                    <td>
                                        @if ($factura["met"] == "stripe")
                                            Tarjeta de Crédito/Débito
                                        @elseif ($factura["met"] == "cupon")
                                            Cupón
                                        @elseif ($factura["met"] == "paypal")
                                            PayPal
                                        @endif
                                    </td>
                                    <td>
                                        @php
                                            $monto = 0;
                                            $totalCompras = count($factura["compras"]);
                                        @endphp
                                        @foreach($factura["compras"] as $index => $compra)
                                            @php
                                                $monto += $compra["monto"];
                                            @endphp
                                            @if($compra["servicio_hs_id"])
                                                {{$compra["servicio_hs_id"]}}
                                            @else
                                                @if ($compra["phasenum"]<10)
                                                    Pago Fase {{$compra["phasenum"]}}
                                                @elseif($compra["phasenum"]==99)
                                                    Pago FCJE/CIL
                                                @elseif($compra["phasenum"]==98)
                                                    Pago Carta de Naturaleza
                                                @endif
                                            @endif
                                            @if($index < $totalCompras - 1)
                                                <br> <!-- Agregar salto de línea si no es el último -->
                                            @endif
                                        @endforeach
                                    </td>
                                    <td>
                                        {{$monto}} €
                                    </td>
                                    <td>
                                        @if(auth()->user()->roles[0]->id == 1)
                                            <a href="{{ route('viewcomprobante', ['id' => $factura['id']]) }}" target="_blank" class="btn btn-primary">
                                                <i class="fas fa-receipt"></i>
                                            </a>
                                        @elseif(auth()->user()->roles[0]->id == 5)
                                            <a href="{{ route('viewcomprobantecliente', ['id' => $factura['id']]) }}" target="_blank" class="btn btn-primary">
                                                <i class="fas fa-receipt"></i>
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="tab-pane fade" id="paymentspen" role="tabpanel" aria-labelledby="payments-tab">

                    <table id="paymentsPenTable" class="min-w-full divide-y divide-gray-200 w-100">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col">Descripción</th>
                                <th scope="col">Monto</th>
                                @if(auth()->user()->roles[0]->id == 5)
                                <th scope="col">Acción</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($comprasSinDealNoPagadas as $compra)
                                <tr>
                                    <td>{{ $compra->descripcion }}</td>
                                    <td>{{ $compra->monto }} €</td>
                                    @if(auth()->user()->roles[0]->id == 5)
                                    <td>
                                        <a href="/pay" class="btn btn-warning">
                                            <i class="fas fa-credit-card"></i> Pagar ahora
                                        </a>
                                    </td>
                                    @endif
                                </tr>
                            @endforeach

                            @foreach($comprasConDealNoPagadas as $compra)
                                <tr>
                                    <td>{{ $compra->descripcion }}</td>
                                    <td>{{ $compra->monto }} €</td>
                                    @if(auth()->user()->roles[0]->id == 5)
                                    <td>
                                        <form action="/payfases" method="POST">
                                            @csrf
                                            <input type="hidden" name="id" value="{{ $compra->id }}">
                                            <button type="submit" class="btn btn-danger">
                                                <i class="fas fa-credit-card"></i> Pagar ahora
                                            </button>
                                        </form>
                                    </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="tab-pane fade" id="etiquetado" role="tabpanel" aria-labelledby="etiquetado-tab">
                    @if ($boardId != 0)
                    <h2 class="text-1xl font-extrabold tracking-tight text-gray-900 sm:text-2xl mt-4 mb-4">
                        <span class="ctvSefar block text-indigo-600">Tablero actual: {{ $boardName }}</span>
                    </h2>

                    <form id="dynamicForm" method="POST">
                        @csrf

                        <input name='boardId' type="hidden" value='{{$boardId}}'>

                        <input name='user_id' type="hidden" value='{{$user->id}}'>
                        <!-- Ejemplo de grid con máximo 3 columnas -->

                        <div style="display: flex; gap: 16px; flex-wrap: wrap;">
                            @foreach ($mondayFormBuilder as $field)
                                @if (in_array($field['type'], [
                                    "name", "subtasks", "auto_number", "progress", "creation_log", "link", "integration", "item_id", "formula", "board_relation", "mirror", "email"
                                ]))
                                    @continue
                                @endif

                                @if (in_array($field['title'], [
                                    "No. PASAPORTE", "FECHA NACIMIENTO", "PADRE", "MADRE", "Record ID"
                                ]))
                                    @continue
                                @endif

                                @if ($field['type'] === 'long_text')
                                    <!-- Textarea abarca toda la fila -->
                                    <div style="flex: 1 1 100%;" class="mb-3">
                                        <label for="{{ $field['column_id'] }}" class="block text-sm font-medium text-gray-700">
                                            {{ $field['title'] }}
                                        </label>
                                        <textarea
                                            class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                            id="{{ $field['column_id'] }}"
                                            name="{{ $field['column_id'] }}"
                                            rows="3"
                                            placeholder="Ingrese {{ strtolower($field['title']) }}"
                                        >{{ old($field['column_id'], $dataMonday[$field['column_id']] ?? '') }}</textarea>
                                    </div>
                                @else
                                    <!-- Otros campos -->
                                    <div style="flex: 1 1 calc(33.33% - 16px);" class="mb-3">
                                        <label for="{{ $field['column_id'] }}" class="block text-sm font-medium text-gray-700">
                                            {{ $field['title'] }}
                                        </label>

                                        @switch($field['type'])
                                            @case('text')
                                                <input
                                                    type="text"
                                                    class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                                    id="{{ $field['column_id'] }}"
                                                    name="{{ $field['column_id'] }}"
                                                    value="{{ old($field['column_id'], $dataMonday[$field['column_id']] ?? '') }}"
                                                    placeholder="Ingrese {{ strtolower($field['title']) }}"
                                                >
                                                @break

                                            @case('date')
                                                <input
                                                    type="date"
                                                    class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                                    id="{{ $field['column_id'] }}"
                                                    name="{{ $field['column_id'] }}"
                                                    value="{{ old($field['column_id'], $dataMonday[$field['column_id']] ?? '') }}"
                                                >
                                                @break

                                            @case('people')
                                                <select
                                                    class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                                    id="{{ $field['column_id'] }}"
                                                    name="{{ $field['column_id'] }}"
                                                >
                                                    <option value="" disabled selected>Seleccione {{ strtolower($field['title']) }}</option>
                                                    @foreach ($usuariosMonday as $usuario)
                                                        <option value="{{ $usuario['email'] }}"
                                                            {{ old($field['column_id'], $dataMonday[$field['column_id']] ?? '') == $usuario['name'] ? 'selected' : '' }}>
                                                            {{ $usuario['name'] }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                                @break

                                            @case('dropdown')
                                                @if (!($field['title'] == "ETIQUETAS" || $field['title'] == "ETIQUETA"))
                                                <select
                                                    class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                                    id="{{ $field['column_id'] }}"
                                                    name="{{ $field['column_id'] }}"
                                                >
                                                    <option value="" disabled selected>Seleccione {{ strtolower($field['title']) }}</option>
                                                    @foreach ($field['settings']['labels'] ?? [] as $option)
                                                        <option value="{{ $option['id'] }}"
                                                            {{ old($field['column_id'], $dataMonday[$field['column_id']] ?? '') == $option['name'] ? 'selected' : '' }}>
                                                            {{ $option['name'] }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                                @else
                                                    <!-- Incluir Tagify CSS -->
                                                    <link href="https://cdn.jsdelivr.net/npm/@yaireo/tagify/dist/tagify.css" rel="stylesheet" type="text/css" />

                                                    <!-- Campo de entrada para Tagify -->
                                                    <input
                                                        style="margin:0;"
                                                        class="block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                                        id="{{ $field['column_id'] }}"
                                                        name="{{ $field['column_id'] }}"
                                                        value="{{ isset($dataMonday[$field['column_id']]) ? $dataMonday[$field['column_id']] : '' }}"
                                                    />

                                                    <!-- Incluir Tagify JS -->
                                                    <script src="https://cdn.jsdelivr.net/npm/@yaireo/tagify/dist/tagify.min.js"></script>

                                                    <!-- Inicializar Tagify -->
                                                    <script>
                                                        document.addEventListener('DOMContentLoaded', function() {
                                                            const input = document.getElementById('{{ $field['column_id'] }}');

                                                            // Convertir el input en un componente de etiquetas
                                                            const tagify = new Tagify(input, {
                                                                whitelist: [
                                                                    @foreach ($field['settings']['labels'] ?? [] as $option)
                                                                        { id: "{{ $option['id'] }}", value: "{{ $option['name'] }}" },
                                                                    @endforeach
                                                                ],
                                                                dropdown: {
                                                                    enabled: 1, // Mostrar dropdown con sugerencias
                                                                    maxItems: 10, // Máximo de sugerencias visibles
                                                                },
                                                                enforceWhitelist: true, // Solo permitir opciones de la lista blanca
                                                            });

                                                            // Pre-seleccionar valores si existen
                                                            const selectedValues = "{{ isset($dataMonday[$field['column_id']]) ? $dataMonday[$field['column_id']] : '' }}";
                                                            if (selectedValues) {
                                                                tagify.addTags(selectedValues.split(','));
                                                            }

                                                            // Asegurar que el valor enviado sea una cadena separada por comas
                                                            input.closest('form').addEventListener('submit', function() {
                                                                const tags = tagify.value.map(tag => tag.value).join(',');
                                                                input.value = tags; // Actualizar el valor del input antes de enviar el formulario
                                                            });
                                                        });
                                                    </script>
                                                @endif
                                                @break

                                            @case('status')
                                                <select
                                                    class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                                    id="{{ $field['column_id'] }}"
                                                    name="{{ $field['column_id'] }}"
                                                >
                                                    <option value="" disabled selected>Seleccione {{ strtolower($field['title']) }}</option>
                                                    @foreach ($field['settings']['labels'] ?? [] as $key => $label)
                                                        <option value="{{ $label }}"
                                                            {{ old($field['column_id'], $dataMonday[$field['column_id']] ?? '') == $label ? 'selected' : '' }}>
                                                            {{ $label }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                                @break

                                            @default
                                                <input
                                                    type="text"
                                                    class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                                    id="{{ $field['column_id'] }}"
                                                    name="{{ $field['column_id'] }}"
                                                    value="{{ old($field['column_id'], $dataMonday[$field['column_id']] ?? '') }}"
                                                    placeholder="Ingrese {{ strtolower($field['title']) }}"
                                                >
                                        @endswitch
                                    </div>
                                @endif
                            @endforeach
                        </div>
                        <!-- Botón de envío -->
                        <div class="mt-3">
                            <button type="button" id="etiquetadosend" class="bg-indigo-600 text-white px-4 py-2 rounded-md">
                                Guardar
                            </button>
                        </div>
                    </form>
                    @else
                        <h2 class="text-1xl font-extrabold tracking-tight text-gray-900 sm:text-2xl mt-4 mb-4">
                            <span class="ctvSefar block text-indigo-600">Este cliente no se encuentra en Monday</span>
                        </h2>
                    @endif
                </div>

                <div class="tab-pane fade" id="negocios" role="tabpanel" aria-labelledby="negocios-tab">
                    <table id="dealsTable" class="min-w-full divide-y divide-gray-200 w-100">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col">Nombre del Negocio</th>
                                <th scope="col">Ver info</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach ( $negocios as $negocio )
                                <tr>
                                    <td>{{$negocio["servicio_solicitado"]}}<br>{!!$negocio["hubspot_id"] ? "<small>Se encuentra en <b><a href='https://app.hubspot.com/contacts/20053496/record/0-3/".$negocio['hubspot_id']."'>Hubspot</a></b></small>" : ''!!}{!! $negocio["teamleader_id"] ? "<small> y en <b><a href='https://focus.teamleader.eu/web/projects/".$negocio['teamleader_id']."'>Teamleader</a></b></small>" : '' !!}</td>
                                    <td>
                                        <a href="/deal/{{$negocio['id']}}/edit" target="_blank" class="btn btn-primary">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="tab-pane fade" id="admin-req" role="tabpanel" aria-labelledby="admin-req-tab">
                    <div class="d-flex justify-content-end mb-3">
                        <button class="cfrSefar inline-flex items-center justify-center px-3 py-2 border border-transparent text-base font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700" data-bs-toggle="modal" data-bs-target="#crearSolicitudModal">
                            + Nueva solicitud
                        </button>
                    </div>

                    <!-- Modal para crear solicitud -->
                    <div class="modal fade" id="crearSolicitudModal" tabindex="-1" aria-labelledby="crearSolicitudLabel" aria-hidden="true">
                        <div class="modal-dialog">
                            <form id="solicitudForm" method="POST">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Nueva solicitud de documento</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="mb-3">
                                            <label class="form-label">Nombre del documento</label>
                                            <input type="text" name="document_name" class="form-control" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Tipo de documento</label>
                                            <select name="document_type" class="form-select" required>
                                                <option value="juridico">Jurídico</option>
                                                <option value="genealogico">Genealógico</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                        <button type="submit" class="btn btn-primary">Crear solicitud</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Modal para editar solicitud -->
                    <div class="modal fade" id="editarSolicitudModal" tabindex="-1" aria-labelledby="editarSolicitudLabel" aria-hidden="true">
                        <div class="modal-dialog">
                            <form id="editarSolicitudForm" method="POST">
                                <input type="hidden" name="request_id" id="edit_request_id">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Editar solicitud de documento</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="mb-3">
                                            <label class="form-label">Nombre del documento</label>
                                            <input type="text" name="document_name" id="edit_document_name" class="form-control" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Tipo de documento</label>
                                            <select name="document_type" id="edit_document_type" class="form-select" required>
                                                <option value="juridico">Jurídico</option>
                                                <option value="genealogico">Genealógico</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                        <button type="submit" class="btn btn-primary">Guardar cambios</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <table class="table table-sm" id="solicitudesTable">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Tipo</th>
                                <th>Estatus</th>
                                <th>Archivo</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($documentRequests as $req)
                            <tr data-id="{{ $req->id }}">
                                <td>{{ $req->document_name }}</td>
                                <td>{{ ucfirst($req->document_type) }}</td>
                                <td>
                                    <span class="badge
                                        @if($req->status === 'aprobada') bg-success
                                        @elseif($req->status === 'rechazada') bg-danger
                                        @elseif($req->status === 'no_documento') bg-warning text-dark
                                        @else bg-secondary @endif">
                                        {{ ucfirst(str_replace('_',' ', $req->status)) }}
                                    </span>
                                </td>
                                <td>
                                    @if($req->file_path)
                                    <a href="{{ Storage::disk('s3')->url($req->file_path) }}" target="_blank">Ver archivo</a>
                                    @else
                                    —
                                    @endif
                                </td>
                                <td>
                                    @if(!in_array($req->status, ['aprobada', 'rechazada', 'no_documento']))
                                    <button class="btn btn-sm btn-primary editar" data-id="{{ $req->id }}">Editar</button>
                                    <button class="btn btn-sm btn-success aprobar" data-id="{{ $req->id }}">Aprobar</button>
                                    <button class="btn btn-sm btn-danger rechazar" data-id="{{ $req->id }}">Rechazar</button>
                                    @endif
                                    <button class="btn btn-sm btn-outline-danger eliminar" data-id="{{ $req->id }}">Eliminar</button>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <script>
                    $(document).ready(function () {
                        // Configuración de DataTables
                        $('#solicitudesTable').DataTable({
                            "language": {
                                "lengthMenu": "Mostrar _MENU_ resultados por página",
                                "zeroRecords": "No hay resultados",
                                "info": "Página _PAGE_ de _PAGES_",
                                "infoEmpty": "No hay resultados"
                            },
                            columnDefs: [
                                { "width": "20%", "targets": 0 }, // Nombre
                                { "width": "15%", "targets": 1 }, // Tipo
                                { "width": "15%", "targets": 2 }, // Estatus
                                { "width": "25%", "targets": 3 }, // Archivo
                                { "width": "25%", "targets": 4 }  // Acciones
                            ]
                        });

                        // Configuración CSRF para AJAX
                        $.ajaxSetup({
                            headers: {
                                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                            }
                        });

                        // Crear nueva solicitud - Versión corregida
                        $('#solicitudForm').on('submit', function (e) {
                            e.preventDefault();

                            $.ajax({
                                url: "{{ route('admin.requests.store', $user) }}",
                                method: 'POST',
                                data: $(this).serialize(),
                                success: function (res) {
                                    const table = $('#solicitudesTable').DataTable();

                                    const formattedStatus = res.status.replace(/_/g, ' ').replace(/\b\w/g, char => char.toUpperCase());

                                    // Crear el nuevo elemento como un array para DataTables
                                    const newRowData = [
                                        res.document_name,
                                        res.document_type.charAt(0).toUpperCase() + res.document_type.slice(1),
                                        '<span class="badge bg-secondary">' + formattedStatus + '</span>',
                                        '—',
                                        '<button class="btn btn-sm btn-primary editar" data-id="'+res.id+'">Editar</button> ' +
                                        '<button class="btn btn-sm btn-success aprobar" data-id="'+res.id+'">Aprobar</button> ' +
                                        '<button class="btn btn-sm btn-danger rechazar" data-id="'+res.id+'">Rechazar</button> ' +
                                        '<button class="btn btn-sm btn-outline-danger eliminar" data-id="'+res.id+'">Eliminar</button>'
                                    ];

                                    // Añadir la nueva fila usando la API de DataTables
                                    table.row.add(newRowData).draw();

                                    // Limpiar y cerrar el modal
                                    $('#crearSolicitudModal').modal('hide');
                                    $('#solicitudForm')[0].reset();

                                    Swal.fire({
                                        icon: 'success',
                                        title: 'Solicitud creada',
                                        text: 'La solicitud se creó correctamente.'
                                    });
                                },
                                error: function () {
                                    alert('Error al crear solicitud');
                                }
                            });
                        });

                        // Editar solicitud - Abrir modal
                        $('#solicitudesTable').on('click', '.editar', function () {
                            const id = $(this).data('id');
                            const row = $(this).closest('tr');

                            $('#edit_request_id').val(id);
                            $('#edit_document_name').val(row.find('td:eq(0)').text());
                            $('#edit_document_type').val(row.find('td:eq(1)').text().toLowerCase());

                            $('#editarSolicitudModal').modal('show');
                        });

                        // Editar solicitud - Enviar formulario
                        $('#editarSolicitudForm').on('submit', function (e) {
                            e.preventDefault();
                            const id = $('#edit_request_id').val();

                            $.ajax({
                                url: `/admin/requests/${id}`,
                                method: 'PUT',
                                data: $(this).serialize(),
                                success: function (res) {
                                    // Verifica que las propiedades necesarias existen
                                    const documentName = res.data.document_name || '';
                                    let documentType = res.data.document_type.charAt(0).toUpperCase() + res.data.document_type.slice(1);

                                    // Actualiza la fila
                                    const row = $(`tr[data-id="${id}"]`);
                                    if (row.length) {
                                        row.find('td:eq(0)').text(documentName);
                                        row.find('td:eq(1)').text(documentType);
                                        $('#editarSolicitudModal').modal('hide');

                                        // Si usas DataTables
                                        if ($.fn.DataTable.isDataTable('#solicitudesTable')) {
                                            $('#solicitudesTable').DataTable().draw();
                                        }

                                        Swal.fire({
                                            icon: 'success',
                                            title: 'Solicitud editada',
                                            text: 'La solicitud se editó correctamente.'
                                        });
                                    } else {
                                        console.error('No se encontró la fila con ID:', id);
                                    }
                                },
                                error: function () {
                                    alert('Error al actualizar solicitud');
                                }
                            });
                        });

                        // Aprobar solicitud
                        $('#solicitudesTable').on('click', '.aprobar', function () {
                            const id = $(this).data('id');
                            const row = $(this).closest('tr');

                            Swal.fire({
                                title: '¿Aprobar solicitud?',
                                text: "¿Estás seguro de aprobar esta solicitud?",
                                icon: 'warning',
                                showCancelButton: true,
                                confirmButtonColor: '#3085d6',
                                cancelButtonColor: '#d33',
                                confirmButtonText: 'Sí, aprobar',
                                cancelButtonText: 'Cancelar'
                            }).then((result) => {
                                if (result.isConfirmed) {
                                    $.ajax({
                                        url: `/admin/requests/${id}/approve`,
                                        method: 'POST',
                                        headers: {
                                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                                        },
                                        success: function (res) {
                                            row.find('td:eq(2)').html('<span class="badge bg-success">Aprobada</span>');
                                            row.find('td:eq(4)').html('<button class="btn btn-sm btn-outline-danger eliminar" data-id="'+id+'">Eliminar</button>');

                                            // Si usas DataTables, actualiza la tabla
                                            if ($.fn.DataTable.isDataTable('#solicitudesTable')) {
                                                $('#solicitudesTable').DataTable().draw(false);
                                            }

                                            Swal.fire('Aprobada!', 'La solicitud ha sido aprobada.', 'success');
                                        },
                                        error: function (xhr) {
                                            let errorMsg = 'Error al aprobar solicitud';
                                            if (xhr.responseJSON && xhr.responseJSON.message) {
                                                errorMsg = xhr.responseJSON.message;
                                                if (xhr.responseJSON.current_status) {
                                                    errorMsg += ` (Estado actual: ${xhr.responseJSON.current_status})`;
                                                }
                                            }
                                            Swal.fire('Error', errorMsg, 'error');
                                        }
                                    });
                                }
                            });
                        });

                        // Rechazar solicitud
                        $('#solicitudesTable').on('click', '.rechazar', function () {
                            const id = $(this).data('id');
                            const row = $(this).closest('tr');

                            Swal.fire({
                                title: '¿Rechazar solicitud?',
                                text: "¿Estás seguro de rechazar esta solicitud?",
                                icon: 'warning',
                                showCancelButton: true,
                                confirmButtonColor: '#3085d6',
                                cancelButtonColor: '#d33',
                                confirmButtonText: 'Sí, rechazar',
                                cancelButtonText: 'Cancelar'
                            }).then((result) => {
                                if (result.isConfirmed) {
                                    $.ajax({
                                        url: `/admin/requests/${id}/reject`,
                                        method: 'POST',
                                        headers: {
                                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                                        },
                                        success: function (res) {
                                            row.find('td:eq(2)').html('<span class="badge bg-danger">Rechazada</span>');
                                            row.find('td:eq(3)').html('');
                                            row.find('td:eq(4)').html('<button class="btn btn-sm btn-outline-danger eliminar" data-id="'+id+'">Eliminar</button>');

                                            // Si usas DataTables, actualiza la tabla
                                            if ($.fn.DataTable.isDataTable('#solicitudesTable')) {
                                                $('#solicitudesTable').DataTable().draw(false);
                                            }

                                            Swal.fire('Rechazada!', 'La solicitud ha sido rechazada.', 'success');
                                        },
                                        error: function (xhr) {
                                            let errorMsg = 'Error al rechazar solicitud';
                                            if (xhr.responseJSON && xhr.responseJSON.message) {
                                                errorMsg = xhr.responseJSON.message;
                                                if (xhr.responseJSON.current_status) {
                                                    errorMsg += ` (Estado actual: ${xhr.responseJSON.current_status})`;
                                                }
                                            }
                                            Swal.fire('Error', errorMsg, 'error');
                                        }
                                    });
                                }
                            });
                        });

                        // Eliminar solicitud
                        $('#solicitudesTable').on('click', '.eliminar', function () {
                            const id = $(this).data('id');
                            const table = $('#solicitudesTable').DataTable();
                            const row = $(this).closest('tr');

                            if(confirm('¿Estás seguro de eliminar esta solicitud?')) {
                                $.ajax({
                                    url: `/admin/requests/${id}`,
                                    method: 'DELETE',
                                    success: function (response) {
                                        if (response.success) {
                                            table.row(row).remove().draw();

                                            Swal.fire({
                                                icon: 'success',
                                                title: 'Solicitud eliminada',
                                                text: 'La solicitud se eliminó correctamente.'
                                            });
                                        } else {
                                            Swal.fire({
                                                icon: 'error',
                                                title: 'Error',
                                                text: response.message || 'Ocurrió un error al eliminar la solicitud.'
                                            });
                                        }
                                    }
                                });
                            }
                        });

                        $('#solicitudesClienteTable').on('click', '.upload-btn', function() {
                            const requestId = $(this).data('request-id');
                            const fileInput = $(this).siblings('.file-input')[0];
                            const file = fileInput.files[0];
                            const row = $(this).closest('tr');

                            if (!file) {
                                Swal.fire('Error', 'Por favor selecciona un archivo', 'error');
                                return;
                            }

                            const formData = new FormData();
                            formData.append('file', file);
                            formData.append('_token', $('meta[name="csrf-token"]').attr('content'));

                            Swal.fire({
                                title: 'Subiendo archivo...',
                                html: 'Por favor espera mientras se sube el archivo',
                                allowOutsideClick: false,
                                didOpen: () => {
                                    Swal.showLoading();
                                }
                            });

                            $.ajax({
                                url: "client/requests/" + requestId + "/upload",
                                type: 'POST',
                                data: formData,
                                processData: false,
                                contentType: false,
                                success: function(response) {
                                    Swal.fire(
                                        'Éxito!',
                                        'El archivo se ha subido correctamente',
                                        'success'
                                    );

                                    // Actualizar la fila
                                    row.find('td:eq(2)').html('<span class="badge bg-info">En revisión</span>');
                                    row.find('.actions-column').html('<em>Sin acciones disponibles</em>');
                                },
                                error: function(xhr) {
                                    let errorMsg = 'Error al subir el archivo';
                                    if (xhr.responseJSON && xhr.responseJSON.message) {
                                        errorMsg = xhr.responseJSON.message;
                                    }
                                    Swal.fire('Error', errorMsg, 'error');
                                }
                            });
                        });

                        // No tengo documento
                        $('#solicitudesClienteTable').on('click', '.no-doc-btn', function() {
                            const requestId = $(this).data('request-id');
                            const row = $(this).closest('tr');

                            Swal.fire({
                                title: '¿No tienes el documento?',
                                text: "¿Estás seguro de que no dispones de este documento?",
                                icon: 'warning',
                                showCancelButton: true,
                                confirmButtonColor: '#3085d6',
                                cancelButtonColor: '#d33',
                                confirmButtonText: 'Sí, no lo tengo',
                                cancelButtonText: 'Cancelar'
                            }).then((result) => {
                                if (result.isConfirmed) {
                                    $.ajax({
                                        url: "client/requests/" + requestId + "/no-doc",
                                        method: 'POST',
                                        headers: {
                                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                                        },
                                        success: function(response) {
                                            Swal.fire(
                                                'Confirmado!',
                                                'Hemos registrado que no dispones del documento.',
                                                'success'
                                            );

                                            // Actualizar la fila
                                            row.find('td:eq(2)').html('<span class="badge bg-warning">Sin documento</span>');
                                            row.find('.actions-column').html('<em>Sin acciones disponibles</em>');
                                        },
                                        error: function(xhr) {
                                            let errorMsg = 'Error al procesar la solicitud';
                                            if (xhr.responseJSON && xhr.responseJSON.message) {
                                                errorMsg = xhr.responseJSON.message;
                                            }
                                            Swal.fire('Error', errorMsg, 'error');
                                        }
                                    });
                                }
                            });
                        });
                    });
                </script>

                <div class="tab-pane fade" id="client-req" role="tabpanel" aria-labelledby="client-req-tab">
                    <table class="table table-sm" id="solicitudesClienteTable">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Tipo</th>
                                <th>Estatus</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($documentRequests as $req)
                            <tr data-id="{{ $req->id }}">
                                <td>{{ $req->document_name }}</td>
                                <td>{{ ucfirst($req->document_type) }}</td>
                                <td><span class="badge bg-secondary">{{ ucfirst(str_replace('_',' ', $req->status)) }}</span></td>
                                <td class="actions-column">
                                    {{-- Subir archivo --}}
                                    @if(in_array($req->status, ['en_espera_cliente', 'rechazada']))
                                    <div class="upload-form d-flex gap-2 mb-2">
                                        <input type="file" class="form-control form-control-sm file-input" data-request-id="{{ $req->id }}">
                                        <button class="btn btn-sm btn-success upload-btn" data-request-id="{{ $req->id }}">Subir</button>
                                    </div>

                                    {{-- No tengo documento --}}
                                    @if(now()->gte($req->no_document_button_at))
                                    <button class="btn btn-sm btn-outline-secondary w-100 no-doc-btn" data-request-id="{{ $req->id }}">No tengo el documento</button>
                                    @endif
                                    @else
                                    <em>Sin acciones disponibles</em>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
@stop

@section('css')
    <link rel="stylesheet" href="{{ asset('css/sefar.css') }}">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.1/css/dataTables.bootstrap5.min.css">
    <style>
    /* Estilos de la tabla y el switch */
    table.dataTable, .dataTables_scrollHeadInner {
        width: 100% !important;
    }
    table.dataTable th, table.dataTable td {
        font-size: 1rem !important;
        padding: 10px 5px;
    }
    .switch {
        position: relative;
        display: inline-block;
        width: 60px;
        height: 34px;
    }
    .switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }
    .slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: #ccc;
        transition: .4s;
    }
    .slider:before {
        position: absolute;
        content: "";
        height: 26px;
        width: 26px;
        left: 4px;
        bottom: 4px;
        background-color: white;
        transition: .4s;
    }
    input:checked + .slider {
        background-color: #093143 !important;
    }
    input:checked + .slider:before {
        transform: translateX(26px);
    }
    .slider.round {
        border-radius: 34px;
    }
    .slider.round:before {
        border-radius: 50%;
    }
    div.dt-row {
        margin:10px 0px;
    }
</style>
@stop

@section('js')
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
<!-- DataTables CSS para Bootstrap 4 -->
<script src="https://code.jquery.com/jquery-3.6.1.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/cleave.js/1.6.0/cleave.min.js"></script>
<script src="//cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<!-- DataTables CSS para Bootstrap 4 -->

<script type="text/javascript" src="https://cdn.datatables.net/1.13.1/js/jquery.dataTables.min.js"></script>
<script type="text/javascript" src="https://cdn.datatables.net/1.13.1/js/dataTables.bootstrap5.min.js"></script>

<script>
    $(document).ready(function () {
        $('#etiquetadosend').on('click', function (e) {
            e.preventDefault(); // Previene el comportamiento predeterminado del botón

            // Obtiene los datos del formulario
            var form = $('#dynamicForm');
            var formData = new FormData(form[0]);

            // Realiza la petición AJAX
            $.ajax({
                url: '{{ route("etiquetasgenealogiamonday") }}', // Ruta al backend
                type: 'POST',
                data: formData,
                processData: false, // Evita que jQuery procese los datos
                contentType: false, // Evita que jQuery establezca el tipo de contenido automáticamente
                headers: {
                    'X-CSRF-TOKEN': $('input[name="_token"]').val() // Incluye el token CSRF
                },
                success: function (response) {
                    // Maneja la respuesta exitosa
                    Swal.fire({
                        icon: 'success',
                        title: 'Guardado',
                        text: 'Los cambios fueron guardados correctamente.'
                    });
                },
                error: function (xhr) {
                    // Maneja errores
                    var errors = xhr.responseJSON?.errors || {};
                    var messages = Object.values(errors).flat().join(' ') || 'Ocurrió un error inesperado.';

                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: messages
                    });
                }
            });
        });

        $('#guardar-datos').on('click', function(e) {
            e.preventDefault(); // Evita el comportamiento predeterminado del botón

            // Serializa los datos del formulario
            let formData = $('#datos-personales-form').serialize();

            // Envía la petición al backend usando AJAX
            $.ajax({
                url: '/guardar-datos-personales', // URL especificada en el formulario
                type: 'POST',
                data: formData,
                success: function(response) {
                    // Maneja la respuesta exitosa del servidor
                    Swal.fire({
                        icon: 'success',
                        title: '¡Éxito!',
                        text: 'Datos guardados exitosamente.',
                        confirmButtonText: 'Aceptar'
                    });
                },
                error: function(xhr) {
                    // Maneja errores
                    let errors = xhr.responseJSON.errors;
                    let errorMessage = xhr.responseJSON.message || 'Hubo un error al guardar los datos.';

                    // Formatea los errores en una lista para mostrarlos en Swal2
                    let formattedErrors = '';
                    if (errors) {
                        Object.keys(errors).forEach(function(key) {
                            formattedErrors += `<p>${errors[key][0]}</p>`;
                        });
                    }

                    // Muestra el mensaje de error usando Swal2
                    Swal.fire({
                        icon: 'error',
                        title: 'Error al guardar',
                        html: formattedErrors || errorMessage,
                        confirmButtonText: 'Aceptar'
                    });
                }
            });
        });

        $('#datos-personales-form').on('submit', function (e) {
            e.preventDefault();
        });

        // Evita el comportamiento predeterminado del formulario
        $('#dynamicForm').on('submit', function (e) {
            e.preventDefault();
        });

        $('#clientSubmitButton').on('click', function (e) {
            e.preventDefault(); // Evita el comportamiento predeterminado

            // Validación básica en el frontend
            var password = $('#password').val();
            var repeatPassword = $('#repeat_password').val();

            if (!password || password.length < 8) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'La contraseña debe tener al menos 8 caracteres.'
                });
                return;
            }

            if (password !== repeatPassword) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Las contraseñas no coinciden.'
                });
                return;
            }

            // Obtén los datos del formulario
            var form = $('#clientChangePasswordForm');
            var formData = new FormData(form[0]);

            // Realiza la petición AJAX
            $.ajax({
                url: '/changepassword', // Ruta al backend
                type: 'POST',
                data: formData,
                processData: false, // Evita que jQuery procese automáticamente los datos
                contentType: false, // Evita que jQuery establezca automáticamente el Content-Type
                headers: {
                    'X-CSRF-TOKEN': $('input[name="_token"]').val() // Incluye el token CSRF
                },
                success: function (response) {
                    // Maneja una respuesta exitosa
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Contraseña actualizada',
                            text: 'La contraseña se cambió correctamente.'
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: response.message || 'Ocurrió un error al actualizar la contraseña.'
                        });
                    }
                },
                error: function (xhr) {
                    // Maneja errores en la petición
                    var errors = xhr.responseJSON?.errors || {};
                    var messages = Object.values(errors).flat().join(' ') || 'Ocurrió un error inesperado.';

                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: messages
                    });
                }
            });
        });

        // Desactiva el comportamiento predeterminado del formulario en caso de envío accidental
        $('#clientChangePasswordForm').on('submit', function (e) {
            e.preventDefault();
        });

        $('#submitButton').on('click', function (e) {
            e.preventDefault(); // Evita el comportamiento predeterminado

            // Obtén los datos del formulario
            var form = $('#adminChangePasswordForm');
            var formData = new FormData(form[0]); // jQuery para acceder al formulario

            // Realiza la petición AJAX
            $.ajax({
                url: '/adminchangepassword', // Ruta al backend
                type: 'POST',
                data: formData,
                processData: false, // Evita que jQuery procese automáticamente los datos
                contentType: false, // Evita que jQuery establezca automáticamente el Content-Type
                headers: {
                    'X-CSRF-TOKEN': $('input[name="_token"]').val() // Incluye el token CSRF
                },
                success: function (response) {
                    // Maneja una respuesta exitosa
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Contraseña actualizada',
                            text: 'La contraseña se cambió correctamente.'
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: response.message || 'Ocurrió un error al actualizar la contraseña.'
                        });
                    }
                },
                error: function (xhr) {
                    // Maneja errores en la petición
                    var errors = xhr.responseJSON?.errors || {};
                    var messages = Object.values(errors).flat().join(' ') || 'Ocurrió un error inesperado.';

                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: messages
                    });
                }
            });
        });

        // Desactiva el comportamiento predeterminado del formulario en caso de envío accidental
        $('#adminChangePasswordForm').on('submit', function (e) {
            e.preventDefault();
        });

        $('#solicitudesClienteTable').DataTable({
            "language": {
                "lengthMenu": "Mostrar _MENU_ resultados por página",
                "zeroRecords": "No hay resultados",
                "info": "Página _PAGE_ de _PAGES_",
                "infoEmpty": "No hay resultados"
            }
        });

        $('#familiarsTable').DataTable({
            "language": {
                "lengthMenu": "Mostrar _MENU_ resultados por página",
                "zeroRecords": "No hay resultados",
                "info": "Página _PAGE_ de _PAGES_",
                "infoEmpty": "No hay resultados"
            }
        });
        $('#paymentsTable').DataTable({
            "language": {
                "lengthMenu": "Mostrar _MENU_ resultados por página",
                "zeroRecords": "No hay resultados",
                "info": "Página _PAGE_ de _PAGES_",
                "infoEmpty": "No hay resultados"
            }
        });
        $('#paymentsPenTable').DataTable({
            "language": {
                "lengthMenu": "Mostrar _MENU_ resultados por página",
                "zeroRecords": "No hay resultados",
                "info": "Página _PAGE_ de _PAGES_",
                "infoEmpty": "No hay resultados"
            }
        });
        $('#documentsTable').DataTable({
            "language": {
                "lengthMenu": "Mostrar _MENU_ resultados por página",
                "zeroRecords": "No hay resultados",
                "info": "Página _PAGE_ de _PAGES_",
                "infoEmpty": "No hay resultados"
            }
        });
        $('#dealsTable').DataTable({
            "language": {
                "lengthMenu": "Mostrar _MENU_ resultados por página",
                "zeroRecords": "No hay resultados",
                "info": "Página _PAGE_ de _PAGES_",
                "infoEmpty": "No hay resultados"
            }
        });
    });
</script>

@stop
