<x-guest-layout>
    @php
        $googleRegistration = session('google_registration', []);
    @endphp
    <!-- Modal de éxito -->
    <div id="successModal" class="fixed inset-0 bg-[rgba(255,255,255,0.6)] overlay-blur flex items-center hidden justify-center z-50">
        <div class="bg-white p-8 rounded-lg shadow-lg max-w-md text-center">
            <div class="flex justify-center items-center">
                <svg class="spin h-8 w-8 text-gray-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                </svg>
            </div>
        </div>
    </div>

    <style>
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        .spin {
            animation: spin 1s linear infinite;
        }
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
        .google-auth-divider {
            display: flex;
            align-items: center;
            gap: .75rem;
            margin: 1.5rem 0;
            color: #6b7280;
            font-size: .8rem;
        }
        .google-auth-divider::before,
        .google-auth-divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #e5e7eb;
        }
        .google-auth-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .65rem;
            width: 100%;
            min-height: 2.75rem;
            padding: .65rem 1rem;
            color: #1f2937;
            background: #fff;
            border: 1px solid #d1d5db;
            border-radius: .55rem;
            font-weight: 600;
            text-decoration: none;
            box-shadow: 0 1px 2px rgba(15, 23, 42, .06);
            transition: border-color .15s ease, box-shadow .15s ease, background-color .15s ease;
        }
        .google-auth-button:hover,
        .google-auth-button:focus-visible {
            color: #111827;
            background: #f9fafb;
            border-color: #9ca3af;
            box-shadow: 0 .25rem .7rem rgba(15, 23, 42, .1);
            text-decoration: none;
        }
        .google-auth-button:focus-visible {
            outline: 3px solid rgba(37, 99, 235, .24);
            outline-offset: 2px;
        }
        .google-auth-notice {
            margin-bottom: 1rem;
            padding: .75rem 1rem;
            color: #065f46;
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            border-radius: .5rem;
            font-size: .875rem;
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

        @if(session('status'))
            <div class="google-auth-notice">{{ session('status') }}</div>
        @endif

        @error('google')
            <p class="error-text">{{ $message }}</p>
        @enderror

        @if($googleLoginEnabled ?? false)
            <a class="google-auth-button"
                data-google-login
                href="{{ route('register.v2.google.redirect', request()->only(['servicio', 'pay', 'rol', 'cantidad_alzada', 'antepasados', 'vinculo_antepasados'])) }}">
                <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <path fill="#4285F4" d="M23.49 12.27c0-.79-.07-1.55-.21-2.27H12v4.3h6.44a5.5 5.5 0 0 1-2.39 3.61v2.79h3.88c2.27-2.09 3.56-5.17 3.56-8.43Z"/>
                    <path fill="#34A853" d="M12 24c3.24 0 5.96-1.07 7.94-2.91l-3.88-2.79c-1.07.72-2.45 1.14-4.06 1.14-3.12 0-5.76-2.11-6.7-4.94H1.3v2.88A12 12 0 0 0 12 24Z"/>
                    <path fill="#FBBC05" d="M5.3 14.5A7.22 7.22 0 0 1 4.93 12c0-.87.15-1.71.37-2.5V6.62H1.3A12 12 0 0 0 0 12c0 1.94.47 3.77 1.3 5.38l4-2.88Z"/>
                    <path fill="#EA4335" d="M12 4.56c1.76 0 3.34.61 4.58 1.81l3.43-3.43C17.95 1.02 15.24 0 12 0A12 12 0 0 0 1.3 6.62l4 2.88c.94-2.83 3.58-4.94 6.7-4.94Z"/>
                </svg>
                <span>Continuar con Google</span>
            </a>
            <div class="google-auth-divider">o completa tus datos</div>
        @endif

        <form method="POST" action="{{ route('register.v2') }}" id="registerV2Form">
            @csrf

            {{-- Nombre / Apellido --}}
            <div class="flex gap-4">
                <div class="flex-1">
                    <label for="nombres" class="block text-sm font-medium">Nombre *</label>
                    <input type="text" name="nombres" value="{{ old('nombres', $googleRegistration['nombres'] ?? '') }}" class="w-full border rounded p-2" required>
                    @error('nombres')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>
                <div class="flex-1">
                    <label for="apellidos" class="block text-sm font-medium">Apellido *</label>
                    <input type="text" name="apellidos" value="{{ old('apellidos', $googleRegistration['apellidos'] ?? '') }}" class="w-full border rounded p-2" required>
                    @error('apellidos')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- Correo / Teléfono --}}
            <div class="flex gap-4 mt-4">
                <div class="flex-1">
                    <label for="email" class="block text-sm font-medium">Correo *</label>
                    <input type="email" name="email" value="{{ old('email', $googleRegistration['email'] ?? '') }}" class="w-full border rounded p-2" required>
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

            document.querySelector("[data-google-login]")?.addEventListener("click", function () {
                modal.classList.remove("hidden");
            });

        });
    </script>
</x-guest-layout>
