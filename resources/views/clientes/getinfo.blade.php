@extends('adminlte::page')

@section('title', 'Completar información')

@section('content_header')
    <h1>Completar información</h1>
    <script src="//cdn.jsdelivr.net/npm/sweetalert2@11"></script>
@stop

@section('content')

    @csrf

    <script charset="utf-8" type="text/javascript" src="/js/parse-names.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.2/jquery.min.js" integrity="sha512-tWHlutFnuG0C6nQRlpvrEhE4QpkG1nn2MOUMWmUeRePl4e3Aki0VB6W1v3oLjFtd0hVOtRQ9PHpSfN6u6/QXkQ==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script charset="utf-8" type="text/javascript" src="//js.hsforms.net/forms/embed/v2.js"></script>

    @if(session("status")=="exito")
        <script type="text/javascript">
            Swal.fire({
                icon: 'success',
                title: '¡Pago procesado correctamente!',
                showConfirmButton: false,
                timer: 2500
            });
        </script>
    @endif

    <style>
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

        @if(auth()->user()->servicio=="Española LMD")
        
            <script>
                hbspt.forms.create({
                    region: "na1",
                    portalId: "20053496",
                    formId: "ae73e323-14a8-40f4-a20c-4a33a30aabde",
                    onFormReady: function($form){
                        var parsed = NameParse.parse("{{ auth()->user()->name }}");
                        $('input[name="firstname"]').val(parsed["firstName"]).change();
                        $('input[name="lastname"]').val(parsed["lastName"]).change();
                        $('.hs-fieldtype-intl-phone.hs-input .hs-input').val("{{ auth()->user()->phone }}").change();
                        $('input[name="email"]').val("{{ auth()->user()->email }}").change();
                        $('input[name="numero_de_pasaporte"]').val("{{ auth()->user()->passport }}").change();
                    },
                    onFormSubmit: function($form){
                        setTimeout( function() {
                            var formData = $form;

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
                                    Swal.fire({
                                        icon: 'success',
                                        title: '¡Registro completado!',
                                        showDenyButton: false,
                                        confirmButtonText: 'Ir a mi arbol',
                                    }).then((result) => {
                                        window.location.href = "/tree";
                                    });
                                }

                            });
                        }, 250 );
                    }
                });
            </script>

        @else
        
            <script>
                hbspt.forms.create({
                    region: "na1",
                    portalId: "20053496",
                    formId: "ae73e323-14a8-40f4-a20c-4a33a30aabde",
                    onFormReady: function($form){
                        var parsed = NameParse.parse("{{ auth()->user()->name }}");
                        $('input[name="firstname"]').val(parsed["firstName"]).change();
                        $('input[name="lastname"]').val(parsed["lastName"]).change();
                        $('.hs-fieldtype-intl-phone.hs-input .hs-input').val("{{ auth()->user()->phone }}").change();
                        $('input[name="email"]').val("{{ auth()->user()->email }}").change();
                        $('input[name="numero_de_pasaporte"]').val("{{ auth()->user()->passport }}").change();
                    },
                    onFormSubmit: function($form){
                        setTimeout( function() {
                            var formData = $form;

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
                                    Swal.fire({
                                        icon: 'success',
                                        title: '¡Registro completado!',
                                        showDenyButton: false,
                                        confirmButtonText: 'Ir a mi arbol',
                                    }).then((result) => {
                                        window.location.href = "/tree";
                                    });
                                }

                            });
                        }, 250 );
                    }
                });
            </script>

        @endif

    </div>
@stop

@section('css')
    <link rel="stylesheet" href="{{ asset('css/sefar.css') }}">
@stop

@section('js')

@stop
