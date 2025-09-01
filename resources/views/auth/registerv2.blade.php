<x-guest-layout>
    <div class="max-w-lg mx-auto bg-white p-6 rounded-lg shadow">
        <h2 class="text-center text-xl font-bold mb-6">Inicia tu análisis preliminar</h2>

        <form method="POST" action="{{ route('register.v2') }}" id="registerV2Form">
            @csrf

            {{-- Nombre / Apellido --}}
            <div class="flex gap-4">
                <div class="flex-1">
                    <label for="nombres" class="block text-sm font-medium">Nombre *</label>
                    <input type="text" id="nombres" class="w-full border rounded p-2" required>
                </div>
                <div class="flex-1">
                    <label for="apellidos" class="block text-sm font-medium">Apellido *</label>
                    <input type="text" id="apellidos" class="w-full border rounded p-2" required>
                </div>
            </div>

            {{-- Correo / Teléfono --}}
            <div class="flex gap-4 mt-4">
                <div class="flex-1">
                    <label for="email" class="block text-sm font-medium">Correo *</label>
                    <input type="email" id="email" name="email" class="w-full border rounded p-2" required>
                </div>
                <div class="flex-1">
                    <label for="phone" class="block text-sm font-medium">Teléfono *</label>
                    <input type="tel" id="phone" class="w-full border rounded p-2" required>
                </div>
            </div>

            {{-- Pasaporte / País --}}
            <div class="flex gap-4 mt-4">
                <div class="flex-1">
                    <label for="passport" class="block text-sm font-medium">Número de Pasaporte *</label>
                    <input type="text" id="passport" class="w-full border rounded p-2" required>
                </div>
                <div class="flex-1">
                    <label for="pais_de_nacimiento" class="block text-sm font-medium">País de nacimiento *</label>
                    <select id="pais_de_nacimiento" class="w-full border rounded p-2" required>
                        <option value="">Selecciona</option>
                        <option value="España">España</option>
                        <option value="Italia">Italia</option>
                        <option value="Venezuela">Venezuela</option>
                        <option value="Argentina">Argentina</option>
                        <option value="Colombia">Colombia</option>
                        {{-- TODO: lista completa --}}
                    </select>
                </div>
            </div>

            {{-- Referido --}}
            <div class="mt-4">
                <label for="referido" class="block text-sm font-medium">Referido por *</label>
                <select id="referido" class="w-full border rounded p-2" required>
                    <option value="">Selecciona</option>
                    <option value="soporteit+familiares@sefarvzla.com">..Amigo - Conocido o Familiares</option>
                    <option value="soporteit+buscadores@sefarvzla.com">..Anuncio en Buscadores</option>
                    <option value="soporteit+google@sefarvzla.com">..Google</option>
                    <option value="soporteit+rrss@sefarvzla.com">..Redes Sociales</option>
                    <option value="soporteit+otros@sefarvzla.com">..Otros</option>
                    {{-- aquí todo tu listado de referidos --}}
                </select>
            </div>

            {{-- Tiene hermanos --}}
            <div class="mt-4">
                <label for="tiene_hermanos" class="block text-sm font-medium">¿Tiene hermanos realizando procesos en Sefar Universal? *</label>
                <select id="tiene_hermanos" class="w-full border rounded p-2" required>
                    <option value="">Selecciona</option>
                    <option value="0">No</option>
                    <option value="1">Sí</option>
                </select>
            </div>

            {{-- Checkboxes --}}
            <div class="mt-4">
                <label class="inline-flex items-center">
                    <input type="checkbox" required>
                    <span class="ml-2 text-sm">Acepto recibir otras comunicaciones de Sefar Universal.</span>
                </label>
            </div>
            <div class="mt-2">
                <label class="inline-flex items-center">
                    <input type="checkbox" required>
                    <span class="ml-2 text-sm">Acepto permitir a Sefar Universal almacenar y procesar mis datos personales.</span>
                </label>
            </div>

            {{-- Botón --}}
            <div class="mt-6 text-center">
                <button type="submit" style="color: white; border-radius: 12px;" class="py-2 px-4 btn btn-primary cfrSefar">¡Registrarme ahora!</button>
            </div>

            {{-- Campos ocultos requeridos por tu backend --}}
            <input type="hidden" name="nombres" id="hiddenNombres">
            <input type="hidden" name="apellidos" id="hiddenApellidos">
            <input type="hidden" name="lastname" id="hiddenLastname">
            <input type="hidden" name="phone" id="hiddenPhone">
            <input type="hidden" name="numero_de_pasaporte" id="hiddenPasaporte">
            <input type="hidden" name="pais_de_nacimiento" id="hiddenPaisNacimiento">
            <input type="hidden" name="referido" id="hiddenReferido">
            <input type="hidden" name="tiene_hermanos" id="hiddenTieneHermanos">

            <input type="hidden" name="servicio" value="{{ request('servicio') }}">
            <input type="hidden" name="pay" value="0">
            <input type="hidden" name="rol" value="cliente">
            <input type="hidden" name="cantidad_alzada" value="{{ request('servicio')=='Recurso de Alzada' ? 0 : '' }}">
            <input type="hidden" name="antepasados" value="0">
            <input type="hidden" name="vinculo_antepasados" value="0">
        </form>
    </div>
</x-guest-layout>

<script>
document.getElementById("registerV2Form").addEventListener("submit", function() {
    document.getElementById("hiddenNombres").value   = document.getElementById("nombres").value;
    document.getElementById("hiddenApellidos").value = document.getElementById("apellidos").value;
    document.getElementById("hiddenLastname").value  = document.getElementById("apellidos").value; // DB necesita lastname
    document.getElementById("hiddenPhone").value     = document.getElementById("phone").value;
    document.getElementById("hiddenPasaporte").value = document.getElementById("passport").value;
    document.getElementById("hiddenPaisNacimiento").value = document.getElementById("pais_de_nacimiento").value;
    document.getElementById("hiddenReferido").value  = document.getElementById("referido").value;
    document.getElementById("hiddenTieneHermanos").value  = document.getElementById("tiene_hermanos").value;
});
</script>
