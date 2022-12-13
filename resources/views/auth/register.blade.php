<?php
    // Captura de parámetros del JotForm
    if (!empty($_GET['numero_de_pasaporte'])) $dni = $_GET['numero_de_pasaporte']; else $dni = null;
    $passport = trim($dni);

    if (!empty($_GET['lastname'])) $apellidos = $_GET['lastname']; else $apellidos = null;
    if (!empty($_GET['email'])) $email = $_GET['email']; else $email = null;
    if (!empty($_GET['phone'])) $phone = $_GET['phone']; else $phone = null;
    if (!empty($_GET['nacionalidad_solicitada'])) $servicio = $_GET['nacionalidad_solicitada']; else $servicio = null;

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
?>
<x-guest-layout>
    <x-jet-authentication-card>
        <x-slot name="logo">
            {{-- <x-jet-authentication-card-logo /> --}}
            @include('layouts.logos.logo')
        </x-slot>

        <x-jet-validation-errors class="mb-4" />

        <form method="POST" action="{{ route('register') }}">
            @csrf

            {{-- Campos ocultos --}}
            <input type="hidden" name="nombres" value="{{ $nombres }}" />
            <input type="hidden" name="apellidos" value="{{ $apellidos }}" />
            <input type="hidden" name="phone" value="{{ $phone }}" />
            <input type="hidden" name="servicio" value="{{ $servicio }}" />
            <input type="hidden" name="rol" value="{{ $rol }}" />

            <div>
                <x-jet-label for="name" value="{{ __('Name') }}" />
                <x-jet-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name',$name)" required autofocus autocomplete="name" />
            </div>

            <div class="mt-4">
                <x-jet-label for="email" value="{{ __('Email') }}" />
                <x-jet-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email',$email)" required />
            </div>

            @if ($rol == 'cliente')
            <div class="mt-4">
                <x-jet-label for="passport" value="{{ __('Número de Pasaporte o Identificación') }}" />
                <x-jet-input id="passport" class="block mt-1 w-full" type="text" name="passport" :value="old('passport',$passport)" required />
            </div>
            @else
            <input type="hidden" name="passport" value="{{ $passport }}" />
            @endif

            <div class="mt-4">
                <x-jet-label for="password" value="{{ __('Password') }}" />
                <x-jet-input id="password" class="block mt-1 w-full" type="password" name="password" required autocomplete="new-password" />
            </div>

            <div class="mt-4">
                <x-jet-label for="password_confirmation" value="{{ __('Confirm Password') }}" />
                <x-jet-input id="password_confirmation" class="block mt-1 w-full" type="password" name="password_confirmation" required autocomplete="new-password" />
            </div>

            @if (Laravel\Jetstream\Jetstream::hasTermsAndPrivacyPolicyFeature())
                <div class="mt-4">
                    <x-jet-label for="terms">
                        <div class="flex items-center">
                            <x-jet-checkbox name="terms" id="terms"/>

                            <div class="ml-2">
                                {!! __('I agree to the :terms_of_service and :privacy_policy', [
                                        'terms_of_service' => '<a target="_blank" href="'.route('terms.show').'" class="underline text-sm text-gray-600 hover:text-gray-900">'.__('Terms of Service').'</a>',
                                        'privacy_policy' => '<a target="_blank" href="'.route('policy.show').'" class="underline text-sm text-gray-600 hover:text-gray-900">'.__('Privacy Policy').'</a>',
                                ]) !!}
                            </div>
                        </div>
                    </x-jet-label>
                </div>
            @endif

            <div class="flex items-center justify-end mt-4">
                <a class="underline text-sm text-gray-600 hover:text-gray-900" href="{{ route('login') }}">
                    {{ __('Already registered?') }}
                </a>

                <x-jet-button class="ml-4 cfrSefar">
                    {{ __('Register') }}
                </x-jet-button>
            </div>
        </form>
    </x-jet-authentication-card>
</x-guest-layout>
