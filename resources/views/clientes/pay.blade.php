@extends('adminlte::page')

@section('title', 'Pago')

@section('content_header')
    <h1>Realizar pago</h1>
@stop

<script src="//cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div id="ajaxload" style="background-color: rgba(0, 0, 0, 0.4); position: fixed; z-index: 1000; display: none; width: 100%; height: 100%; top: 0; left: 0;">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center;">
        <div class="spinner-border text-light" role="status" style="width: 4rem; height: 4rem;">
            <span class="sr-only">Cargando...</span>
        </div>
        <p class="text-light mt-3">Procesando pago...</p>
    </div>
</div>

@section('content')

    {{-- Mensajes de error de sesión --}}
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

    <style>
        /* Ocultar botón de PayPal */
        #paypal-button-container {
            display: none !important;
        }

        /* Estilos para Stripe Elements */
        .StripeElement {
            box-sizing: border-box;
            height: 40px;
            padding: 10px 12px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            background-color: white;
            box-shadow: 0 1px 3px 0 #e6ebf1;
            transition: box-shadow 150ms ease;
        }

        .StripeElement--focus {
            box-shadow: 0 1px 3px 0 #cfd7df;
            border-color: #80bdff;
        }

        .StripeElement--invalid {
            border-color: #fa755a;
        }

        .StripeElement--webkit-autofill {
            background-color: #fefde5 !important;
        }

        #card-errors {
            color: #fa755a;
            font-size: 14px;
            margin-top: 10px;
        }

        /* Separador visual */
        .separator {
            border-top: 2px solid #e9ecef;
            margin: 30px 0;
        }

        /* Estilos específicos para las alertas en el foreach */
        .custom-swal-popup {
            width: 50em !important;
        }
        .custom-swal-image {
            min-height: 70vh;
            max-height: 70vh;
            min-width: 100%;
            max-width: 100%;
            object-fit: contain;
        }

        /* Alertas flotantes */
        .alerta-popup {
            display: inline-block;
            height: 200px !important;
            border-radius: 10px;
            overflow: hidden;
            position: relative;
            background-size: cover;
            background-repeat: no-repeat;
            background-position: center;
            margin-top: 10px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
            font-family: 'Roboto', sans-serif;
            max-width: 400px;
            transition: all 0.3s ease;
        }

        .alerta-close {
            position: absolute;
            top: 5px;
            right: 5px;
            width: 25px;
            height: 25px;
            border-radius: 50%;
            background-color: #F8F8F7;
            color: #000;
            font-size: 16px;
            text-align: center;
            line-height: 25px;
            cursor: pointer;
            z-index: 10;
        }

        .alerta-cupon {
            position: absolute;
            bottom: 10px;
            left: 50%;
            transform: translateX(-50%);
            background-color: #06C2CC;
            color: white;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 14px;
            cursor: pointer;
            opacity: 0;
            transition: opacity 0.3s ease;
            z-index: 5;
            font-weight: bold;
        }

        .alerta-popup:hover .alerta-cupon {
            opacity: 1;
        }

        /* Label required */
        .required-label::after {
            content: " *";
            color: red;
        }
    </style>

    @if ($alertas->isNotEmpty())
        <script>
            const alertas = {!! json_encode($alertas) !!};
        </script>
    @else
        <script>
            const alertas = [];
        </script>
    @endif

    <div id="alerta-container" style="position: fixed; bottom: 20px; right: 20px; z-index: 9999;"></div>

    <script src="https://code.jquery.com/jquery-3.6.1.min.js"></script>

    <script>
        $(document).ready(function() {
            // Alertas flotantes
            if (Array.isArray(alertas) && alertas.length > 0) {
                const container = document.getElementById('alerta-container');

                alertas.forEach(alerta => {
                    const popup = document.createElement('div');
                    popup.classList.add('alerta-popup');

                    const img = new Image();
                    img.src = alerta.image;

                    img.onload = function () {
                        popup.style.width = img.width + 'px';
                        popup.style.height = img.height + 'px';
                        popup.style.backgroundImage = `url(${alerta.image})`;
                    };

                    const cuponBtn = document.createElement('div');
                    cuponBtn.classList.add('alerta-cupon');
                    cuponBtn.textContent = 'Copiar Cupón';
                    cuponBtn.addEventListener('click', (e) => {
                        e.stopPropagation();
                        e.preventDefault();
                        navigator.clipboard.writeText(alerta.title).then(() => {
                            cuponBtn.textContent = '¡Copiado!';
                            setTimeout(() => {
                                cuponBtn.textContent = 'Copiar Cupón';
                            }, 2000);
                        }).catch(() => {
                            alert("No se pudo copiar el cupón");
                        });
                    });

                    const closeBtn = document.createElement('div');
                    closeBtn.classList.add('alerta-close');
                    closeBtn.textContent = '×';
                    closeBtn.addEventListener('click', () => {
                        popup.remove();
                    });

                    const link = document.createElement('a');
                    link.href = alerta.link || "#";
                    link.target = "_blank";
                    link.style.position = "absolute";
                    link.style.top = "0";
                    link.style.left = "0";
                    link.style.width = "100%";
                    link.style.height = "100%";
                    link.style.zIndex = "1";

                    popup.appendChild(link);
                    popup.appendChild(closeBtn);
                    popup.appendChild(cuponBtn);
                    container.appendChild(popup);
                });
            }

            // Validar cupón
            $(document).on("click", "#valcoupon", function(e){
                e.preventDefault();
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
                                title: 'Se ha aplicado un descuento de un ' + response["percentage"] + '%.',
                                showConfirmButton: false,
                                timer: 3000
                            }).then(function() {
                                window.location.reload();
                            });
                        } else if (response["status"]=="promo") {
                            $("#ajaxload").hide();
                            Swal.fire({
                                icon: 'success',
                                title: 'Se ha aplicado un descuento: ' + response["percentage"] + '.',
                                showConfirmButton: false,
                                timer: 3000
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

            // Eliminar elemento de pago
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
            });

            // Convertir a mayúsculas
            $('#coupon').keyup(function(){
                this.value = this.value.toUpperCase();
            });
        });
    </script>

    <div class="container-fluid px-2 py-3">
        @csrf
        <form id="payment-form">
            <div class="row">
                <!-- Columna izquierda: Datos de pago -->
                <div class="col-12 col-lg-7 mb-3 order-2 order-lg-1">
                    <div class="card card-body shadow">
                        {{-- Sección de cupón --}}
                        <h2 class="text-center mb-4">
                            <b>Ingresar Cupón</b>
                        </h2>
                        <div class="form-group mb-3">
                            <label for="coupon">Código de cupón</label>
                            <input
                                autocomplete="off"
                                name="coupon"
                                id="coupon"
                                class="form-control"
                                type="text"
                                placeholder="Ingrese su cupón">
                        </div>

                        <div class="d-flex justify-content-center mb-3">
                            <button type="button" class="btn btn-info me-2" id="valcoupon">Validar cupón</button>
                        </div>

                        <div class="separator"></div>

                        {{-- Información Personal --}}
                        <h2 class="text-center mb-4">
                            <b>Información Personal</b>
                        </h2>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="first_name" class="control-label required-label">Nombre</label>
                                <input
                                    required
                                    autocomplete="given-name"
                                    id="first_name"
                                    name="first_name"
                                    class="form-control"
                                    value="{{ auth()->user()->nombres }}"
                                    type="text"
                                    placeholder="Juan">
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="last_name" class="control-label required-label">Apellido</label>
                                <input
                                    required
                                    autocomplete="family-name"
                                    id="last_name"
                                    name="last_name"
                                    class="form-control"
                                    value="{{ auth()->user()->apellidos }}"
                                    type="text"
                                    placeholder="Pérez">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="email" class="control-label required-label">Correo electrónico</label>
                                <input
                                    required
                                    autocomplete="email"
                                    id="email"
                                    name="email"
                                    class="form-control"
                                    type="email"
                                    value="{{ auth()->user()->email }}"
                                    placeholder="ejemplo@correo.com">
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="phone" class="control-label">Teléfono (Opcional)</label>
                                <input
                                    autocomplete="tel"
                                    id="phone"
                                    name="phone"
                                    class="form-control"
                                    value="{{ auth()->user()->phone }}"
                                    type="tel"
                                    placeholder="+34 600 000 000">
                            </div>
                        </div>

                        <div class="separator"></div>

                        {{-- Dirección de Facturación --}}
                        <h2 class="text-center mb-4">
                            <b>Dirección de Facturación</b>
                        </h2>

                        <div class="form-group mb-3">
                            <label for="address_line1" class="control-label required-label">Dirección</label>
                            <input
                                required
                                autocomplete="address-line1"
                                id="address_line1"
                                name="address_line1"
                                class="form-control"
                                type="text"
                                placeholder="Calle Principal 123">
                        </div>

                        <div class="form-group mb-3">
                            <label for="address_line2" class="control-label">Dirección 2 (Opcional)</label>
                            <input
                                autocomplete="address-line2"
                                id="address_line2"
                                name="address_line2"
                                class="form-control"
                                type="text"
                                placeholder="Apartamento, suite, etc.">
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="city" class="control-label required-label">Ciudad</label>
                                <input
                                    required
                                    autocomplete="address-level2"
                                    id="city"
                                    name="city"
                                    class="form-control"
                                    type="text"
                                    placeholder="Madrid">
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="state" class="control-label">Estado/Provincia</label>
                                <input
                                    autocomplete="address-level1"
                                    id="state"
                                    name="state"
                                    class="form-control"
                                    type="text"
                                    placeholder="Comunidad de Madrid">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="postal_code" class="control-label required-label">Código Postal</label>
                                <input
                                    required
                                    autocomplete="postal-code"
                                    id="postal_code"
                                    name="postal_code"
                                    class="form-control"
                                    type="text"
                                    placeholder="28001">
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="country" class="control-label required-label">País</label>
                                <select
                                    required
                                    autocomplete="country"
                                    id="country"
                                    name="country"
                                    class="form-control">
                                    <option value="">Seleccione un país</option>
                                    <option value="ES" selected>España</option>
                                    <option value="PT">Portugal</option>
                                    <option value="FR">Francia</option>
                                    <option value="IT">Italia</option>
                                    <option value="DE">Alemania</option>
                                    <option value="GB">Reino Unido</option>
                                    <option value="US">Estados Unidos</option>
                                    <option value="MX">México</option>
                                    <option value="AR">Argentina</option>
                                    <option value="CO">Colombia</option>
                                    <option value="VE">Venezuela</option>
                                    <option value="CL">Chile</option>
                                    <option value="PE">Perú</option>
                                    <option value="EC">Ecuador</option>
                                    <option value="UY">Uruguay</option>
                                    <option value="PY">Paraguay</option>
                                    <option value="BO">Bolivia</option>
                                    <option value="CR">Costa Rica</option>
                                    <option value="PA">Panamá</option>
                                    <option value="DO">República Dominicana</option>
                                    <option value="BR">Brasil</option>
                                    <option value="CA">Canadá</option>
                                    <!-- Agrega más países según necesites -->
                                </select>
                            </div>
                        </div>

                        <div class="separator"></div>

                        {{-- Información de la Tarjeta --}}
                        <h2 class="text-center mb-4">
                            <b>Información de la Tarjeta</b>
                        </h2>

                        <div class="form-group mb-3">
                            <label for="name_on_card" class="control-label required-label">Nombre en la Tarjeta</label>
                            <input
                                required
                                autocomplete="cc-name"
                                id="name_on_card"
                                name="name_on_card"
                                class="form-control"
                                type="text"
                                placeholder="JUAN PEREZ"
                                style="text-transform: uppercase;">
                        </div>

                        <div class="form-group mb-3">
                            <label for="card-element" class="control-label required-label">
                                Número de Tarjeta, Fecha de Vencimiento y CVC
                            </label>
                            <div id="card-element">
                                <!-- Stripe Elements insertará aquí el campo de tarjeta -->
                            </div>
                            <div id="card-errors" role="alert"></div>
                        </div>

                        <div class="d-flex justify-content-center mb-3">
                            <button class="btn btn-primary btn-lg cfrSefar" type="submit" id="submit-button">
                                <span id="button-text">Realizar pago</span>
                                <span id="spinner" class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                            </button>
                        </div>

                        <div class="text-center mb-3">
                            <small class="text-muted">
                                <i class="fas fa-lock"></i> Pago seguro procesado por Stripe
                            </small>
                        </div>

                        <div class="separator"></div>

                        <div class="d-flex justify-content-center mb-3">
                            <div id="paypal-button-container" class="w-100"></div>
                        </div>
                    </div>
                </div>

                <!-- Columna derecha: Resumen de compra -->
                <div class="col-12 col-lg-5 mb-3 order-1 order-lg-2">
                    <div class="card shadow">
                        <div class="card-body">
                            <div class="text-center mb-3">
                                <img class="img-fluid" style="max-width:100px;
                                    background: #093143 !important;
                                    margin-bottom: 15px; border-radius:100px;"
                                     src="/vendor/adminlte/dist/img/LogoSefar.png"
                                     alt="Logo Sefar">
                                <h4 style="font-weight: bold;">Resumen de Compra</h4>
                            </div>

                            <div class="table-responsive">
                                <table class="table styled-table">
                                    <thead>
                                        <tr>
                                            <th>Descripción</th>
                                            <th>Costo(€)</th>
                                            @if (count($compras) > 1)
                                                <th>Acciones</th>
                                            @endif
                                        </tr>
                                    </thead>
                                    <tbody>
                                    @php
                                        $total = 0;
                                        $vinc = 0;
                                    @endphp
                                    @foreach ($compras as $compra)
                                        @php
                                            if (
                                                auth()->user()->servicio == 'Constitución de Empresa' ||
                                                auth()->user()->servicio == 'Representante Fiscal' ||
                                                auth()->user()->servicio == 'Codigo  Fiscal' ||
                                                auth()->user()->servicio == 'Apertura de cuenta' ||
                                                auth()->user()->servicio == 'Trimestre contable' ||
                                                auth()->user()->servicio == 'Cooperativa 10 años' ||
                                                auth()->user()->servicio == 'Cooperativa 5 años'
                                            ) {
                                                $vinc = 1;
                                            }
                                            $total += $compra["monto"];
                                        @endphp
                                        <tr>
                                            <td>{{ $compra["descripcion"] }}</td>
                                            <td><center>{{ $compra["monto"] }}€</center></td>
                                            @if(count($compras) > 1)
                                                <td>
                                                    <center>
                                                    <i class="fas fa-trash text-danger cursor-pointer deletedesc" id="{{ $compra['id'] }}" style="font-size: 1.2rem; cursor: pointer;"></i>
                                                    </center>
                                                </td>
                                            @endif
                                        </tr>
                                    @endforeach
                                        <tr>
                                            <td class="text-end fw-bold" style="text-align: right"><b>TOTAL:</b></td>
                                            <td class="fw-bold"><center><b>{{ $total }}€</b></center></td>
                                            @if (count($compras) > 1)
                                                <td></td>
                                            @endif
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            @if($vinc == 1)
                                <div class="text-center mt-3">
                                    <a href="{{ route('cliente.vinculaciones') }}"
                                       class="btn btn-primary">
                                        Solicitar más servicios de Vinculaciones
                                    </a>
                                </div>
                            @endif

                            <input type="hidden" id="idproducto"
                                   name="idproducto"
                                   value="{{ isset($servicio[0]['id']) ? $servicio[0]['id'] : '' }}">
                        </div>
                    </div>

                    {{-- Información de seguridad --}}
                    <div class="card shadow mt-3">
                        <div class="card-body">
                            <h5 class="card-title"><i class="fas fa-shield-alt text-success"></i> Pago Seguro</h5>
                            <p class="card-text small">
                                Tu información está protegida con encriptación SSL de 256 bits.
                                No almacenamos los datos de tu tarjeta en nuestros servidores.
                            </p>
                            <div class="text-center">
                                <img src="https://upload.wikimedia.org/wikipedia/commons/b/ba/Stripe_Logo%2C_revised_2016.svg"
                                     alt="Stripe"
                                     style="max-width: 80px; opacity: 0.7;">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class='row' style="padding: 0px 60px 0px 20px;">
                <div class="col-12">
                    <div class="alert alert-info" role="alert">
                        <small>
                            <p class="mb-2"><strong>Política de Privacidad</strong></p>
                            <p class="mb-2">Sefar Universal se compromete a proteger y respetar tu privacidad, y solo usaremos tu información personal para administrar tu cuenta y proporcionar los productos y servicios que nos solicitaste. De vez en cuando, nos gustaría ponernos en contacto contigo acerca de nuestros productos y servicios, así como sobre otros contenidos que puedan interesarte.</p>
                            <p class="mb-0">Puedes darte de baja de estas comunicaciones en cualquier momento. Para obtener más información sobre cómo darte de baja, nuestras prácticas de privacidad y cómo nos comprometemos a proteger y respetar tu privacidad, consulta nuestra Política de privacidad.
                            Al hacer clic en "Realizar pago", aceptas que Sefar Universal almacene y procese la información personal suministrada arriba para proporcionarte el contenido solicitado.</p>
                        </small>
                    </div>
                </div>
            </div>
        </form>
    </div>

    {{-- Scripts de Stripe --}}
    <script src="https://js.stripe.com/v3/"></script>
    <script>
        // Determinar qué clave usar según el servicio
        @if (auth()->user()->servicio == 'Constitución de Empresa' || auth()->user()->servicio == 'Representante Fiscal' || auth()->user()->servicio == 'Codigo  Fiscal' || auth()->user()->servicio == 'Apertura de cuenta' || auth()->user()->servicio == 'Trimestre contable' || auth()->user()->servicio == 'Cooperativa 10 años' || auth()->user()->servicio == 'Cooperativa 5 años' || auth()->user()->servicio == 'Portuguesa Sefardi' || auth()->user()->servicio == 'Portuguesa Sefardi - Subsanación' || auth()->user()->servicio == 'Certificación de Documentos - Portugal')
            var stripePublicKey = '{{ env("STRIPE_KEY_PORT") }}';
        @else
            var stripePublicKey = '{{ env("STRIPE_KEY") }}';
        @endif

        // Inicializar Stripe
        var stripe = Stripe(stripePublicKey);
        var elements = stripe.elements();

        // Configuración de estilo para el elemento de tarjeta
        var style = {
            base: {
                color: '#32325d',
                fontFamily: '"Helvetica Neue", Helvetica, sans-serif',
                fontSmoothing: 'antialiased',
                fontSize: '16px',
                '::placeholder': {
                    color: '#aab7c4'
                }
            },
            invalid: {
                color: '#fa755a',
                iconColor: '#fa755a'
            }
        };

        // Crear elemento de tarjeta
        var cardElement = elements.create('card', {style: style});
        cardElement.mount('#card-element');

        // Manejar errores de validación en tiempo real
        cardElement.on('change', function(event) {
            var displayError = document.getElementById('card-errors');
            if (event.error) {
                displayError.textContent = event.error.message;
            } else {
                displayError.textContent = '';
            }
        });

        // Manejar el envío del formulario
        var form = document.getElementById('payment-form');
        form.addEventListener('submit', async function(event) {
            event.preventDefault();

            // Deshabilitar el botón de envío
            var submitButton = document.getElementById('submit-button');
            var buttonText = document.getElementById('button-text');
            var spinner = document.getElementById('spinner');

            submitButton.disabled = true;
            buttonText.classList.add('d-none');
            spinner.classList.remove('d-none');

            // Mostrar loader
            $("#ajaxload").show();

            // Crear el Payment Method con toda la información
            const {paymentMethod, error} = await stripe.createPaymentMethod({
                type: 'card',
                card: cardElement,
                billing_details: {
                    name: document.getElementById('name_on_card').value,
                    email: document.getElementById('email').value,
                    phone: document.getElementById('phone').value,
                    address: {
                        line1: document.getElementById('address_line1').value,
                        line2: document.getElementById('address_line2').value,
                        city: document.getElementById('city').value,
                        state: document.getElementById('state').value,
                        postal_code: document.getElementById('postal_code').value,
                        country: document.getElementById('country').value,
                    }
                },
            });

            if (error) {
                // Mostrar error
                var errorElement = document.getElementById('card-errors');
                errorElement.textContent = error.message;

                $("#ajaxload").hide();
                submitButton.disabled = false;
                buttonText.classList.remove('d-none');
                spinner.classList.add('d-none');

                Swal.fire({
                    icon: 'error',
                    title: 'Error en la tarjeta',
                    text: error.message,
                    showConfirmButton: true
                });
            } else {
                // Enviar el paymentMethod.id al servidor
                $.ajax({
                    url: '{{ route("procesar-pago-stripe") }}', // Debes crear esta ruta
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    data: JSON.stringify({
                        payment_method_id: paymentMethod.id,
                        first_name: document.getElementById('first_name').value,
                        last_name: document.getElementById('last_name').value,
                        email: document.getElementById('email').value,
                        phone: document.getElementById('phone').value,
                        address_line1: document.getElementById('address_line1').value,
                        address_line2: document.getElementById('address_line2').value,
                        city: document.getElementById('city').value,
                        state: document.getElementById('state').value,
                        postal_code: document.getElementById('postal_code').value,
                        country: document.getElementById('country').value
                    }),
                    contentType: 'application/json',
                    success: function(response) {
                        if (response.success) {
                            window.location.href = "{{ route('gracias') }}";
                        } else {
                            $("#ajaxload").hide();
                            submitButton.disabled = false;
                            buttonText.classList.remove('d-none');
                            spinner.classList.add('d-none');

                            Swal.fire({
                                icon: 'error',
                                title: 'Error al procesar el pago',
                                text: response.message || 'Ha ocurrido un error. Por favor, intenta nuevamente.',
                                showConfirmButton: true
                            });
                        }
                    },
                    error: function(xhr, status, error) {
                        $("#ajaxload").hide();
                        submitButton.disabled = false;
                        buttonText.classList.remove('d-none');
                        spinner.classList.add('d-none');

                        console.error('Error al procesar el pago:', error);
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Hubo un problema al procesar tu pago. Por favor, intenta nuevamente.'
                        });
                    }
                });
            }
        });

        // Convertir nombre en tarjeta a mayúsculas
        document.getElementById('name_on_card').addEventListener('input', function() {
            this.value = this.value.toUpperCase();
        });
    </script>

    {{-- Script de PayPal (oculto) --}}
    <script
        src="https://www.paypal.com/sdk/js?client-id={{ env('PAYPAL_CLIENT_ID') }}&currency=EUR&components=buttons&disable-funding=credit,card"
        data-sdk-integration-source="developer-studio"
    ></script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            window.paypal.Buttons({
                createOrder: function (data, actions) {
                    return actions.order.create({
                        purchase_units: [{
                            amount: {
                                value: '{{ $total }}'
                            }
                        }]
                    });
                },
                onApprove: function (data, actions) {
                    return actions.order.capture().then(function (details) {
                        $("#ajaxload").show();
                        $.ajax({
                            url: "{{ route('procesarpaypal') }}",
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            data: JSON.stringify({
                                orderID: data.orderID,
                                details: details
                            }),
                            contentType: 'application/json',
                            success: function(response) {
                                window.location.href = "{{ route('gracias') }}";
                            },
                            error: function(xhr, status, error) {
                                console.error('Error al procesar el pago:', error);
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error',
                                    text: 'Hubo un problema al procesar tu pago. Por favor, intenta nuevamente.'
                                });
                            }
                        });
                    });
                },
                onError: function (err) {
                    console.error('Ocurrió un error con PayPal: ', err);
                    Swal.fire({
                        icon: 'error',
                        title: 'Ocurrió un error al procesar el pago con PayPal.',
                        showConfirmButton: true
                    });
                }
            }).render('#paypal-button-container');
        });
    </script>
@stop

@section('css')
    <link rel="stylesheet" href="{{ asset('css/sefar.css') }}">
@stop

@section('js')

@stop
