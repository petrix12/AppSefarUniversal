@extends('adminlte::page')

@section('title', $user->name)

@section('content_header')

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
        <div class="card p-4">
            <ul class="nav nav-tabs" id="formTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="mystatus-tab" data-bs-toggle="tab" data-bs-target="#mystatus" type="button" role="tab" aria-controls="mystatus" aria-selected="true">
                        Mi Estatus
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="personal-data-tab" data-bs-toggle="tab" data-bs-target="#personal_data" type="button" role="tab" aria-controls="personal_data" aria-selected="true">
                        Datos personales
                    </button>
                </li>
                @if(auth()->user()->roles[0]->id == 1)
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="adminchangepassword-tab" data-bs-toggle="tab" data-bs-target="#adminchangepassword" type="button" role="tab" aria-controls="adminchangepassword" aria-selected="true">
                        Contraseña
                    </button>
                </li>
                @else
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="mypassword-tab" data-bs-toggle="tab" data-bs-target="#mypassword" type="button" role="tab" aria-controls="mypassword" aria-selected="true">
                        Cambiar mi Contraseña
                    </button>
                </li>
                @endif
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="familiars-tab" data-bs-toggle="tab" data-bs-target="#familiars" type="button" role="tab" aria-controls="familiars" aria-selected="false">
                        Familiares registrados
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="payments-tab" data-bs-toggle="tab" data-bs-target="#payments" type="button" role="tab" aria-controls="payments" aria-selected="false">
                        Pagos realizados
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="documents-tab" data-bs-toggle="tab" data-bs-target="#documents" type="button" role="tab" aria-controls="documents" aria-selected="false">
                        Archivos Cargados
                    </button>
                </li>
                @if(auth()->user()->roles[0]->id == 1)
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="etiquetado-tab" data-bs-toggle="tab" data-bs-target="#etiquetado" type="button" role="tab" aria-controls="etiquetado" aria-selected="false">
                        Etiquetado
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="negocios-tab" data-bs-toggle="tab" data-bs-target="#negocios" type="button" role="tab" aria-controls="negocios" aria-selected="false">
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
                    height: 200px;
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
                @if (count($cos)>0)
                    @foreach ($cos as $co)
                        @php
                            $steps = [
                                [
                                    'icon' => 'fa-check-circle',
                                    'imgurl' => '/img/IMAGENESCOS/IMAGENES COS 1.jpg',
                                    'status' => 0,
                                    'label' => 'Registro',
                                    'mensaje' =>    "Aún te encuentras en el proceso de registro. Para avanzar y que iniciemos con tu estudio, es necesario que firmes tu contrato, completes tu información y realices el pago de tu análisis jurídico-genealógico.<br>
                                                    Una vez completados estos pasos, nuestro equipo de especialistas en Derecho genealogista, historia y paleografía, comenzará el análisis detallado de tu genealogía."
                                ],
                                [
                                    'icon' => 'fa-check-circle',
                                    'imgurl' => '/img/IMAGENESCOS/IMAGENES COS 2.jpg',
                                    'status' => 1,
                                    'label' => 'Pre análisis',
                                    'mensaje' =>    "Hemos recibido tu información inicial y estamos realizando una evaluación preliminar de tus antecedentes familiares.<br>
                                                    Esta etapa nos permite identificar patrones, apellidos relevantes, y elementos históricos que nos indiquen la viabilidad de un linaje sefardí.<br>
                                                    Este análisis nos ayuda a definir la estrategia investigativa que se utilizará a lo largo del estudio."
                                ],
                                [
                                    'icon' => 'fa-check-circle',
                                    'imgurl' => '/img/IMAGENESCOS/IMAGENES COS 3.jpg',
                                    'status' => 2,
                                    'label' => 'Análisis Genealógico 1',
                                    'mensaje' =>    "Nuestros expertos en Derecho genealogista, historia y paleografía, están llevando a cabo un análisis exhaustivo de tu genealogía.<br>
                                                    Nos encontramos verificando la documentación que nos has suministrado y contrastándola con diferentes bases de datos históricas y genealógicas para identificar conexiones y vínculos, que suelen remontarse incluso entre 15 y 20 generaciones atrás."
                                ],
                                [
                                    'icon' => 'fa-check-circle',
                                    'imgurl' => '/img/IMAGENESCOS/IMAGENES COS 4.jpg',
                                    'status' => 3,
                                    'label' => 'Análisis Genealógico 2',
                                    'mensaje' =>    "Nuestro equipo ha detectado posibles vínculos adicionales y ha activado una nueva línea de investigación para enriquecer tu árbol genealógico.<br>
                                                    Este segundo nivel de análisis nos permite descubrir conexiones familiares indirectas o poco evidentes, que refuerzan o complementan el primer informe y abren nuevas posibilidades."
                                ],
                                [
                                    'icon' => 'fa-check-circle',
                                    'imgurl' => '/img/IMAGENESCOS/IMAGENES COS 5.jpg',
                                    'status' => 4,
                                    'label' => 'Análisis Genealógico 3',
                                    'mensaje' =>    "Hemos entrado en una etapa avanzada del análisis genealógico, donde buscamos información en registros poco convencionales: archivos inquisitoriales, censos coloniales, listas de conversos y bibliografía especializada.<br>
                                                    Activamos alianzas con investigadores locales e internacionales para ampliar tus posibilidades de éxito."
                                ],
                                [
                                    'icon' => 'fa-check-circle',
                                    'imgurl' => '/img/IMAGENESCOS/IMAGENES COS 6.jpg',
                                    'status' => 5,
                                    'label' => 'Investigación más profunda',
                                    'mensaje' =>    "Nuestros especialistas continúan explorando y reconstruyendo líneas genealógicas nuevas y desconocidas, para ampliar tus posibilidades.<br>
                                                    Para ello, necesitamos llevar a cabo una investigación más profunda, utilizando fuentes especializadas e investigadores particulares.<br>
                                                    Te hemos enviado un presupuesto detallado para esta investigación, en la que optimizaremos al máximo la búsqueda de conexiones genealógicas."
                                ],
                                [
                                    'icon' => 'fa-check-circle',
                                    'imgurl' => '/img/IMAGENESCOS/IMAGENES COS 7.jpg',
                                    'status' => 6,
                                    'label' => 'Investigación más profunda',
                                    'mensaje' =>    "¡Excelente noticia, tu genealogía ha sido aprobada!<br>
                                                    Esto quiere decir que eres apto para obtener tu nacionalidad por medio de tu genealogía.<br>
                                                    Te hemos hecho llegar un presupuesto, en el cual detallamos nuestra propuesta profesional para que inicies formalmente tu solicitud de nacionalidad."
                                ],
                                [
                                    'icon' => 'fa-check-circle',
                                    'imgurl' => '/img/IMAGENESCOS/IMAGENES COS 8 modificado.jpg',
                                    'status' => 7,
                                    'label' => 'Apto para Otros Procesos',
                                    'mensaje' =>    "Nuestros especialistas continúan explorando nuevas opciones para ti.<br>
                                                    Aunque aún no se ha confirmado una conexión genealógica, podemos brindarte alternativas como la obtención de residencias o visas para que puedas vivir, trabajar y desarrollarte legalmente en la Unión Europea.<br>
                                                    Adicionalmente, nuestro equipo continúa ampliando y verificando bases de datos genealógicas, lo que podría generar nuevas oportunidades para ti en el futuro."
                                ],
                                [
                                    'icon' => 'fa-check-circle',
                                    'imgurl' => '/img/IMAGENESCOS/IMAGENES COS 9.jpg',
                                    'status' => 8,
                                    'label' => 'Presupuesto y Pago para la Redacción del Informe Genealógico',
                                    'mensaje' =>    "Nos encontramos a la espera de su pago para iniciar su proceso.<br>
                                                    Una vez proceda con su abono, nuestros especialistas se encargarán de elaborar un informe genealógico detallado, sustentando generación por generación con documentos probatorios, que llegan incluso a remontarse a una antigüedad de 7 siglos atrás."
                                ],
                                [
                                    'icon' => 'fa-check-circle',
                                    'imgurl' => '/img/IMAGENESCOS/IMAGENES COS 10.jpg',
                                    'status' => 9,
                                    'label' => 'Redacción del Informe Genealógico',
                                    'mensaje' =>    "Nuestro equipo especializado se encuentra recopilando y organizando toda la documentación filiatoria necesaria para sustentar tu linaje.<br>
                                                    Además, estamos elaborando un informe detallado con referencias históricas y pruebas documentales que refuercen tu conexión con tu antepasado sefardí."
                                ],
                                [
                                    'icon' => 'fa-check-circle',
                                    'imgurl' => '/img/IMAGENESCOS/IMAGENES COS 11.jpg',
                                    'status' => 10,
                                    'label' => 'Investigación y búsqueda de documentos',
                                    'mensaje' =>    "En esta etapa, realizamos un rastreo intensivo de documentos en archivos parroquiales, notariales, civiles e inquisitoriales.<br>
                                                    Nuestro objetivo es recolectar todas las pruebas documentales que sostienen tu árbol genealógico y que pueden ser requeridas por la FCJE o el Ministerio de Justicia."
                                ],
                                [
                                    'icon' => 'fa-check-circle',
                                    'imgurl' => '/img/IMAGENESCOS/IMAGENES COS 12.jpg',
                                    'status' => 11,
                                    'label' => 'Transcripción de documentos',
                                    'mensaje' =>    "Muchos de los documentos antiguos están escritos en castellano arcaico, latín o paleografía difícil de leer. <br>
                                                    Nuestro equipo especializado transcribe y adapta estos textos al formato jurídico y documental necesario para que sean aceptados como prueba válida."
                                ],
                                [
                                    'icon' => 'fa-check-circle',
                                    'imgurl' => '/img/IMAGENESCOS/IMAGENES COS 13.jpg',
                                    'status' => 12,
                                    'label' => 'Anexos al expediente genealógico',
                                    'mensaje' =>    "Estamos organizando todos los documentos recopilados en una estructura formal y detallada.<br>
                                                    Estos anexos acompañarán tu informe genealógico principal y serán presentados ante las autoridades correspondientes como evidencia directa."
                                ],
                                [
                                    'icon' => 'fa-check-circle',
                                    'imgurl' => '/img/IMAGENESCOS/IMAGENES COS 14.jpg',
                                    'status' => 13,
                                    'label' => 'Anexos al expediente genealógico',
                                    'mensaje' =>    "Hemos completado tu informe genealógico con éxito y está listo para su presentación ante la Federación de Comunidades Judías de España.<br>
                                                    Este documento es fundamental para tu expediente de solicitud del certificado de origen sefardí."
                                ],
                                [
                                    'icon' => 'fa-check-circle',
                                    'imgurl' => '/img/IMAGENESCOS/IMAGENES COS 15.jpg',
                                    'status' => 14,
                                    'label' => 'Presupuesto y Pago para la Carga del Informe Genealógico en la Plataforma de la FCJE',
                                    'mensaje' =>    "Actualmente se encuentra pendiente su pago correspondiente a la solicitud de su certificado ante la Federación de Comunidades Judías de España (FCJE).<br>
                                                    Es necesario que proceda con el mismo para evitar retrasos en su proceso y así cargar su informe en la plataforma de la FCJE."
                                ],
                                [
                                    'icon' => 'fa-check-circle',
                                    'imgurl' => '/img/IMAGENESCOS/IMAGENES COS 16.jpg',
                                    'status' => 15,
                                    'label' => 'Expediente Cargado en el FCJE',
                                    'mensaje' =>    "¡Tu expediente ha sido cargado con éxito en la plataforma de la FCJE! <br>
                                                    En esta etapa, la Federación hará una revisión exhaustiva de la documentación que hemos aportado y, una vez validado tu expediente, procederán con la emisión de tu certificado de origen sefardí."
                                ],
                                [
                                    'icon' => 'fa-check-circle',
                                    'imgurl' => '/img/IMAGENESCOS/IMAGENES COS 17.jpg',
                                    'status' => 16,
                                    'label' => 'Defensa del informe',
                                    'mensaje' =>    "Nos encontramos en la fase de evaluación por parte de la FCJE.<br>
                                                    Durante esta etapa, es común que se presenten observaciones o dudas que deben ser respondidas con precisión. <br>
                                                    Nuestro equipo se encarga de defender el informe presentado, aportando información adicional y argumentando cada punto."
                                ],
                                [
                                    'icon' => 'fa-check-circle',
                                    'imgurl' => '/img/IMAGENESCOS/IMAGENES COS 18.jpg',
                                    'status' => 17,
                                    'label' => 'Respuesta de requerimientos',
                                    'mensaje' =>    "Si la Federación solicita más documentos o aclaraciones, nos encargamos de brindar respuestas puntuales y fundamentadas. <br>
                                                    Este seguimiento activo es esencial para que tu proceso avance sin bloqueos ni demoras innecesarias."
                                ],
                                [
                                    'icon' => 'fa-check-circle',
                                    'imgurl' => '/img/IMAGENESCOS/IMAGENES COS 20.jpg',
                                    'status' => 19,
                                    'label' => 'Redacción de nuevo informe con la otra línea',
                                    'mensaje' =>    "Una vez identificada la nueva línea, nuestro equipo redacta un nuevo informe completo y detallado. <br>
                                                    Este segundo informe cumple con los mismos estándares técnicos y probatorios que el primero."
                                ],
                                [
                                    'icon' => 'fa-check-circle',
                                    'imgurl' => '/img/IMAGENESCOS/IMAGENES COS 21.jpg',
                                    'status' => 20,
                                    'label' => 'Defensa del nuevo informe',
                                    'mensaje' =>    "Al igual que con el primero, el nuevo informe será defendido ante la FCJE por nuestro equipo. <br>
                                                    Realizamos presentaciones argumentativas claras y respaldadas por fuentes verificadas."
                                ],
                                [
                                    'icon' => 'fa-check-circle',
                                    'imgurl' => '/img/IMAGENESCOS/IMAGENES COS 22.jpg',
                                    'status' => 21,
                                    'label' => 'Respuesta de requerimientos de la nueva línea',
                                    'mensaje' =>    "En caso de que se presenten observaciones sobre esta nueva línea, también gestionamos sus respuestas. <br>
                                                    Nuestro compromiso es asegurar que cualquier alternativa viable sea validada por completo."
                                ],
                                [
                                    'icon' => 'fa-check-circle',
                                    'imgurl' => '/img/IMAGENESCOS/IMAGENES COS 23.jpg',
                                    'status' => 22,
                                    'label' => 'Certificado descargado',
                                    'mensaje' =>    "¡Tu certificado de origen sefardí ha sido emitido por la FCJE! <br>
                                                    Este documento representa un gran paso en tu proceso y será clave en la formalización de tu expediente."
                                ],
                                [
                                    'icon' => 'fa-check-circle',
                                    'imgurl' => '/img/IMAGENESCOS/IMAGENES COS 24.jpg',
                                    'status' => 23,
                                    'label' => 'Redacción del Informe Legal',
                                    'mensaje' =>    "Nuestro equipo jurídico se encuentra trabajando en la redacción de tu informe legal, asegurando que toda la documentación cumpla con los requisitos exigidos por las autoridades. <br>
                                                    Cada detalle es revisado minuciosamente para maximizar las probabilidades de éxito en tu solicitud."
                                ],
                                [
                                    'icon' => 'fa-check-circle',
                                    'imgurl' => '/img/IMAGENESCOS/IMAGENES COS 25.jpg',
                                    'status' => 24,
                                    'label' => 'Solicitud de la documentación para formalizar',
                                    'mensaje' =>    "Te indicamos exactamente qué documentos debes entregarnos para formalizar tu expediente. <br>
                                                    Este paso es vital para garantizar que tu solicitud sea admitida sin errores."
                                ],
                                [
                                    'icon' => 'fa-check-circle',
                                    'imgurl' => '/img/IMAGENESCOS/IMAGENES COS 26.jpg',
                                    'status' => 25,
                                    'label' => 'Revisión de la documentación jurídico-genealógica',
                                    'mensaje' =>    "Una vez recibida la documentación, nuestro equipo la revisa integralmente. <br>
                                                    Verificamos que cumpla con los estándares del Ministerio de Justicia y que sea coherente con tu genealogía."
                                ],
                                [
                                    'icon' => 'fa-check-circle',
                                    'imgurl' => '/img/IMAGENESCOS/IMAGENES COS 27.jpg',
                                    'status' => 26,
                                    'label' => 'Expediente formalizado',
                                    'mensaje' =>    "¡Su expediente ha sido formalizado con éxito! <br>
                                                    Ahora, tu proceso de nacionalidad se encuentra en manos de las autoridades españolas, contando además con un seguimiento continuo por parte de nuestro equipo jurídico."
                                ],
                                [
                                    'icon' => 'fa-check-circle',
                                    'imgurl' => '/img/IMAGENESCOS/IMAGENES COS 28.jpg',
                                    'status' => 27,
                                    'label' => 'Seguimiento del expediente en el Ministerio de Justicia',
                                    'mensaje' =>    "Realizamos un seguimiento estratégico del avance de tu expediente. <br>
                                                    Nos mantenemos atentos a cualquier notificación, requerimiento o actualización del Ministerio."
                                ],
                            ];

                            $message = $co["message"];
                            $color = $co["color"];

                            $currentStep = $co["currentStep"];
                        @endphp

                        <div class="card">
                            <div class="card-header" style="text-align: center;">
                                <h2 class="card-title mb-4">Estatus de mi proceso: {!! $co["servicename"] !!}</h2>
                                <p class="mt-2" style="    padding-top: 22px;">
                                    @if ($currentStep >=0 && $currentStep <1)
                                        {!!$steps[0]["mensaje"]!!}
                                        <img class="w-100 mt-4" src='{{$steps[0]["imgurl"]}}'>
                                    @elseif ($currentStep >=1 && $currentStep <2)
                                        {!!$steps[1]["mensaje"]!!}
                                        <img class="w-100 mt-4" src='{{$steps[1]["imgurl"]}}'>
                                    @elseif ($currentStep >=2 && $currentStep <3)
                                        {!!$steps[2]["mensaje"]!!}
                                        <img class="w-100 mt-4" src='{{$steps[2]["imgurl"]}}'>
                                    @elseif ($currentStep >=3 && $currentStep <4)
                                        {!!$steps[3]["mensaje"]!!}
                                        <img class="w-100 mt-4" src='{{$steps[3]["imgurl"]}}'>
                                    @elseif ($currentStep >=4 && $currentStep <5)
                                        {!!$steps[4]["mensaje"]!!}
                                        <img class="w-100 mt-4" src='{{$steps[4]["imgurl"]}}'>
                                    @elseif ($currentStep >=5 && $currentStep <6)
                                        {!!$steps[5]["mensaje"]!!}
                                        <img class="w-100 mt-4" src='{{$steps[5]["imgurl"]}}'>
                                    @elseif ($currentStep >=6 && $currentStep <7)
                                        {!!$steps[6]["mensaje"]!!}
                                        <img class="w-100 mt-4" src='{{$steps[6]["imgurl"]}}'>
                                    @elseif ($currentStep >=7 && $currentStep <8)
                                        {!!$steps[7]["mensaje"]!!}
                                        <img class="w-100 mt-4" src='{{$steps[7]["imgurl"]}}'>
                                    @elseif ($currentStep >=8 && $currentStep <9)
                                        {!!$steps[8]["mensaje"]!!}
                                        <img class="w-100 mt-4" src='{{$steps[8]["imgurl"]}}'>
                                    @elseif ($currentStep >=9 && $currentStep <10)
                                        {!!$steps[9]["mensaje"]!!}
                                        <img class="w-100 mt-4" src='{{$steps[9]["imgurl"]}}'>
                                    @elseif ($currentStep >=10 && $currentStep <11)
                                        {!!$steps[10]["mensaje"]!!}
                                        <img class="w-100 mt-4" src='{{$steps[10]["imgurl"]}}'>
                                    @elseif ($currentStep >=11 && $currentStep <12)
                                        {!!$steps[11]["mensaje"]!!}
                                        <img class="w-100 mt-4" src='{{$steps[11]["imgurl"]}}'>
                                    @elseif ($currentStep >=12 && $currentStep <13)
                                        {!!$steps[12]["mensaje"]!!}
                                        <img class="w-100 mt-4" src='{{$steps[12]["imgurl"]}}'>
                                    @elseif ($currentStep >=13 && $currentStep <14)
                                        {!!$steps[13]["mensaje"]!!}
                                        <img class="w-100 mt-4" src='{{$steps[13]["imgurl"]}}'>
                                    @elseif ($currentStep >=14 && $currentStep <15)
                                        {!!$steps[14]["mensaje"]!!}
                                        <img class="w-100 mt-4" src='{{$steps[14]["imgurl"]}}'>
                                    @elseif ($currentStep >=15 && $currentStep <16)
                                        {!!$steps[15]["mensaje"]!!}
                                        <img class="w-100 mt-4" src='{{$steps[15]["imgurl"]}}'>
                                    @elseif ($currentStep >=16 && $currentStep <17)
                                        {!!$steps[16]["mensaje"]!!}
                                        <img class="w-100 mt-4" src='{{$steps[16]["imgurl"]}}'>
                                    @elseif ($currentStep >=17 && $currentStep <18)
                                        {!!$steps[17]["mensaje"]!!}
                                        <img class="w-100 mt-4" src='{{$steps[17]["imgurl"]}}'>
                                    @elseif ($currentStep >=18 && $currentStep <19)
                                        {!!$steps[18]["mensaje"]!!}
                                        <img class="w-100 mt-4" src='{{$steps[18]["imgurl"]}}'>
                                    @elseif ($currentStep >=19 && $currentStep <20)
                                        {!!$steps[19]["mensaje"]!!}
                                        <img class="w-100 mt-4" src='{{$steps[19]["imgurl"]}}'>
                                    @elseif ($currentStep >=20 && $currentStep <21)
                                        {!!$steps[20]["mensaje"]!!}
                                        <img class="w-100 mt-4" src='{{$steps[20]["imgurl"]}}'>
                                    @elseif ($currentStep >=21 && $currentStep <22)
                                        {!!$steps[21]["mensaje"]!!}
                                        <img class="w-100 mt-4" src='{{$steps[21]["imgurl"]}}'>
                                    @elseif ($currentStep >=22 && $currentStep <23)
                                        {!!$steps[22]["mensaje"]!!}
                                        <img class="w-100 mt-4" src='{{$steps[22]["imgurl"]}}'>
                                    @elseif ($currentStep >=23 && $currentStep <24)
                                        {!!$steps[23]["mensaje"]!!}
                                        <img class="w-100 mt-4" src='{{$steps[23]["imgurl"]}}'>
                                    @elseif ($currentStep >=24 && $currentStep <25)
                                        {!!$steps[24]["mensaje"]!!}
                                        <img class="w-100 mt-4" src='{{$steps[24]["imgurl"]}}'>
                                    @elseif ($currentStep >=25 && $currentStep <26)
                                        {!!$steps[25]["mensaje"]!!}
                                        <img class="w-100 mt-4" src='{{$steps[25]["imgurl"]}}'>
                                    @elseif ($currentStep >=26 && $currentStep <27)
                                        {!!$steps[26]["mensaje"]!!}
                                        <img class="w-100 mt-4" src='{{$steps[26]["imgurl"]}}'>
                                    @elseif ($currentStep >=27 && $currentStep <28)
                                        {!!$steps[27]["mensaje"]!!}
                                        <img class="w-100 mt-4" src='{{$steps[27]["imgurl"]}}'>
                                    @elseif ($currentStep >=28 && $currentStep <29)
                                        {!!$steps[28]["mensaje"]!!}
                                        <img class="w-100 mt-4" src='{{$steps[28]["imgurl"]}}'>
                                    @endif
                                </p>
                            </div>
                            <div class="card-body" style="text-align: center;">
                                <!-- Contenedor para el scroll horizontal -->
                                <div class="progress-scroll-container">
                                    <div class="progress-container" id="progressContainer">
                                        <!-- Línea de progreso completa (fondo) -->
                                        <div class="progress-line-full"></div>

                                        <!-- Línea de progreso activa -->
                                        <div class="progress-line" style="width: {{ $co['progressPercentageGen'] }}%;"></div>

                                        <!-- Círculos de las etapas -->
                                        @foreach ($steps as $step)
                                            <div class="progress-step {{ $currentStep >= $step['status'] ? 'active' : '' }}" data-step="{{ $step['status'] }}">
                                                <i class="fas {{ $step['icon'] }}"></i>
                                                <span class="step-label">{{ $step['label'] }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                            <script>
                                document.addEventListener('DOMContentLoaded', function() {
                                    const container = document.querySelector('.progress-scroll-container');
                                    const progressContainer = document.getElementById('progressContainer');
                                    const activeSteps = document.querySelectorAll('.progress-step.active');

                                    if (activeSteps.length > 0) {
                                        // Seleccionar el último paso activo
                                        const lastActiveStep = activeSteps[activeSteps.length - 1];

                                        // Calcular la posición para centrarlo
                                        const containerWidth = container.clientWidth;
                                        const stepRect = lastActiveStep.getBoundingClientRect();
                                        const containerRect = container.getBoundingClientRect();
                                        const stepCenter = stepRect.left - containerRect.left + stepRect.width/2;
                                        const scrollTo = stepCenter - containerWidth/2 + container.scrollLeft;

                                        // Aplicar el scroll
                                        container.scrollTo({
                                            left: scrollTo,
                                            behavior: 'smooth'
                                        });
                                    }
                                });
                            </script>
                            @if ($color == "warning")
                            <div class="alert my-4" style="border-radius:15px; margin: auto; position:relative; width: 500px; background-color: #eeeeee; border: 1px solid #cccccc">
                                <div style="position: absolute; top: -10px; right: -10px; width: 30px; height: 30px; background-color: #fa1d33; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-exclamation" style="color: white;"></i>
                                </div>

                                <p class="mb-0">
                                    <strong>Estado actual:</strong> {!! $message !!}
                                </p>
                            </div>
                            @elseif ($color == "danger")
                            <div class="alert my-4" style="border-radius:15px; margin: auto; position:relative; width: 500px; background-color: #eeeeee; border: 1px solid #cccccc">
                                <div style="position: absolute; top: -10px; right: -10px; width: 30px; height: 30px; background-color: #ffcc00; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-exclamation" style="color: white;"></i>
                                </div>
                                <p class="mb-0">
                                    <strong>Estado actual:</strong> {!! $message !!}
                                </p>
                            </div>
                            @endif
                        </div>
                    @endforeach
                @endif
                </div>

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
                        @if(auth()->user()->roles[0]->id == 1)
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

                        @if(auth()->user()->roles[0]->id == 1)

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
                        @endif
                        @if(auth()->user()->roles[0]->id == 1)

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

                        <button type="button" id="guardar-datos" class="cfrSefar btn btn-primary mt-3">Guardar</button>
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
                        <a href="/tree/{{$user->passport}}" class="cfrSefar mb-3 inline-flex items-center justify-center px-3 py-1 border border-transparent text-base font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                            Ir al Arbol
                        </a>
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
                                    <td>{{$negocio["dealname"]}}<br>{!!$negocio["hubspot_id"] ? "<small>Se encuentra en <b><a href='https://app.hubspot.com/contacts/20053496/record/0-3/".$negocio['hubspot_id']."'>Hubspot</a></b></small>" : ''!!}{!! $negocio["teamleader_id"] ? "<small> y en <b><a href='https://focus.teamleader.eu/web/projects/".$negocio['teamleader_id']."'>Teamleader</a></b></small>" : '' !!}</td>
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
