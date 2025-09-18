<x-guest-layout>
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
                    <option value="soporteit+familiares@sefarvzla.com" {{ old('referido')=='soporteit+familiares@sefarvzla.com' ? 'selected' : '' }}>..Amigo - Conocido o Familiares</option>
                    <option value="soporteit+buscadores@sefarvzla.com" {{ old('referido')=='soporteit+buscadores@sefarvzla.com' ? 'selected' : '' }}>..Anuncio en Buscadores</option>
                    <option value="soporteit+google@sefarvzla.com" {{ old('referido')=='soporteit+google@sefarvzla.com' ? 'selected' : '' }}>..Google</option>
                    <option value="soporteit+rrss@sefarvzla.com" {{ old('referido')=='soporteit+rrss@sefarvzla.com' ? 'selected' : '' }}>..Redes Sociales</option>
                    <option value="soporteit+otros@sefarvzla.com" {{ old('referido')=='soporteit+otros@sefarvzla.com' ? 'selected' : '' }}>..Otros</option>
                    <option value="admin.sefar@sefarvzla.com" {{ old('referido')=='admin.sefar@sefarvzla.com' ? 'selected' : '' }}>Abel Tejeda Molina</option>
                    <option value="avillasmil@sefarvzla.com" {{ old('referido')=='avillasmil@sefarvzla.com' ? 'selected' : '' }}>Adela Villasmil</option>
                    <option value="agoiticoa@sefarvzla.com" {{ old('referido')=='agoiticoa@sefarvzla.com' ? 'selected' : '' }}>Alex Goiticoa González</option>
                    <option value="a.delgado@sefarvzla.com" {{ old('referido')=='a.delgado@sefarvzla.com' ? 'selected' : '' }}>Ana María Delgado</option>
                    <option value="arosales@sefarvzla.com" {{ old('referido')=='arosales@sefarvzla.com' ? 'selected' : '' }}>Angel Rosales</option>
                    <option value="amonroy@sefarvzla.com" {{ old('referido')=='amonroy@sefarvzla.com' ? 'selected' : '' }}>Angelica Monroy Gualteros</option>
                    <option value="bnarvaez@sefarvzla.com" {{ old('referido')=='bnarvaez@sefarvzla.com' ? 'selected' : '' }}>Belsy Del Carmen Narvaez Colon</option>
                    <option value="cmolina@sefarvzla.com" {{ old('referido')=='cmolina@sefarvzla.com' ? 'selected' : '' }}>Carmen Alicia Molina Moscarella</option>
                    <option value="cguerrero@sefarvzla.com" {{ old('referido')=='cguerrero@sefarvzla.com' ? 'selected' : '' }}>Carolina Guerrero Villegas</option>
                    <option value="c.alcantara@sefarvzla.com" {{ old('referido')=='c.alcantara@sefarvzla.com' ? 'selected' : '' }}>Celeste Alcantara</option>
                    <option value="cora.diaz@sefarvzla.com" {{ old('referido')=='cora.diaz@sefarvzla.com' ? 'selected' : '' }}>Cora Diaz</option>
                    <option value="crisantoantonio@sefarvzla.com" {{ old('referido')=='crisantoantonio@sefarvzla.com' ? 'selected' : '' }}>Crisanto Bello</option>
                    <option value="dgarcia@sefarvzla.com" {{ old('referido')=='dgarcia@sefarvzla.com' ? 'selected' : '' }}>Dangmar García de Segnini</option>
                    <option value="daniela.cernik@sefaruniversal.eu" {{ old('referido')=='daniela.cernik@sefaruniversal.eu' ? 'selected' : '' }}>Daniela Cernik Vera</option>
                    <option value="dgutierrez@sefarvzla.com" {{ old('referido')=='dgutierrez@sefarvzla.com' ? 'selected' : '' }}>Dayvelis Carolina Gutiérrez Rodríguez</option>
                    <option value="gromero@sefarvzla.com" {{ old('referido')=='gromero@sefarvzla.com' ? 'selected' : '' }}>Gabriella Romero Garay</option>
                    <option value="hleon@sefaruniversal.eu" {{ old('referido')=='hleon@sefaruniversal.eu' ? 'selected' : '' }}>Hernando León</option>
                    <option value="irodriguez@sefarvzla.com" {{ old('referido')=='irodriguez@sefarvzla.com' ? 'selected' : '' }}>Ingrid Cecilia Rodriguez Valderrama</option>
                    <option value="iardila@sefarvzla.com" {{ old('referido')=='iardila@sefarvzla.com' ? 'selected' : '' }}>Ivette Ardila</option>
                    <option value="admin.presidencia@sefarvzla.com" {{ old('referido')=='admin.presidencia@sefarvzla.com' ? 'selected' : '' }}>Jose Alejandro Zuñiga</option>
                    <option value="jquero@sefaruniversal.eu" {{ old('referido')=='jquero@sefaruniversal.eu' ? 'selected' : '' }}>José Quero</option>
                    <option value="jbelisario@sefaruniversal.eu" {{ old('referido')=='jbelisario@sefaruniversal.eu' ? 'selected' : '' }}>Juan Belisario</option>
                    <option value="jcordova@sefaruniversal.eu" {{ old('referido')=='jcordova@sefaruniversal.eu' ? 'selected' : '' }}>Juan Miguel Cordova</option>
                    <option value="jlozano@sefarvzla.com" {{ old('referido')=='jlozano@sefarvzla.com' ? 'selected' : '' }}>Julibeth Del Carmen Lozano Alvarado</option>
                    <option value="j.munoz@sefaruniversal.eu" {{ old('referido')=='j.munoz@sefaruniversal.eu' ? 'selected' : '' }}>Julieth Muñoz</option>
                    <option value="katherine.agraz@sefaruniversal.eu" {{ old('referido')=='katherine.agraz@sefaruniversal.eu' ? 'selected' : '' }}>Katherine del Valle Agráz</option>
                    <option value="lauram@sefarvzla.com" {{ old('referido')=='lauram@sefarvzla.com' ? 'selected' : '' }}>Laura Muñoz Gabiria</option>
                    <option value="automatizacion@sefarvzla.com" {{ old('referido')=='automatizacion@sefarvzla.com' ? 'selected' : '' }}>Leandro Roman</option>
                    <option value="libsen.rodriguez@sefarvzla.com" {{ old('referido')=='libsen.rodriguez@sefarvzla.com' ? 'selected' : '' }}>Libsen Rodríguez</option>
                    <option value="lguzmanposso@sefarvzla.com" {{ old('referido')=='lguzmanposso@sefarvzla.com' ? 'selected' : '' }}>Lina Marcela Guzman Posso&nbsp;</option>
                    <option value="lrondon@sefaruniversal.eu" {{ old('referido')=='lrondon@sefaruniversal.eu' ? 'selected' : '' }}>Luisa Rondon</option>
                    <option value="mbriceno@sefarvzla.com" {{ old('referido')=='mbriceno@sefarvzla.com' ? 'selected' : '' }}>Maria Soledad Briceño</option>
                    <option value="m.moreno@sefarvzla.com" {{ old('referido')=='m.moreno@sefarvzla.com' ? 'selected' : '' }}>Marisol Moreno González</option>
                    <option value="mhernandez@sefarvzla.com" {{ old('referido')=='mhernandez@sefarvzla.com' ? 'selected' : '' }}>María Carolina Hernandez</option>
                    <option value="mgimenez@sefaruniversal.eu" {{ old('referido')=='mgimenez@sefaruniversal.eu' ? 'selected' : '' }}>María Giménez</option>
                    <option value="mchavez@sefarvzla.com" {{ old('referido')=='mchavez@sefarvzla.com' ? 'selected' : '' }}>María Luisa Chávez</option>
                    <option value="mlopez@sefaruniversal.eu" {{ old('referido')=='mlopez@sefaruniversal.eu' ? 'selected' : '' }}>Mauricio Lopez Cisneros</option>
                    <option value="milenacera@sefarvzla.com" {{ old('referido')=='milenacera@sefarvzla.com' ? 'selected' : '' }}>Milena Lucia Cera Avendaño</option>
                    <option value="nnavarro@sefarvzla.com" {{ old('referido')=='"nnavarro@sefarvzla.com' ? 'selected' : '' }}>Nathalie Navarro González</option>
                    <option value="odettevera@sefarvzla.com" {{ old('referido')=='odettevera@sefarvzla.com' ? 'selected' : '' }}>Odette Vera</option>
                    <option value="auxatc1@sefarvzla.com" {{ old('referido')=='auxatc1@sefarvzla.com' ? 'selected' : '' }}>Orlando Enrique Angulo Osorio</option>
                    <option value="ocastro@sefarvzla.com" {{ old('referido')=='ocastro@sefarvzla.com' ? 'selected' : '' }}>Oscar Enrique Castro Rodriguez</option>
                    <option value="osanabria@sefaruniversal.eu" {{ old('referido')=='osanabria@sefaruniversal.eu' ? 'selected' : '' }}>Oscar Sanabria Garcia</option>
                    <option value="p.urdaneta@sefaruniversal.eu" {{ old('referido')=='p.urdaneta@sefaruniversal.eu' ? 'selected' : '' }}>Paula Urdaneta</option>
                    <option value="rorozco@sefarvzla.com" {{ old('referido')=='rorozco@sefarvzla.com' ? 'selected' : '' }}>Roberto Enrique Orozco Zuleta</option>
                    <option value="sscuzzarello@sefaruniversal.eu" {{ old('referido')=='sscuzzarello@sefaruniversal.eu' ? 'selected' : '' }}>Sarah Scuzzarello</option>
                    <option value="alfredo.machado@somaconsultores.com" {{ old('referido')=='alfredo.machado@somaconsultores.com' ? 'selected' : '' }}>Soma Consultores//Alfredo Machado</option>
                    <option value="TRANSFORMANDO 360 GRADOS A.C." {{ old('referido')=='TRANSFORMANDO 360 GRADOS A.C.' ? 'selected' : '' }}>TRANSFORMANDO 360</option>
                    <option value="veronica.poletto@sefarvzla.com" {{ old('referido')=='veronica.poletto@sefarvzla.com' ? 'selected' : '' }}>Veronica Poletto</option>
                    <option value="yeinsondiaz@sefarvzla.com" {{ old('referido')=='yeinsondiaz@sefarvzla.com' ? 'selected' : '' }}>Yeinson Diaz</option>
                    <option value="y.hernandez@sefaruniversal.eu" {{ old('referido')=='y.hernandez@sefaruniversal.eu' ? 'selected' : '' }}>Yineska Hernández</option>
                    <option value="ahernandez@sefarvzla.com" {{ old('referido')=='ahernandez@sefarvzla.com' ? 'selected' : '' }}>Alexandra Hernández Gómez</option>
                    <option value="mherrera@sefarvzla.com" {{ old('referido')=='mherrera@sefarvzla.com' ? 'selected' : '' }}>Marla Herrera Sulbarán</option>
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
