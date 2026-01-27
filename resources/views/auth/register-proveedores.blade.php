<x-guest-layout>
    <x-authentication-card>
        <x-slot name="logo">
            @include('layouts.logos.logo')
        </x-slot>

        <x-validation-errors class="mb-4" />

        <form method="POST" action="{{ route('proveedor.register.store') }}">
            @csrf

            <center>
                <h1 style="font-size: 20px; margin: 5px 0px;">Registro de Coordinadores</h1>
            </center>

            <div class="mt-4">
                <x-label for="nombres" value="Nombres" />
                <x-input id="nombres" class="block mt-1 w-full" type="text" name="nombres" :value="old('nombres')" required autofocus />
            </div>

            <div class="mt-4">
                <x-label for="email" value="Correo corporativo (para iniciar sesión)" />
                <x-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required />
            </div>

            <div class="mt-4">
                <x-label for="email_2" value="Correo personal" />
                <x-input id="email_2" class="block mt-1 w-full" type="email" name="email_2" :value="old('email_2')" />
            </div>

            <div class="mt-4">
                <x-label for="phone" value="Teléfono / WhatsApp" />
                <x-input id="phone" class="block mt-1 w-full" type="text" name="phone" :value="old('phone')" required />
            </div>

            <div class="mt-4">
                <x-label for="pais_de_residencia" value="País de residencia" />
                <x-input id="pais_de_residencia" class="block mt-1 w-full" type="text" name="pais_de_residencia" :value="old('pais_de_residencia')" required />
            </div>

            <div class="mt-4">
                <x-label for="city" value="Ciudad de residencia" />
                <x-input id="city" class="block mt-1 w-full" type="text" name="city" :value="old('city')" required />
            </div>

            <div class="mt-4">
                <x-label for="address" value="Dirección" />
                <x-input id="address" class="block mt-1 w-full" type="text" name="address" :value="old('address')" required />
            </div>

            <div class="mt-4">
                <x-label for="metodo_pago_preferido" value="Método de pago preferido" />
                <select id="metodo_pago_preferido" name="metodo_pago_preferido" class="block mt-1 w-full rounded-md border-gray-300" required>
                    <option value="">Selecciona una opción</option>
                    <option value="Zelle" @selected(old('metodo_pago_preferido')=='Zelle')>Zelle</option>
                    <option value="Transferencia" @selected(old('metodo_pago_preferido')=='Transferencia')>Transferencia</option>
                    <option value="Tarjeta" @selected(old('metodo_pago_preferido')=='Tarjeta')>Tarjeta</option>
                    <option value="PayPal" @selected(old('metodo_pago_preferido')=='PayPal')>PayPal</option>
                    <option value="Otro" @selected(old('metodo_pago_preferido')=='Otro')>Otro</option>
                </select>
            </div>

            <div class="mt-4">
                <x-label for="motivo_coordinador" value="¿Por qué te interesa unirte al equipo de ventas como coordinador? (respuesta corta)" />
                <x-input id="motivo_coordinador" class="block mt-1 w-full" type="text" name="motivo_coordinador" :value="old('motivo_coordinador')" required />
            </div>

            <div class="mt-4">
                <x-label for="tiene_contactos_sociales" value="¿Cuentas con contactos que puedan adquirir servicios de Sefar Universal?" />
                <select id="tiene_contactos_sociales" name="tiene_contactos_sociales" class="block mt-1 w-full rounded-md border-gray-300" required>
                    <option value="">Selecciona una opción</option>
                    <option value="1" @selected(old('tiene_contactos_sociales')==='1')>Sí</option>
                    <option value="0" @selected(old('tiene_contactos_sociales')==='0')>No</option>
                </select>
            </div>

            <div class="mt-4">
                <x-label for="acepta_politicas_comisiones">
                    <div class="flex items-center">
                        <x-checkbox name="acepta_politicas_comisiones" id="acepta_politicas_comisiones" :checked="old('acepta_politicas_comisiones') ? true : false" />
                        <div class="ml-2">Acepto políticas de comisiones</div>
                    </div>
                </x-label>
            </div>

            <div class="mt-4">
                <x-label for="password" value="Contraseña" />
                <x-input id="password" class="block mt-1 w-full" type="password" name="password" required autocomplete="new-password" />
            </div>

            <div class="mt-4">
                <x-label for="password_confirmation" value="Confirmar contraseña" />
                <x-input id="password_confirmation" class="block mt-1 w-full" type="password" name="password_confirmation" required />
            </div>

            <div class="flex items-center justify-end mt-4">
                <a class="underline text-sm text-gray-600 hover:text-gray-900" href="{{ route('login') }}">
                    Ya estoy registrado
                </a>

                <x-button class="ml-4 cfrSefar">
                    Enviar solicitud
                </x-button>
            </div>
        </form>
    </x-authentication-card>
</x-guest-layout>
