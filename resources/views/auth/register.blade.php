<?php
    // Captura de parámetros del JotForm

    if (!empty($_GET['numero_de_pasaporte'])) $dni = $_GET['numero_de_pasaporte']; else if (!empty(session('request')['numero_de_pasaporte'])) $dni = session('request')['numero_de_pasaporte']; else $dni = null;
    $passport = trim($dni);

    if (!empty($_GET['lastname'])) $apellidos = $_GET['lastname']; else if (!empty(session('request')['lastname'])) $apellidos = session('request')['lastname']; else $apellidos = null;
    if (!empty($_GET['email'])) $email = $_GET['email']; else if (!empty(session('request')['email'])) $email = session('request')['email']; else $email = null;
    if (!empty($_GET['phone'])) $phone = $_GET['phone']; else if (!empty(session('request')['phone'])) $phone = session('request')['phone']; else $phone = null;
    if (!empty($_GET['nacionalidad_solicitada'])) $servicio = $_GET['nacionalidad_solicitada']; else if (!empty(session('request')['nacionalidad_solicitada'])) $servicio = session('request')['nacionalidad_solicitada']; else $servicio = null;
    if (!empty($_GET['n000__referido_por__clonado_'])) $referido = $_GET['n000__referido_por__clonado_']; else if (!empty(session('request')['n000__referido_por__clonado_'])) $referido = session('request')['n000__referido_por__clonado_']; else $referido = null;
    if (!empty($_GET['aplicar_cupon'])) $cupon = $_GET['aplicar_cupon']; else if (!empty(session('request')['aplicar_cupon'])) $cupon = session('request')['aplicar_cupon']; else $cupon = null;
    if (!empty($_GET['pais_de_nacimiento'])) $pais_de_nacimiento = $_GET['pais_de_nacimiento']; else if (!empty(session('request')['pais_de_nacimiento'])) $pais_de_nacimiento = session('request')['pais_de_nacimiento']; else $pais_de_nacimiento = null;

    if (!empty($_GET['vinculo_antepasados'])) $vinculo_antepasados = $_GET['vinculo_antepasados']; else if (!empty(session('request')['vinculo_antepasados'])) $vinculo_antepasados = session('request')['vinculo_antepasados']; else $vinculo_antepasados = null;

    if (!empty($_GET['estado_de_datos_y_documentos_de_los_antepasados'])) $estado_de_datos_y_documentos_de_los_antepasados = $_GET['estado_de_datos_y_documentos_de_los_antepasados']; else if (!empty(session('request')['estado_de_datos_y_documentos_de_los_antepasados'])) $estado_de_datos_y_documentos_de_los_antepasados = session('request')['estado_de_datos_y_documentos_de_los_antepasados']; else $estado_de_datos_y_documentos_de_los_antepasados = null;

    if (!empty($_GET['pay'])) $pay = $_GET['pay']; else if (!empty(session('request')['pay'])) $pay = session('request')['pay']; else $pay = 0;

    if (!empty($_GET['monto'])) $monto = $_GET['monto']; else if (!empty(session('request')['monto'])) $monto = session('request')['monto']; else $monto = 0;

    if (!empty($_GET['jotform'])) $jotform = $_GET['jotform']; else if (!empty(session('request')['jotform'])) $jotform = session('request')['jotform']; else $jotform = 0;

    if (!empty($_GET['tiene_hermanos'])) $tiene_hermanos = $_GET['tiene_hermanos']; else if (!empty(session('request')['tiene_hermanos'])) $tiene_hermanos = session('request')['tiene_hermanos']; else $tiene_hermanos = 0;

    $antepasados = 0;

    if (isset(session('request')['tiene_antepasados_espanoles']) && session('request')['tiene_antepasados_espanoles'] == "Si"){
        $antepasados = 1;
    }

    if (isset(session('request')['tiene_antepasados_italianos']) && session('request')['tiene_antepasados_italianos'] == "Si"){
        $antepasados = 2;
    }

    $cantidad_alzada = 1;

    // Captura de parámetros de Alzada

    if (!empty(session('request')['cantidad_alzada'])) $cantidad_alzada = session('request')['cantidad_alzada'] + 1; else $cantidad_alzada = 1;

    $rol = 'cliente';

    // Unir nombres y apellidos
    $name = null;
    if (!empty($_GET['firstname'])){
        if(is_null($apellidos)){
            $name = $_GET['firstname'];
        } else {
            $name = $_GET['firstname'].' '.$_GET['lastname'];
        }
        $nombres = $_GET['firstname'];
    }else{
        $nombres = null;
    }

    if (!empty(session('request')['firstname'])){
        if(is_null($apellidos)){
            $name = session('request')['firstname'];
        } else {
            $name = session('request')['firstname'].' '.session('request')['lastname'];
        }
        $nombres = session('request')['firstname'];
    }else{
        $nombres = null;
    }
