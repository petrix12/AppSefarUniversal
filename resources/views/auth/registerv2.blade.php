<x-guest-layout>
    <x-authentication-card>
        <x-slot name="logo">
            @include('layouts.logos.logo')
        </x-slot>

        <x-validation-errors class="mb-4" />

        <form method="POST" action="{{ route('register.v2') }}">
            @csrf

            {{-- Nombre --}}
            <div>
                <x-label for="nombres" value="Nombres"/>
                <x-input id="nombres" type="text" name="nombres_visible" required/>
            </div>

            {{-- Apellidos --}}
            <div class="mt-4">
                <x-label for="apellidos" value="Apellidos"/>
                <x-input id="apellidos" type="text" name="apellidos_visible" required/>
            </div>

            {{-- Email --}}
            <div class="mt-4">
                <x-label for="email" value="Email"/>
                <x-input id="email" type="email" name="email" required/>
            </div>

            {{-- Teléfono --}}
            <div class="mt-4">
                <x-label for="phone" value="Teléfono"/>
                <x-input id="phone" type="text" name="phone_visible"/>
            </div>

            {{-- Pasaporte --}}
            <div class="mt-4">
                <x-label for="passport" value="Número de Pasaporte"/>
                <x-input id="passport" type="text" name="passport_visible" required/>
            </div>

            {{-- País de nacimiento --}}
            <div class="mt-4">
                <x-label for="pais_de_nacimiento" value="País de nacimiento"/>
                <select name="pais_de_nacimiento_visible" id="pais_de_nacimiento" class="block mt-1 w-full" required>
                    <option value="">Seleccione...</option>
                    <option value="España">España</option>
                    <option value="Italia">Italia</option>
                    <option value="Venezuela">Venezuela</option>
                    <option value="Argentina">Argentina</option>
                    <option value="Colombia">Colombia</option>
                    {{-- TODO: lista completa --}}
                </select>
            </div>

            {{-- Referido --}}
            <div class="mt-4">
                <x-label for="referido" value="¿Cómo nos conociste?"/>
                <select name="referido_visible" id="referido" class="block mt-1 w-full">
                    <option value="">Seleccione...</option>
                    <option value="GoogleAds">Google Ads</option>
                    <option value="Instagram">Instagram</option>
                    <option value="Recomendacion">Recomendación</option>
                </select>
            </div>

            {{-- ✅ Campos ocultos (los que espera tu backend) --}}
            <input type="hidden" name="nombres" id="hiddenNombres">
            <input type="hidden" name="apellidos" id="hiddenApellidos">
            <input type="hidden" name="lastname" id="hiddenLastname">
            <input type="hidden" name="phone" id="hiddenPhone">
            <input type="hidden" name="numero_de_pasaporte" id="hiddenPasaporte">
            <input type="hidden" name="pais_de_nacimiento" id="hiddenPaisNacimiento">

            <input type="hidden" name="servicio" id="hiddenServicio" value="{{ request('servicio') }}">
            <input type="hidden" name="referido" id="hiddenReferido">

            <input type="hidden" name="pay" id="hiddenPay" value="0">
            <input type="hidden" name="rol" id="hiddenRol" value="cliente">

            <input type="hidden" name="cantidad_alzada" id="hiddenCantidadAlzada" value="{{ request('servicio')=='Recurso de Alzada' ? 0 : '' }}">
            <input type="hidden" name="antepasados" id="hiddenAntepasados" value="0">
            <input type="hidden" name="vinculo_antepasados" id="hiddenVinculoAntepasados" value="0">
            <input type="hidden" name="tiene_hermanos" id="hiddenTieneHermanos" value="0">

            {{-- Botón --}}
            <div class="flex justify-end mt-4">
                <x-button class="ml-4">Registrarme</x-button>
            </div>
        </form>
    </x-authentication-card>
</x-guest-layout>

{{-- Script para sincronizar datos visibles → ocultos --}}
<script>
document.querySelector("form").addEventListener("submit", function() {
    document.getElementById("hiddenNombres").value   = document.getElementById("nombres").value;
    document.getElementById("hiddenApellidos").value = document.getElementById("apellidos").value;
    document.getElementById("hiddenLastname").value  = document.getElementById("apellidos").value; // DB necesita ambos
    document.getElementById("hiddenPhone").value     = document.getElementById("phone").value;
    document.getElementById("hiddenPasaporte").value = document.getElementById("passport").value;
    document.getElementById("hiddenPaisNacimiento").value = document.getElementById("pais_de_nacimiento").value;
    document.getElementById("hiddenReferido").value  = document.getElementById("referido").value;
});
</script>
