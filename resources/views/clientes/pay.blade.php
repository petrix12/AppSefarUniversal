@extends('adminlte::page')

@section('title', 'Pago')

@section('content_header')
    <h1>Realizar pago</h1>
@stop

@section('content')
    @if(session("status")=="exito")
        <script type="text/javascript">
            Swal.fire({
                icon: 'error',
                title: 'Ha habido un error procesando su pago',
                showConfirmButton: false,
                timer: 2500
            });
        </script>
    @endif
    <form action="" method="POST" class="require-validation" data-cc-on-file="false" data-stripe-publishable-key="{{ env('STRIPE_KEY') }}" id="payment-form">
        <div class="container p-8 row" style="display:flex;">
            <script src="https://code.jquery.com/jquery-3.6.1.min.js"></script>
            
            <script src="https://cdnjs.cloudflare.com/ajax/libs/cleave.js/1.6.0/cleave.min.js"></script>

            <script>

                document.addEventListener('DOMContentLoaded', () => {

                    $('#nameoncard').keyup(function(){
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

                });

            </script>


            
            
            @csrf
            <div class="col-sm-12 col-md-7 mb-0">
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

                <div class='row' style="margin: 0;">
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

                <div class='row' style="justify-content: center;">
                    <center>
                        <br>
                        <button class="btn btn-primary btn-block" type="submit">Realizar pago</button>
                        <br>
                    </center> 
                </div>

                <div class='row'>
                    <p>Sefar Universal se compromete a proteger y respetar tu privacidad, y solo usaremos tu información personal para administrar tu cuenta y proporcionar los productos y servicios que nos solicitaste. De vez en cuando, nos gustaría ponernos en contacto contigo acerca de nuestros productos y servicios, así como sobre otros contenidos que puedan interesarte. Si aceptas que nos comuniquemos contigo para este fin, marca la casilla a continuación para indicar cómo deseas que nos comuniquemos</p>

                    <p>Puedes darte de baja de estas comunicaciones en cualquier momento. Para obtener más información sobre cómo darte de baja, nuestras prácticas de privacidad y cómo nos comprometemos a proteger y respetar tu privacidad, consulta nuestra Política de privacidad.
                    Al hacer clic en Enviar, aceptas que Sefar Universal almacene y procese la información personal suministrada arriba para proporcionarte el contenido solicitado.</p>
                </div>
            </div>
            <div class="col-sm-12 col-md-5 mb-0">
                <div class="card" style="margin:0 30px; padding: 30px;">
                    @php
                        $servicio= array();

                        $servicio["name"]="Nacionalidad Española por origen Sefardí";

                        $servicio["id"]=auth()->user()->servicio;

                        if(auth()->user()->servicio=="Española LMD"){
                            $servicio["name"]="Ley de Memoria Democratica";
                            $servicio["price"]=25;
                        } else {
                            if(auth()->user()->servicio=="Italiana"){
                                $servicio["name"]="Nacionalidad Italiana";
                            } else if(auth()->user()->servicio=="Española Sefardi"){
                                $servicio["name"]="Nacionalidad Española por origen Sefardí";
                            } else if(auth()->user()->servicio=="Portuguesa Sefardi"){
                                $servicio["name"]="Nacionalidad Portuguesa por origen Sefardí";
                            }
                            $servicio["price"]=50;
                        }
                    @endphp
                    <center>
                        <h3 style="padding:10px 0px; color:#12313a">Información del servicio</h3>
                        <img style="width:100px;" src="/vendor/adminlte/dist/img/LogoSefar.png">
                    </center>

                    <h4 style="padding:10px 0px; color:#12313a"><b>Inicia tu Proceso: {{$servicio["name"]}}</b></h4>

                    <h4 style="padding:10px 0px 2px 0px; color:#12313a">Pago: <b>{{$servicio["price"]}}€</b></h4> 
                    
                    <input type="hidden" id="idproducto" name="idproducto" value="{{$servicio['id']}}">
                </div>
            </div>
    
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
                    Swal.fire({
                        icon: 'error',
                        title: response.error.message,
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