?>
<x-guest-layout>
    <x-authentication-card>
        <x-slot name="logo">
            {{-- <x-authentication-card-logo /> --}}
            @include('layouts.logos.logo')
        </x-slot>

        @if($jotform!=0)
            <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

            <script>
                Swal.fire({
                  icon: 'success',
                  title: '¡Gracias!',
                  html: '<b>Hemos procesado tu pago correctamente.</b><br><br>Para continuar con tu proceso, debes completar tu registro en nuestra app.'
                })
            </script>
        @endif

        <x-validation-errors class="mb-4" />

        <form method="POST" action="{{ route('register') }}">
            <center>
                <h1 style="font-size: 20px; margin: 5px 0px;">Crea tu contraseña</h1>

            </center>
            @csrf

            {{-- Campos ocultos --}}
            <input type="hidden" name="nombres" value="{{ old('nombres',$nombres) }}" />
            <input type="hidden" name="apellidos" value="{{ old('apellidos',$apellidos) }}" />
            <input type="hidden" name="phone" value="{{ old('phone',$phone) }}" />
            <input type="hidden" name="servicio" value="{{ old('servicio',$servicio) }}" />
            <input type="hidden" name="referido" value="{{ old('referido',$referido) }}" />
            <input type="hidden" name="pay" value="{{ old('pay',$pay) }}" />
            <input type="hidden" name="monto" value="{{ old('monto',$monto) }}" />
            <input type="hidden" name="cupon" value="{{ old('cupon',$cupon) }}" />
            <input type="hidden" name="pais_de_nacimiento" value="{{ old('pais_de_nacimiento',$pais_de_nacimiento) }}" />
            <input type="hidden" name="rol" value="{{ old('rol',$rol) }}" />
            <input type="hidden" name="cantidad_alzada" value="{{ old('cantidad_alzada',$cantidad_alzada) }}" />
            <input type="hidden" name="antepasados" value="{{ old('antepasados',$antepasados) }}" />
            <input type="hidden" name="vinculo_antepasados" value="{{ old('vinculo_antepasados',$vinculo_antepasados) }}" />
            <input type="hidden" name="estado_de_datos_y_documentos_de_los_antepasados" value="{{ old('estado_de_datos_y_documentos_de_los_antepasados',$estado_de_datos_y_documentos_de_los_antepasados) }}" />
            <input type="hidden" name="tiene_hermanos" value="{{ old('tiene_hermanos',$tiene_hermanos) }}" />

            <div>
                <x-label for="name" value="{{ __('Name') }}" />
                <x-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name',$name)" required autofocus autocomplete="name" />
            </div>

            <div class="mt-4">
                <x-label for="email" value="{{ __('Email') }}" />
                <x-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email',$email)" required />
            </div>

            @if ($rol == 'cliente')
            <div class="mt-4">
                <x-label for="passport" value="{{ __('Número de Pasaporte o Identificación') }}" />
                <x-input id="passport" class="block mt-1 w-full" type="text" name="passport" :value="old('passport',$passport)" required />
            </div>
            @else
            <input type="hidden" name="passport" value="{{ $passport }}" />
            @endif

            <div class="mt-4">
                <x-label for="password" value="{{ __('Password') }}" />
                <x-input id="password" class="block mt-1 w-full" type="password" name="password" required autocomplete="new-password" />
            </div>

            <div class="mt-4">
                <x-label for="password_confirmation" value="{{ __('Confirm Password') }}" />
                <x-input id="password_confirmation" class="block mt-1 w-full" type="password" name="password_confirmation" required autocomplete="new-password" />
            </div>

            @if (Laravel\Jetstream\Jetstream::hasTermsAndPrivacyPolicyFeature())
                <div class="mt-4">
                    <x-label for="terms">
                        <div class="flex items-center">
                            <x-checkbox name="terms" id="terms"/>

                            <div class="ml-2">
                                {!! __('I agree to the :terms_of_service and :privacy_policy', [
                                        'terms_of_service' => '<a target="_blank" href="'.route('terms.show').'" class="underline text-sm text-gray-600 hover:text-gray-900">'.__('Terms of Service').'</a>',
                                        'privacy_policy' => '<a target="_blank" href="'.route('policy.show').'" class="underline text-sm text-gray-600 hover:text-gray-900">'.__('Privacy Policy').'</a>',
                                ]) !!}
                            </div>
                        </div>
                    </x-label>
                </div>
            @endif

            <div class="flex items-center justify-end mt-4">
                <a class="underline text-sm text-gray-600 hover:text-gray-900" href="{{ route('login') }}">
                    {{ __('Already registered?') }}
                </a>

                <x-button class="ml-4 cfrSefar">
                    Continuar
                </x-button>
            </div>
        </form>
    </x-authentication-card>
</x-guest-layout>
