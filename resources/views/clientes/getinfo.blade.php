@extends('adminlte::page')

@section('title', 'Completar información')

@section('content_header')
    <h1>Completar información</h1>
    <script src="//cdn.jsdelivr.net/npm/sweetalert2@11"></script>
@stop

@section('content')

    <div style="position: fixed; top: 0; left: 0; background-color:rgba(0, 0, 0, 0.5); z-index: 6000;" id="ajaxload"></div>

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
            hbspt.forms.create({
                region: "na1",
                portalId: "20053496",
                formId: "ae73e323-14a8-40f4-a20c-4a33a30aabde",
                onFormReady: function($form){
                    setTimeout( function() {
                        $("#ajaxload").hide();
                        var antepasados = <?php echo (auth()->user()->antepasados); ?>;
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
                    setTimeout( function() {
                        $("#ajaxload").show();
                        var formData = $form;

                        if($('input[name="firstname"]').val() == "" || $('input[name="lastname"]').val() == "" || $('.hs-fieldtype-intl-phone.hs-input .hs-input').val() == "" || $('input[name="email"]').val() == "" || $('input[name="numero_de_pasaporte"]').val() == "" || $('input[name="pais_de_nacimiento"]').val() == "" || $('input[name="nacionalidad_solicitada"]').val() == ""){
                            return false;
                        }

                        var data = formData.serializeArray();

                        $.ajaxSetup({
                            headers: {
                                'X-CSRF-TOKEN': $("input[name='_token']").val()
                            }
                        });

                        $.ajax({
                            url: '{{ route("procesargetinfo") }}',
                            method: 'POST',
                            data: {
                                data
                            },
                            success: function(response){
                                window.location.href = "/tree";
                            }
                        });
                    }, 4500 );
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
