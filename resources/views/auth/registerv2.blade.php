<x-guest-layout>
    <!-- Modal de éxito -->
    <div id="successModal" class="fixed inset-0 bg-[rgba(255,255,255,0.6)] overlay-blur flex items-center justify-center hidden z-50">
        <div class="bg-white p-8 rounded-lg shadow-lg max-w-md text-center">
            <!-- Ícono check -->
            <div class="mx-auto mb-4 h-16 w-16 rounded-full bg-green-100 flex items-center justify-center">
                <svg class="h-10 w-10 text-green-600" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M5 13l4 4L19 7"
                          stroke="currentColor"
                          stroke-width="2"
                          stroke-linecap="round"
                          stroke-linejoin="round"/>
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
            background: rgba(255, 255, 255, 0.3);   /* semitransparente */
            backdrop-filter: blur(6px);             /* difuminado */
            -webkit-backdrop-filter: blur(6px);     /* Safari */
        }

    </style>

    <!-- Contenido -->
    <div class="max-w-lg mx-auto bg-white p-6 rounded-lg">
        <h2 class="text-center text-xl font-bold mb-6">Inicia tu análisis preliminar</h2>

        <form method="POST" action="{{ route('register.v2') }}" id="registerV2Form">
            @csrf

            {{-- Nombre / Apellido --}}
            <div class="flex gap-4">
                <div class="flex-1">
                    <label for="nombres" class="block text-sm font-medium">Nombre *</label>
                    <input type="text" name="nombres" value="{{ old('nombres') }}" class="w-full border rounded p-2" required>
                </div>
                <div class="flex-1">
                    <label for="apellidos" class="block text-sm font-medium">Apellido *</label>
                    <input type="text" name="apellidos" value="{{ old('apellidos') }}" class="w-full border rounded p-2" required>
                </div>
            </div>

            {{-- Correo / Teléfono --}}
            <div class="flex gap-4 mt-4">
                <div class="flex-1">
                    <label for="email" class="block text-sm font-medium">Correo *</label>
                    <input type="email" name="email" value="{{ old('email') }}" class="w-full border rounded p-2" required>
                </div>
                <div class="flex-1">
                    <label for="phone" class="block text-sm font-medium">Teléfono *</label>
                    <input type="tel" name="phone" value="{{ old('phone') }}" class="w-full border rounded p-2" required>
                </div>
            </div>

            {{-- Pasaporte / País --}}
            <div class="flex gap-4 mt-4">
                <div class="flex-1">
                    <label for="passport" class="block text-sm font-medium">Número de Pasaporte *</label>
                    <input type="text" name="passport" value="{{ old('passport') }}" class="w-full border rounded p-2" required>
                </div>
                <div class="flex-1">
                    <label for="pais_de_nacimiento" class="block text-sm font-medium">País de nacimiento *</label>
                    <input type="text" name="pais_de_nacimiento" value="{{ old('pais_de_nacimiento') }}" class="w-full border rounded p-2" required>
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
                    <option value="y.hernandez@sefaruniversal.eu" {{ old('referido')=='y.hernandez@sefaruniversal.eu' ? 'selected' : '' }}>Yineska Hernández</option></select>
                </select>
            </div>

            {{-- Tiene hermanos --}}
            <div class="mt-4">
                <label for="tiene_hermanos" class="block text-sm font-medium">¿Tiene hermanos realizando procesos en Sefar Universal? *</label>
                <select id="tiene_hermanos" name="tiene_hermanos" class="w-full border rounded p-2" required>
                    <option value="">Selecciona</option>
                    <option value="0" {{ old('tiene_hermanos')==='0' ? 'selected' : '' }}>No</option>
                    <option value="1" {{ old('tiene_hermanos')==='1' ? 'selected' : '' }}>Sí</option>
                </select>
            </div>

            {{-- Campo oculto --}}
            <div class="mt-4 {{ old('tiene_hermanos')==='1' ? '' : 'hidden' }}" id="familiarContainer">
                <label for="nombre_de_familiar_realizando_procesos" class="block text-sm font-medium">
                    Nombre del familiar que realiza procesos *
                </label>
                <input type="text"
                    id="nombre_de_familiar_realizando_procesos"
                    name="nombre_de_familiar_realizando_procesos"
                    value="{{ old('nombre_de_familiar_realizando_procesos') }}"
                    class="w-full border rounded p-2"
                    {{ old('tiene_hermanos')==='1' ? 'required' : '' }}>
            </div>

            {{-- Checkboxes --}}
            <div class="mt-4">
                <label class="inline-flex items-center">
                    <input type="checkbox" name="acepta_comunicaciones" {{ old('acepta_comunicaciones') ? 'checked' : '' }} required>
                    <span class="ml-2 text-sm">Acepto recibir otras comunicaciones de Sefar Universal.</span>
                </label>
            </div>
            <div class="mt-2">
                <label class="inline-flex items-center">
                    <input type="checkbox" name="acepta_datos" {{ old('acepta_datos') ? 'checked' : '' }} required>
                    <span class="ml-2 text-sm">Acepto permitir a Sefar Universal almacenar y procesar mis datos personales.</span>
                </label>
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
