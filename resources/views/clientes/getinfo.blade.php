@extends('adminlte::page')

@section('title', 'Completar información')

@section('content_header')
    <h1>Completar información</h1>
    <script src="//cdn.jsdelivr.net/npm/sweetalert2@11"></script>
@stop

@section('content')

    @if(session('info'))
        <div class="alert alert-info" role="alert">
            {{ session('info') }}
        </div>
    @endif

    <div style="position: fixed; top: 0; left: 0; background-color:rgba(0, 0, 0, 0.5); z-index: 6000; width: 100%; height: 100%;" id="ajaxload"></div>

    <div style="position: fixed;top: 0;left: 0;background-color:rgba(0, 0, 0, 0.5);z-index: 6000;width: 100%;height: 100%; display: none;" id="ajaxload2">

        <div class="card" style="
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 600px;
            height: 420px;
            background-color: #EFEFEFEF;
            border-radius: 20px;
            display: table;
            text-align: center;
            vertical-align: middle;
        ">
            <div style="display:table-cell;vertical-align: middle;">
                <h1 style="
                    color: black;
                    vertical-align: middle;
                ">¡Gracias!</h1>
                <p style="
                    color: black;
                ">Estamos guardando tu información. En breve serás redirigido.</p>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.2/jquery.min.js" integrity="sha512-tWHlutFnuG0C6nQRlpvrEhE4QpkG1nn2MOUMWmUeRePl4e3Aki0VB6W1v3oLjFtd0hVOtRQ9PHpSfN6u6/QXkQ==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script charset="utf-8" type="text/javascript" src="//js.hsforms.net/forms/embed/v2.js"></script>

    <style>
        .hs_nacionalidad_solicitada {
            display: none;
        }
        .hs-input{
            display: block;
            width: 100%;
            height: calc(2.25rem + 2px);
            padding: 0.375rem 0.75rem;
            font-size: 1rem;
            font-weight: 400;
            line-height: 1.5;
            color: #495057;
            background-color: #fff;
            background-clip: padding-box;
            border: 1px solid #ced4da;
            border-radius: 0.25rem;
            box-shadow: inset 0 0 0 transparent;
            transition: border-color .15s ease-in-out,box-shadow .15s ease-in-out;
        }
        .form-columns-3{
            width: 92%;
            max-width: 100%!important;
        }
        .form-columns-2{
            width: 90%;
            max-width: 100%!important;
        }
        .form-columns-1{
            width: 94.9%;
            max-width: 100%!important;
        }
        .hs-form-field{
            margin: 15px 0 0 0;
        }
        .hs-form-booleancheckbox-display{
            display: flex;
        }
        .hs-fieldtype-intl-phone.hs-input select {
            display: none;
        }
        .hs-fieldtype-intl-phone.hs-input input {
            width: 100%!important;
            float: left;
        }
        .inputs-list {
            list-style-type: none;
            padding-inline-start: 0px;
        }
        .inputs-list li label input {
            width: 35px;
            min-width: 35px;
            margin-right: 6px;
        }
        .inputs-list li label span, .legal-consent-container {
            margin: auto 0px !important;
        }
        input[type="file"]::-webkit-file-upload-button {
            margin: -3px 3px 0 -3px;
        }
    </style>

    <div class="container m-3">

        <script>
            function showGetInfoProgress(message) {
                if (!window.Swal) return;

                Swal.fire({
                    title: 'Estamos procesando tu información',
                    html: message || 'Guardando tus datos y verificando el formulario...',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    showConfirmButton: false,
                    didOpen: function () {
                        Swal.showLoading();
                    }
                });
            }

            function updateGetInfoProgress(message) {
                if (!window.Swal || !Swal.isVisible()) return;

                Swal.update({
                    title: 'Estamos procesando tu información',
                    html: message
                });
                Swal.showLoading();
            }

            function redirectAfterGetInfo(response) {
                var redirectUrl = (response && response.redirect_url) ? response.redirect_url : "{{ route('cliente.contrato') }}";

                if (window.Swal && Swal.isVisible()) {
                    Swal.update({
                        icon: 'success',
                        title: 'Información recibida',
                        html: 'Todo listo. Ahora continuaremos con la firma del contrato.',
                        showConfirmButton: false
                    });
                }

                setTimeout(function () {
                    window.location.href = redirectUrl;
                }, 700);
            }

            hbspt.forms.create({
                region: "na1",
                portalId: "20053496",
                formId: "ae73e323-14a8-40f4-a20c-4a33a30aabde",
                onFormReady: function($form){
                    setTimeout( function() {
                        $("#ajaxload").hide();
                        var antepasados = <?php echo (auth()->user()->antepasados ? auth()->user()->antepasados : 0); ?>;
                        var servicio = "<?php echo (auth()->user()->servicio); ?>";
                        $('#hs-form-iframe-0').contents().find('input[name="firstname"]').val("{{ auth()->user()->nombres }}").change();
                        $('#hs-form-iframe-0').contents().find('input[name="lastname"]').val("{{ auth()->user()->apellidos }}").change();
                        $('#hs-form-iframe-0').contents().find('input[name="phone"]').val("{{ auth()->user()->phone }}").change();
                        $('#hs-form-iframe-0').contents().find('input[name="email"]').val("{{ auth()->user()->email }}").change();
                        $('#hs-form-iframe-0').contents().find('input[name="numero_de_pasaporte"]').val("{{ auth()->user()->passport }}").change();
                        $('#hs-form-iframe-0').contents().find('input[name="pais_de_nacimiento"]').val("{{ auth()->user()->pais_de_nacimiento }}").change();
                        $('#hs-form-iframe-0').contents().find('select[name="nacionalidad_solicitada"]').val("{{ auth()->user()->servicio }}").change();
                        if (servicio == 'Italiana'){
                            if (antepasados == 2){
                                $('#hs-form-iframe-0').contents().find('select[name="tiene_antepasados_italianos"]').val("Si").change();
                                $('#hs-form-iframe-0').contents().find('input[name="tiene_antepasados_italianos"]').val("Si").change();

                                var checkbox = $('#hs-form-iframe-0').contents().find('input[value="<?php echo(auth()->user()->vinculo_antepasados); ?>"]');
                                checkbox.prop('checked', true);

                                $('#hs-form-iframe-0').contents().find('select[name="estado_de_datos_y_documentos_de_los_antepasados"]').val("<?php echo(auth()->user()->estado_de_datos_y_documentos_de_los_antepasados); ?>").change();
                            } else {
                                $('#hs-form-iframe-0').contents().find('select[name="tiene_antepasados_italianos"]').val("No").change();
                                $('#hs-form-iframe-0').contents().find('input[name="tiene_antepasados_italianos"]').val("No").change();
                            }
                        }
                        if (servicio == 'Española LMD'){
                            if (antepasados == 1){
                                $('#hs-form-iframe-0').contents().find('select[name="tiene_antepasados_espanoles"]').val("Si").change();
                                $('#hs-form-iframe-0').contents().find('input[name="tiene_antepasados_espanoles"]').val("Si").change();

                                var checkbox = $('#hs-form-iframe-0').contents().find('input[value="<?php echo(auth()->user()->vinculo_antepasados); ?>"]');
                                checkbox.prop('checked', true);

                                $('#hs-form-iframe-0').contents().find('select[name="estado_de_datos_y_documentos_de_los_antepasados"]').val("<?php echo(auth()->user()->estado_de_datos_y_documentos_de_los_antepasados); ?>").change();
                            } else {
                                $('#hs-form-iframe-0').contents().find('select[name="tiene_antepasados_espanoles"]').val("No").change();
                                $('#hs-form-iframe-0').contents().find('input[name="tiene_antepasados_espanoles"]').val("No").change();
                            }
                        }
                    }, 1000 );
                },
                onFormSubmit: function($form){
                    $("#ajaxload2").show();
                    showGetInfoProgress('Recibimos el formulario. Estamos guardando tu información para continuar con el contrato.');

                    setTimeout(function() {
                        var data = [];

                        if ($form && typeof $form.serializeArray === 'function') {
                            data = $form.serializeArray();
                        }

                        if (!data.length) {
                            try {
                                data = $('#hs-form-iframe-0').contents().find('form').serializeArray();
                            } catch (error) {
                                data = [];
                            }
                        }

                        if (!data.length) {
                            $("#ajaxload2").hide();
                            Swal.fire({
                                icon: 'error',
                                title: 'No se pudo leer el formulario',
                                text: 'Por favor, recarga la página e intenta nuevamente.'
                            });
                            return;
                        }

                        submitGetInfo(data, 1);
                    }, 1500);

                    function submitGetInfo(data, attempt) {
                        $.ajax({
                            url: '{{ route("procesargetinfo") }}',
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') || $("input[name='_token']").val()
                            },
                            data: {
                                data: data
                            },
                            success: function(response){
                                redirectAfterGetInfo(response);
                            },
                            error: function(xhr){
                                var response = xhr.responseJSON || {};
                                var shouldRetry = response.retry || xhr.status === 409 || xhr.status === 504;

                                if (shouldRetry && attempt < 40) {
                                    updateGetInfoProgress('HubSpot todavía está terminando de registrar el formulario. Seguimos verificando automáticamente...');
                                    setTimeout(function() {
                                        submitGetInfo(data, attempt + 1);
                                    }, 3000);
                                    return;
                                }

                                $("#ajaxload2").hide();
                                Swal.fire({
                                    icon: 'error',
                                    title: 'No se pudo completar la información',
                                    text: response.message || response.error || 'Hubo un problema al verificar el formulario. Por favor, intenta nuevamente.'
                                });
                            }
                        });
                    }
                }
            });
        </script>

        @csrf

    </div>
@stop

@section('css')
    <link rel="stylesheet" href="{{ asset('css/sefar.css') }}">
@stop

@section('js')

@stop
