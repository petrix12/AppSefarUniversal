<?php
    // Captura de parámetros del JotForm
    if (!empty($_GET['pasaporte'])){
        $passport = trim($_GET['pasaporte']);
        $rol = 'cliente';
    }else{
        $passport = null;
        $rol = null;
    }
    if (!empty($_GET['apellidos'])) $apellidos = $_GET['apellidos']; else $apellidos = null;
    if (!empty($_GET['email'])) $email = $_GET['email']; else $email = null;
    if (!empty($_GET['telefono'])) $telefono = $_GET['telefono']; else $telefono = null;

    if (!empty($_GET['fnacimiento'])) $fnacimiento = $_GET['fnacimiento']; else $fnacimiento = null;
    if (!empty($_GET['cnacimiento'])) $cnacimiento = $_GET['cnacimiento']; else $cnacimiento = null;
    if (!empty($_GET['pnacimiento'])) $pnacimiento = $_GET['pnacimiento']; else $pnacimiento = null;
    if (!empty($_GET['sexo'])) $sexo = $_GET['sexo']; else $sexo = null;
    if (!empty($_GET['nombre_f'])) $nombre_f = $_GET['nombre_f']; else $nombre_f = null;
    if (!empty($_GET['pasaporte_f'])) $pasaporte_f = $_GET['pasaporte_f']; else $pasaporte_f = null;
    if (!empty($_GET['referido'])) $referido = $_GET['referido']; else $referido = null;

    if (trim($referido) == 'P'){
        $referido = 'P & V Abogados';
    }

    $name = null;
    if (!empty($_GET['nombres'])){
        if(is_null($apellidos)){
            $name = $_GET['nombres'];
        } else {
            $name = $_GET['nombres'].' '.$_GET['apellidos'];
        }
        $nombres = $_GET['nombres'];
    }else{
        $nombres = null;
    }

    switch ($sexo) {
        case "FEMENINO":
            $sexo = 'F';
            break;
        case "FEMENINO / FEMALE":
            $sexo = 'F';
            break;
        case "MASCULINO":
            $sexo = 'M';
            break;
        case "MASCULINO / MALE":
            $sexo = 'M';
            break;
        case "OTROS":
            $sexo = 'O';
            break;
    }
    // Familiares
    $Familiares = is_null($nombre_f) ? NULL : 'Si';
    // Fecha de nacimiento
    $AnhoNac = date("Y", strtotime($fnacimiento));
    $MesNac = date("m", strtotime($fnacimiento));
    $DiaNac = date("d", strtotime($fnacimiento));
?>
<x-guest-layout>
    <x-authentication-card>
        <x-slot name="logo">
            {{-- <x-authentication-card-logo /> --}}
            @include('layouts.logos.logo')
        </x-slot>

        <x-validation-errors class="mb-4" />

        <form method="POST" action="{{ route('register') }}">
            @csrf

            {{-- Campos ocultos --}}
            <input type="hidden" name="nombres" value="{{ $nombres }}" />
            <input type="hidden" name="apellidos" value="{{ $apellidos }}" />
            <input type="hidden" name="fnacimiento" value="{{ $fnacimiento }}" />
            <input type="hidden" name="cnacimiento" value="{{ $cnacimiento }}" />
            <input type="hidden" name="pnacimiento" value="{{ $pnacimiento }}" />
            <input type="hidden" name="sexo" value="{{ $sexo }}" />
            <input type="hidden" name="nombre_f" value="{{ $nombre_f }}" />
            <input type="hidden" name="pasaporte_f" value="{{ $pasaporte_f }}" />
            <input type="hidden" name="rol" value="{{ $rol }}" />
            <input type="hidden" name="referido" value="{{ $referido }}" />
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
                <x-label for="passport" value="{{ __('Passport') }}" />
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
                    {{ __('Register') }}
                </x-button>
            </div>
        </form>
    </x-authentication-card>
</x-guest-layout>
