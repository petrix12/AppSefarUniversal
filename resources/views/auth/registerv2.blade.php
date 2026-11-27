<x-guest-layout>
    @php
        $googleRegistration = $googleRegistration ?? [];
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

        @if($registrationService ?? null)
            <div class="google-auth-notice">
                <p class="mb-1">Registro · {{ $registrationService->nombre }}</p>
                @include('services.partials.price', ['servicio' => $registrationService])
            </div>
        @endif

        @if(session('status'))
            <div class="google-auth-notice">{{ session('status') }}</div>
        @endif

        @error('google')
            <p class="error-text">{{ $message }}</p>
        @enderror

        @if($googleRegistration)
            <div class="google-auth-notice">
                Google verificado: <strong>{{ $googleRegistration['email'] }}</strong>.
                Completa tus datos para finalizar el registro.
                <a href="{{ route('register.v2.google.cancel', request()->only(['servicio', 'cantidad_alzada', 'antepasados', 'vinculo_antepasados'])) }}" class="block mt-2 underline">Usar otro correo</a>
            </div>
        @elseif($googleLoginEnabled ?? false)
            <x-google-auth-button data-google-login :href="route('register.v2.google.redirect', request()->only(['servicio', 'cantidad_alzada', 'antepasados', 'vinculo_antepasados']))" />
            <div class="sefar-google-divider">o completa tus datos</div>
        @endif

        <form method="POST" action="{{ route('register.v2') }}" id="registerV2Form">
            @csrf
            @if($googleRegistration)<input type="hidden" name="google_registration" value="1">@endif
            @unless(request()->filled('servicio'))
                <div class="mb-4">
                    <label for="registration-service" class="block text-sm font-medium">Servicio que deseas contratar *</label>
                    <select id="registration-service" name="servicio" class="w-full border rounded p-2" required>
                        <option value="">Selecciona un servicio</option>
                        @foreach($registrationServices ?? [] as $service)
                            <option value="{{ $service->id_hubspot }}" @selected(old('servicio') === $service->id_hubspot)>{{ $service->nombre }} · {{ format_money($service->currentPrice(), 2) }} EUR</option>
                        @endforeach
                    </select>
                    @error('servicio')<p class="error-text">{{ $message }}</p>@enderror
                </div>
            @endunless

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
                    <input id="email" type="email" name="email" value="{{ $googleRegistration['email'] ?? old('email', '') }}" class="w-full border rounded p-2" @readonly($googleRegistration) required>
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
            <div class="mt-4 {{ request('servicio')==='Española LMD' ? '' : 'hidden' }}" data-service-question="Española LMD">
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
            <div class="mt-4 {{ request('servicio')==='Italiana' ? '' : 'hidden' }}" data-service-question="Italiana">
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
            @if(request()->filled('servicio'))<input type="hidden" name="servicio" value="{{ request('servicio') }}">@endif
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
            const service = form.querySelector('[name="servicio"]');
            const showServiceQuestions = () => {
                form.querySelectorAll('[data-service-question]').forEach(question => {
                    const visible = question.dataset.serviceQuestion === service.value;
                    question.classList.toggle('hidden', !visible);
                    question.querySelector('select').required = visible;
                });
            };
            service.addEventListener('change', showServiceQuestions);
            showServiceQuestions();
            form.addEventListener("submit", function () {
                modal.classList.remove("hidden");
            });

            document.querySelector("[data-google-login]")?.addEventListener("click", function () {
                modal.classList.remove("hidden");
            });

        });
    </script>
</x-guest-layout>
