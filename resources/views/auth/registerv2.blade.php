<x-guest-layout>
    <!-- Modal de éxito -->
    <div id="successModal" class="fixed inset-0 bg-[rgba(255,255,255,0.6)] overlay-blur flex items-center justify-center hidden z-50">
        <div class="bg-white p-8 rounded-lg shadow-lg max-w-md text-center">
            <div class="mx-auto mb-4 h-16 w-16 rounded-full bg-green-100 flex items-center justify-center">
                <svg class="h-10 w-10 text-green-600" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M5 13l4 4L19 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
            <h3 class="text-xl font-bold mb-4">¡Registro exitoso!</h3>
            <p class="mb-4">
                Te has registrado exitosamente.<br>
                Hemos enviado tu contraseña a tu correo.<br>
                En breve serás redirigido a nuestra plataforma.
            </p>
            <div class="animate-pulse text-gray-500">Redirigiendo...</div>
        </div>
    </div>

    <style>
        .overlay-blur {
            background: rgba(255, 255, 255, 0.3);
            backdrop-filter: blur(6px);
            -webkit-backdrop-filter: blur(6px);
        }
        .error-text {
            color: #dc2626; /* Tailwind red-600 */
            font-size: 0.875rem; /* Tailwind text-sm */
            margin-top: 0.25rem; /* Tailwind mt-1 */
        }
    </style>

    <!-- Contenido -->
    <div class="max-w-lg mx-auto bg-white p-6 rounded-lg">
        <h2 class="text-center text-xl font-bold mb-6">
            @if(request('servicio')==="Formalizacion Anticipada Ley de Memoria Democrática" || request('servicio')==="Formalizacion Anticipada Portuguesa Sefardi")
            Solicita tu Formalización AHORA
            @else
            Inicia tu análisis genealógico
            @endif
        </h2>

        <form method="POST" action="{{ route('register.v2') }}" id="registerV2Form">
            @csrf

            {{-- Nombre / Apellido --}}
            <div class="flex gap-4">
                <div class="flex-1">
                    <label for="nombres" class="block text-sm font-medium">Nombre *</label>
                    <input type="text" name="nombres" value="{{ old('nombres') }}" class="w-full border rounded p-2" required>
                    @error('nombres')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>
                <div class="flex-1">
                    <label for="apellidos" class="block text-sm font-medium">Apellido *</label>
                    <input type="text" name="apellidos" value="{{ old('apellidos') }}" class="w-full border rounded p-2" required>
                    @error('apellidos')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- Correo / Teléfono --}}
            <div class="flex gap-4 mt-4">
                <div class="flex-1">
                    <label for="email" class="block text-sm font-medium">Correo *</label>
                    <input type="email" name="email" value="{{ old('email') }}" class="w-full border rounded p-2" required>
                    @error('email')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>
                <div class="flex-1">
                    <label for="phone" class="block text-sm font-medium">Teléfono *</label>
                    <input type="tel" name="phone" value="{{ old('phone') }}" class="w-full border rounded p-2" required>
                    @error('phone')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- Pasaporte / País --}}
            <div class="flex gap-4 mt-4">
                <div class="flex-1">
                    <label for="passport" class="block text-sm font-medium">Número de Pasaporte *</label>
                    <input type="text" name="passport" value="{{ old('passport') }}" class="w-full border rounded p-2" required>
                    @error('passport')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>
                <div class="flex-1">
                    <label for="pais_de_nacimiento" class="block text-sm font-medium">País de nacimiento *</label>
                    <input type="text" name="pais_de_nacimiento" value="{{ old('pais_de_nacimiento') }}" class="w-full border rounded p-2" required>
                    @error('pais_de_nacimiento')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- Referido --}}
            <div class="mt-4">
                <label for="referido" class="block text-sm font-medium">Referido por *</label>
                <select name="referido" class="w-full border rounded p-2" required>
                    <option value="">Selecciona</option>
                    <!-- Omitted options for brevity -->
                </select>
                @error('referido')
                    <p class="error-text">{{ $message }}</p>
                @enderror
            </div>

            {{-- Tiene antepasados españoles --}}
            <div class="mt-4 {{ request('servicio')==='Española LMD' ? '' : 'hidden' }}">
                <label for="tiene_antepasados_espanoles" class="block text-sm font-medium">¿Sabes si usted tiene uno o más antepasados Españoles? *</label>
                <select id="tiene_antepasados_espanoles" name="tiene_antepasados_espanoles" class="w-full border rounded p-2" {{ request('servicio')==='Española LMD' ? 'required' : '' }}>
                    <option value="">Selecciona</option>
                    <option value="0" {{ old('tiene_antepasados_espanoles')==='0' ? 'selected' : '' }}>No</option>
                    <option value="1" {{ old('tiene_antepasados_espanoles')==='1' ? 'selected' : '' }}>Sí</option>
                </select>
                @error('tiene_antepasados_espanoles')
                    <p class="error-text">{{ $message }}</p>
                @enderror
            </div>

            {{-- Tiene antepasados italianos --}}
            <div class="mt-4 {{ request('servicio')==='Italiana' ? '' : 'hidden' }}">
                <label for="tiene_antepasados_italianos" class="block text-sm font-medium">¿Sabes si usted tiene uno o más antepasados italianos? *</label>
                <select id="tiene_antepasados_italianos" name="tiene_antepasados_italianos" class="w-full border rounded p-2" {{ request('servicio')==='Italiana' ? 'required' : '' }}>
                    <option value="">Selecciona</option>
                    <option value="0" {{ old('tiene_antepasados_italianos')==='0' ? 'selected' : '' }}>No</option>
                    <option value="1" {{ old('tiene_antepasados_italianos')==='1' ? 'selected' : '' }}>Sí</option>
                </select>
                @error('tiene_antepasados_italianos')
                    <p class="error-text">{{ $message }}</p>
                @enderror
            </div>

            {{-- Tiene hermanos --}}
            <div class="mt-4">
                <label for="tiene_hermanos" class="block text-sm font-medium">¿Tiene hermanos realizando procesos en Sefar Universal? *</label>
                <select id="tiene_hermanos" name="tiene_hermanos" class="w-full border rounded p-2" required>
                    <option value="">Selecciona</option>
                    <option value="0" {{ old('tiene_hermanos')==='0' ? 'selected' : '' }}>No</option>
                    <option value="1" {{ old('tiene_hermanos')==='1' ? 'selected' : '' }}>Sí</option>
                </select>
                @error('tiene_hermanos')
                    <p class="error-text">{{ $message }}</p>
                @enderror
            </div>

            {{-- Campo oculto --}}
            <div class="mt-4 {{ old('tiene_hermanos')==='1' ? '' : 'hidden' }}" id="familiarContainer">
                <label for="nombre_de_familiar_realizando_procesos" class="block text-sm font-medium">Nombre del familiar que realiza procesos *</label>
                <input type="text" id="nombre_de_familiar_realizando_procesos" name="nombre_de_familiar_realizando_procesos" value="{{ old('nombre_de_familiar_realizando_procesos') }}" class="w-full border rounded p-2" {{ old('tiene_hermanos')==='1' ? 'required' : '' }}>
                @error('nombre_de_familiar_realizando_procesos')
                    <p class="error-text">{{ $message }}</p>
                @enderror
            </div>

            {{-- Checkboxes --}}
            <div class="mt-4">
                <label class="inline-flex items-center">
                    <input type="checkbox" name="acepta_comunicaciones" {{ old('acepta_comunicaciones') ? 'checked' : '' }} required>
                    <span class="ml-2 text-sm">Acepto recibir otras comunicaciones de Sefar Universal.</span>
                </label>
                @error('acepta_comunicaciones')
                    <p class="error-text">{{ $message }}</p>
                @enderror
            </div>
            <div class="mt-2">
                <label class="inline-flex items-center">
                    <input type="checkbox" name="acepta_datos" {{ old('acepta_datos') ? 'checked' : '' }} required>
                    <span class="ml-2 text-sm">Acepto permitir a Sefar Universal almacenar y procesar mis datos personales.</span>
                </label>
                @error('acepta_datos')
                    <p class="error-text">{{ $message }}</p>
                @enderror
            </div>

            {{-- Botón --}}
            <div class="mt-6 text-center">
                <button type="submit" style="color: white; border-radius: 12px;" class="py-2 px-4 btn btn-primary cfrSefar">¡Registrarme ahora!</button>
            </div>

            {{-- Campos ocultos --}}
            <input type="hidden" name="lastname" id="hiddenLastname" value="{{ old('lastname') }}">
            <input type="hidden" name="numero_de_pasaporte" id="hiddenPasaporte" value="{{ old('numero_de_pasaporte') }}">
            <input type="hidden" name="servicio" value="{{ request('servicio') }}">
            <input type="hidden" name="pay" value="{{ old('pay', 0) }}">
            <input type="hidden" name="rol" value="{{ old('rol','cliente') }}">
            <input type="hidden" name="cantidad_alzada" value="{{ request('servicio')=='Recurso de Alzada' ? 0 : old('cantidad_alzada') }}">
            <input type="hidden" name="antepasados" value="{{ old('antepasados', 0) }}">
            <input type="hidden" name="vinculo_antepasados" value="{{ old('vinculo_antepasados', 0) }}">
        </form>
    </div>

    <!-- Scripts -->
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const form = document.getElementById("registerV2Form");
            const modal = document.getElementById("successModal");

            form.addEventListener("submit", function () {
                modal.classList.remove("hidden");
            });

            const selectHermanos = document.getElementById("tiene_hermanos");
            const familiarContainer = document.getElementById("familiarContainer");
            const familiarInput = document.getElementById("nombre_de_familiar_realizando_procesos");

            selectHermanos.addEventListener("change", function () {
                if (this.value === "1") {
                    familiarContainer.classList.remove("hidden");
                    familiarInput.setAttribute("required", "required");
                } else {
                    familiarContainer.classList.add("hidden");
                    familiarInput.removeAttribute("required");
                    familiarInput.value = "";
                }
            });
        });
    </script>
</x-guest-layout>
