@extends('adminlte::page')

@section('title', 'Pago')

@section('content_header')
    <h1>Realizar pago</h1>
@stop

<script src="//cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div id="ajaxload" style="background-color: rgba(0, 0, 0, 0.4); position: fixed; z-index: 1000; display: none; width: 100%; height: 100%;"></div>

@section('content')

    @if(session("status")=="errorx")
        <script type="text/javascript">
            Swal.fire({
                icon: 'error',
                title: 'Pago rechazado: {{ session("code") }}',
                showConfirmButton: false,
                timer: 5000
            });
        </script>
    @endif

    @if(session("status")=="error1")
        <script type="text/javascript">
            Swal.fire({
                icon: 'error',
                title: 'Se realizaron varios intentos sin éxito. Por favor, comunicarse con el emisor de su tarjeta.',
                showConfirmButton: false,
                timer: 5000
            });
        </script>
    @endif

    @if(session("status")=="error2")
        <script type="text/javascript">
            Swal.fire({
                icon: 'error',
                title: 'Se ha realizado una petición inválida. Por favor, comunicar este error a Sistemas.',
                showConfirmButton: false,
                timer: 5000
            });
        </script>
    @endif

    @if(session("status")=="error3")
        <script type="text/javascript">
            Swal.fire({
                icon: 'error',
                title: 'Se realizaron varios intentos sin éxito. Por favor, comunicarse con el emisor de su tarjeta',
                showConfirmButton: false,
                timer: 5000
            });
        </script>
    @endif

    @if(session("status")=="error4")
        <script type="text/javascript">
            Swal.fire({
                icon: 'error',
                title: 'Error conectándose a la pasarela de pago. Por favor, comunicar este error a Sistemas.',
                showConfirmButton: false,
                timer: 5000
            });
        </script>
    @endif

    @if(session("status")=="error5")
        <script type="text/javascript">
            Swal.fire({
                icon: 'error',
                title: 'En este momento, la pasarela de pago está en mantenimiento. Por favor, intente pagar mas tarde.',
                showConfirmButton: false,
                timer: 5000
            });
        </script>
    @endif

    @if(session("status")=="error6")
        <script type="text/javascript">
            Swal.fire({
                icon: 'error',
                title: 'Ha ocurrido un error desconocido al realizar su pago.',
                showConfirmButton: false,
                timer: 5000
            });
        </script>
    @endif

    @if (auth()->user()->servicio == 'Constitución de Empresa' || auth()->user()->servicio == 'Representante Fiscal' || auth()->user()->servicio == 'Codigo  Fiscal' || auth()->user()->servicio == 'Apertura de cuenta' || auth()->user()->servicio == 'Trimestre contable' || auth()->user()->servicio == 'Cooperativa 10 años' || auth()->user()->servicio == 'Cooperativa 5 años' || auth()->user()->servicio == 'Portuguesa Sefardi' || auth()->user()->servicio == 'Portuguesa Sefardi - Subsanación' || auth()->user()->servicio == 'Certificación de Documentos - Portugal')
    <form action="" method="POST" class="require-validation" data-cc-on-file="false" data-stripe-publishable-key="{{ env('STRIPE_KEY_PORT') }}" id="payment-form">
    @else
    <form action="" method="POST" class="require-validation" data-cc-on-file="false" data-stripe-publishable-key="{{ env('STRIPE_KEY') }}" id="payment-form">
    @endif 
        <div class="container p-8 row" style="display:flex;">
            <script src="https://code.jquery.com/jquery-3.6.1.min.js"></script>

            <script src="https://cdnjs.cloudflare.com/ajax/libs/cleave.js/1.6.0/cleave.min.js"></script>

            <script>

                document.addEventListener('DOMContentLoaded', () => {

                    $('#nameoncard').keyup(function(){
                        this.value = this.value.toUpperCase();
                    });
                    $('#coupon').keyup(function(){
                        this.value = this.value.toUpperCase();
                    });

                    new Cleave('#ccn', {
                        creditCard: true
                    });

                    new Cleave('.card-expiry-year', {
                        date: true,
                        datePattern: ['y']
                    });

                    new Cleave('.card-expiry-month', {
                        date: true,
                        datePattern: ['m']
                    });

                    $(document).on("click", "#valcoupon", function(e){
                        if ($("#coupon").val() == "" || !$("#coupon").val()){
                            Swal.fire({
                                icon: 'error',
                                title: 'No ha ingresado ningún cupón',
                                showConfirmButton: false,
                                timer: 2500
                            });
                            return false;
                        }
                        $("#ajaxload").show();
                        $.ajax({
                            url: '{{ route("revisarcupon") }}',
                            data: {
                                cpn: $("#coupon").val()
                            },
                            success: function(response){
                                if(response["status"]=="true"){
                                    $("#coupon").attr('readonly', true);
                                    $("#valcoupon").attr('disabled', true);
                                    $("#priced").html("0€");
                                    window.location.href = "{{ route('gracias') }}";
                                } else if (response["status"]=="halftrue") {
                                    $("#ajaxload").hide();
                                    Swal.fire({
                                        icon: 'success',
                                        title: 'Se ha aplicado un descuento de un ' + response["percentage"] + '%. En un momento recargaré la página.',
                                        showConfirmButton: false,
                                        timer: 5000
                                    }).then(function() {
                                        window.location.reload();
                                    });
                                } else {
                                    $("#ajaxload").hide();
                                    Swal.fire({
                                        icon: 'error',
                                        title: 'Cupón inválido',
                                        showConfirmButton: false,
                                        timer: 2500
                                    });
                                }

                            }

                        });
                    });

                    $(document).on("click", '.deletedesc', function(){
                        $.ajaxSetup({
                            headers: {
                                'X-CSRF-TOKEN': $("input[name='_token']").val()
                            }
                        });
                        if (confirm("¿Está seguro que desea eliminar este pago?") == true) {
                            $("#ajaxload").show();
                            $.ajax({
                                type: "POST",
                                url: "{{ route('destroypayelement') }}",
                                data: {id: this.id},
                                success: function(info){
                                    if (info == 0 || info == '0'){
                                        window.location.replace("/noservices");
                                    } else {
                                        window.location.replace("/pay");
                                    }
                                }
                            });
                        }
                    })
                });




            </script>



            @csrf
            <div class="col-sm-12 col-md-5 mb-0">
                <center>
                    <h2 style="padding:10px 0px; color:#12313a"><i class="mt-4 fas fa-credit-card"></i> Datos de Pago <i class="fas fa-credit-card"></i></h2>
                </center>
                <div class='row' style="margin: 0;">
                    <div class='col-xs-12 form-group required' style="width: 100%;">
                        <label class='control-label'>Nombre en la Tarjeta</label>
                        <input
                            autocomplete='off' id="nameoncard" name="nameoncard" class='form-control'
                            type='text' style="width: 100%;">
                    </div>
                </div>

                <div class='mt-2 row' style="margin: 0;">
                    <div class='col-xs-12 form-group required' style="width: 100%;">
                        <label class='control-label type'>Número de Tarjeta de Débito/Crédito</label>
                        <input
                            autocomplete='off' id="ccn" class='form-control card-number'
                            type='tel' style="width: 100%;">
                    </div>
                </div>

                <div class='row' style="margin: 0 0 1rem 0;">
                    <div class='mt-2' style="width: calc(100%/3); padding-right: 3px;">
                        <label class='control-label'>CVC</label> <input autocomplete='off'
                            class='form-control card-cvc' placeholder='***' maxlength="4"
                            type='password'>
                    </div>
                    <div class='mt-2' style="width: calc(100%/3); padding-left: 3px; padding-right: 3px;">
                        <label class='control-label'>Mes de Expiración</label> <input
                            class='form-control card-expiry-month' placeholder='MM' size='2'
                            type='text'>
                    </div>
                    <div class='mt-2' style="width: calc(100%/3); padding-left: 3px;">
                        <label class='control-label'>Año de Expiración</label> <input
                            class='form-control card-expiry-year' placeholder='YY' size='2'
                            type='text'>
                    </div>
                </div>

                <div class='mt-2 row' style="margin: 0;">
                    <div class='col-xs-12 form-group required' style="width: 100%;">
                        <label class='control-label type'>Ingresar Cupón</label>
                        <input
                            autocomplete='off' name="coupon" id="coupon" class='form-control coupon'
                            type='text' style="width: 100%;">
                    </div>
                </div>

                <div class='row' style="justify-content: center; display: flex; margin-bottom:2rem;">
                    <input type="button" id="valcoupon" value="Validar cupón" class="btn csrSefar" style="margin-right: 1rem;"><button class="btn btn-primary cfrSefar" type="submit">Realizar pago</button>
                </div>
            </div>
            <div class="col-sm-12 col-md-7 mb-0">
                <div class="card" style="margin:0 30px; padding: 30px;">
                    <center>
                        <img style="width:100px;" src="/vendor/adminlte/dist/img/LogoSefar.png">
                        <h4 style="font-weight: bold;">Información de Pago</h4>
                    </center>

                    <style type="text/css">
                        .styled-table {
                            border-collapse: collapse;
                            margin: 15px 0 25px 0;
                            font-size: 0.9em;
                            min-width: 400px;
                            box-shadow: 0 0 20px rgba(0, 0, 0, 0.15);
                        }
                        .styled-table thead tr {
                            background-color: var(--main-bg-color) !important;
                            color: #ffffff;
                            text-align: left;
                        }
                        .styled-table th,
                        .styled-table td {
                            padding: 12px 15px;
                        }
                        .styled-table tbody tr {
                            border-bottom: 1px solid #dddddd;
                        }

                        .styled-table tbody tr:nth-of-type(even) {
                            background-color: #f3f3f3;
                        }

                        .styled-table tbody tr:last-of-type {
                            border-bottom: 2px solid var(--main-bg-color);
                        }
                    </style>

                    <table class="styled-table">
                        <thead>
                            <tr>
                                <th>Descripción</th>
                                <th>Costo(€)</th>
                                @if (count($compras)>1)
                                <th></th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            <?php

                            $total = 0;

                            $vinc = 0;

                            foreach ($compras as $key => $compra) {
                                if (auth()->user()->servicio == 'Constitución de Empresa' || auth()->user()->servicio == 'Representante Fiscal' || auth()->user()->servicio == 'Codigo  Fiscal' || auth()->user()->servicio == 'Apertura de cuenta' || auth()->user()->servicio == 'Trimestre contable' || auth()->user()->servicio == 'Cooperativa 10 años' || auth()->user()->servicio == 'Cooperativa 5 años') {
                                    $vinc = 1;
                                }
                            ?>

                                <tr>
                                    <td style="">{{$compra["descripcion"]}}</td>
                                    <td style="">{{$compra["monto"]}}€</td>
                                    @if (count($compras)>1)
                                    <td style="">
                                        <a style="color: white;" 
                                            class="deletedesc btn btn-danger"
                                            id="{{$compra['id']}}"
                                            ><i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                    @endif
                                </tr>

                            <?php
                                $total = $total + $compra["monto"];
                            }

                            ?>
                            <tr>
                                <td style="font-weight: bold; text-align: right;">TOTAL:</td>
                                <td style="font-weight: bold;">{{$total}}€</td>
                                @if (count($compras)>1)
                                <td style=""></td>
                                @endif
                            </tr>
                        </tbody>
                    </table>
                    <center>

                        <?php

                            if ($vinc == 1){

                        ?>
                                <a href="{{ route('cliente.vinculaciones') }}"><input type="button" value="Solicitar más servicios de Vinculaciones" class="btn btn-primary cfrSefar"></a>
                        <?php

                            }

                        ?>
                    </center>
                    
                    <input type="hidden" id="idproducto" name="idproducto" value="{{isset($servicio[0]['id']) ? $servicio[0]['id'] : ''}}">
                </div>
            </div>

        </div>
        <div class='row' style="padding: 0px 60px 0px 20px;"><br><br>
            <small>
                <p>Sefar Universal se compromete a proteger y respetar tu privacidad, y solo usaremos tu información personal para administrar tu cuenta y proporcionar los productos y servicios que nos solicitaste. De vez en cuando, nos gustaría ponernos en contacto contigo acerca de nuestros productos y servicios, así como sobre otros contenidos que puedan interesarte.</p>

                <p>Puedes darte de baja de estas comunicaciones en cualquier momento. Para obtener más información sobre cómo darte de baja, nuestras prácticas de privacidad y cómo nos comprometemos a proteger y respetar tu privacidad, consulta nuestra Política de privacidad.
                Al hacer clic en Enviar, aceptas que Sefar Universal almacene y procese la información personal suministrada arriba para proporcionarte el contenido solicitado.</p>
            </small>
        </div>
    </form>



    <script type="text/javascript" src="//js.stripe.com/v2/"></script>

    <script type="text/javascript">

        $(function() {

            /*------------------------------------------
            --------------------------------------------
            Stripe Payment Code
            --------------------------------------------
            --------------------------------------------*/

            var $form = $(".require-validation");

            $('form.require-validation').bind('submit', function(e) {

                $("#ajaxload").show();

                var $form = $(".require-validation"),
                inputSelector = ['input[type=email]', 'input[type=password]',
                                 'input[type=text]', 'input[type=file]',
                                 'textarea'].join(', '),
                $inputs = $form.find('.required').find(inputSelector),
                $errorMessage = $form.find('div.error'),
                valid = true;
                $errorMessage.addClass('hide');

                $('.has-error').removeClass('has-error');
                $inputs.each(function(i, el) {
                  var $input = $(el);
                  if ($input.val() === '') {
                    $input.parent().addClass('has-error');
                    $errorMessage.removeClass('hide');
                    e.preventDefault();
                  }
                });

                if (!$form.data('cc-on-file')) {
                  e.preventDefault();
                  Stripe.setPublishableKey($form.data('stripe-publishable-key'));
                  Stripe.createToken({
                    number: $('.card-number').val(),
                    cvc: $('.card-cvc').val(),
                    exp_month: $('.card-expiry-month').val(),
                    exp_year: $('.card-expiry-year').val()
                  }, stripeResponseHandler);
                }

            });

            /*------------------------------------------
            --------------------------------------------
            Stripe Response Handler
            --------------------------------------------
            --------------------------------------------*/
            function stripeResponseHandler(status, response) {
                if (response.error) {
                    $("#ajaxload").hide();
                    var error = "";

                    switch(response.error.code){
                        case "authentication_required":
                            error = "La tarjeta fue rechazada porque la transacción requiere autenticación.";
                            break;
                        case "approve_with_id":
                            error = "No se puede autorizar el pago.";
                            break;
                        case "call_issuer":
                            error = "La tarjeta fue rechazada por un motivo desconocido.";
                            break;
                        case "card_not_supported":
                            error = "La tarjeta no admite este tipo de compra.";
                            break;
                        case "card_velocity_exceeded":
                            error = "El cliente ha superado el saldo o límite de crédito disponible en su tarjeta.";
                            break;
                        case "currency_not_supported":
                            error = "La tarjeta no admite la moneda especificada.";
                            break;
                        case "do_not_honor":
                            error = "La tarjeta fue rechazada por un motivo desconocido.";
                            break;
                        case "do_not_try_again":
                            error = "La tarjeta fue rechazada por un motivo desconocido.";
                            break;
                        case "duplicate_transaction":
                            error = "Recientemente se envió una transacción con la misma cantidad e información de la tarjeta de crédito.";
                            break;
                        case "expired_card":
                            error = "La tarjeta ha caducado.";
                            break;
                        case "fraudulent":
                            error = "El pago fue rechazado porque Stripe sospecha que es fraudulento.";
                            break;
                        case "generic_decline":
                            error = "La tarjeta fue rechazada por un motivo desconocido o posiblemente provocada por una regla de pago bloqueada .";
                            break;
                        case "incorrect_number":
                            error = "El número de tarjeta es incorrecto.";
                            break;
                        case "incorrect_cvc":
                            error = "El número CVC es incorrecto.";
                            break;
                        case "incorrect_pin":
                            error = "El PIN ingresado es incorrecto. Este código de rechazo solo se aplica a los pagos realizados con un lector de tarjetas.";
                            break;
                        case "incorrect_zip":
                            error = "El código postal es incorrecto.";
                            break;
                        case "insufficient_funds":
                            error = "La tarjeta no tiene fondos suficientes para completar la compra.";
                            break;
                        case "invalid_account":
                            error = "La tarjeta o la cuenta a la que está conectada la tarjeta no es válida.";
                            break;
                        case "invalid_amount":
                            error = "El monto del pago no es válido o excede el monto permitido.";
                            break;
                        case "invalid_cvc":
                            error = "El número CVC es incorrecto.";
                            break;
                        case "invalid_expiry_month":
                            error = "El mes de vencimiento no es válido.";
                            break;
                        case "invalid_expiry_year":
                            error = "El año de caducidad no es válido.";
                            break;
                        case "invalid_number":
                            error = "El número de tarjeta es incorrecto.";
                            break;
                        case "invalid_pin":
                            error = "El PIN ingresado es incorrecto. Este código de rechazo solo se aplica a los pagos realizados con un lector de tarjetas.";
                            break;
                        case "issuer_not_available":
                            error = "No se pudo contactar al emisor de la tarjeta, por lo que no se pudo autorizar el pago.";
                            break;
                        case "lost_card":
                            error = "El pago fue rechazado porque la tarjeta se reportó perdida.";
                            break;
                        case "merchant_blacklist":
                            error = "El pago fue rechazado porque coincide con un valor en la lista de bloqueo del usuario de Stripe.";
                            break;
                        case "new_account_information_available":
                            error = "La tarjeta o la cuenta a la que está conectada la tarjeta no es válida.";
                            break;
                        case "no_action_taken":
                            error = "La tarjeta fue rechazada por un motivo desconocido.";
                            break;
                        case "not_permitted":
                            error = "El pago no está permitido.";
                            break;
                        case "offline_pin_required":
                            error = "La tarjeta fue rechazada porque requiere un PIN.";
                            break;
                        case "online_or_offline_pin_required":
                            error = "La tarjeta fue rechazada porque requiere un PIN.";
                            break;
                        case "pickup_card":
                            error = "El cliente no puede usar esta tarjeta para realizar este pago (es posible que haya sido reportada como perdida o robada).";
                            break;
                        case "pin_try_exceeded":
                            error = "Se superó el número permitido de intentos de PIN.";
                            break;
                        case "processing_error":
                            error = "Ocurrió un error al procesar la tarjeta.";
                            break;
                        case "reenter_transaction":
                            error = "El emisor no pudo procesar el pago por un motivo desconocido.";
                            break;
                        case "restricted_card":
                            error = "El cliente no puede usar esta tarjeta para realizar este pago (es posible que haya sido reportada como perdida o robada).";
                            break;
                        case "revocation_of_all_authorizations":
                            error = "La tarjeta fue rechazada por un motivo desconocido";
                            break;
                        case "revocation_of_authorization":
                            error = "La tarjeta fue rechazada por un motivo desconocido.";
                            break;
                        case "security_violation":
                            error = "La tarjeta fue rechazada por un motivo desconocido.";
                            break;
                        case "service_not_allowed":
                            error = "La tarjeta fue rechazada por un motivo desconocido.";
                            break;
                        case "stolen_card":
                            error = "El pago fue rechazado porque la tarjeta fue reportada como robada.";
                            break;
                        case "stop_payment_order":
                            error = "La tarjeta fue rechazada por un motivo desconocido.";
                            break;
                        case "testmode_decline":
                            error = "Se utilizó un número de tarjeta de prueba de Stripe.";
                            break;
                        case "transaction_not_allowed":
                            error = "La tarjeta fue rechazada por un motivo desconocido.";
                            break;
                        case "try_again_later":
                            error = "La tarjeta fue rechazada por un motivo desconocido.";
                            break;
                        case "withdrawal_count_limit_exceeded":
                            error = "El cliente ha superado el saldo o límite de crédito disponible en su tarjeta.";
                            break;
                        default:
                            error = response.error.code;
                            break;
                    }

                    Swal.fire({
                        icon: 'error',
                        title: error,
                        showConfirmButton: false,
                        timer: 2500
                    });
                } else {
                    /* token contains id, last4, and card type */
                    var token = response['id'];

                    $form.find('input[type=text]').empty();
                    $form.append("<input type='hidden' name='stripeToken' value='" + token + "'/>");
                    $form.get(0).submit();
                }
            }

        });
    </script>
@stop

@section('css')
    <link rel="stylesheet" href="{{ asset('css/sefar.css') }}">
@stop

@section('js')

@stop
