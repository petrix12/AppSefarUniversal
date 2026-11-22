<div class="tab-pane fade show cos-user-profile {{ $canEditCos ? '' : 'is-readonly' }}" id="personal_data" role="tabpanel" aria-labelledby="personal-data-tab">
    <header class="cos-profile-intro">
        <div>
            <h2>Datos personales</h2>
            <p>{{ $canEditCos ? 'Identidad, contacto y registro del solicitante.' : 'Consulta tus datos. Para corregirlos, contacta al equipo.' }}</p>
        </div>
        <span class="cos-profile-mode">
            <i class="fas fa-{{ $canEditCos ? 'pen' : 'eye' }}" aria-hidden="true"></i>
            {{ $canEditCos ? 'Edición interna' : 'Solo consulta' }}
        </span>
    </header>
    <section class="cos-profile-search" data-profile-search hidden aria-label="Buscar campos del perfil">
        <label for="cos-profile-search-input">Buscar un dato</label>
        <div class="cos-profile-search__controls">
            <input type="search" id="cos-profile-search-input" data-profile-search-input
                   placeholder="Ej.: pasaporte, teléfono, nacimiento" autocomplete="off"
                   aria-controls="datos-personales-form" aria-describedby="cos-profile-search-status">
            <button type="button" class="btn btn-outline-secondary" data-profile-search-clear hidden>Limpiar búsqueda</button>
        </div>
        <p id="cos-profile-search-status" data-profile-search-status role="status" aria-live="polite" aria-atomic="true">Busca por el nombre del campo.</p>
        <p class="cos-profile-search__empty" data-profile-search-empty hidden>Prueba otra palabra o limpia la búsqueda para ver todos los datos.</p>
    </section>
    <form id="datos-personales-form">
        @csrf
        <input type="hidden" id="id" name="id" value="{{$user->id}}" />
        <fieldset class="cos-profile-fields" @disabled(!$canEditCos)>
            <legend class="cos-profile-legend">Información del perfil</legend>
            <section class="cos-profile-section" aria-labelledby="cos-profile-identity-title">
                <header class="cos-profile-section__header">
                    <span class="cos-profile-section__icon" aria-hidden="true"><i class="fas fa-id-card"></i></span>
                    <div>
                        <h3 id="cos-profile-identity-title">Identidad y datos personales</h3>
                        <p>Información básica del solicitante.</p>
                    </div>
                </header>
        <div class="cos-profile-grid">
            <div class="cos-profile-field">
                <label for="nombres" class="block text-sm font-medium text-gray-700">Nombre</label>
                <input type="text" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="nombres" name="nombres" value="{{ old('nombres', $user->nombres) }}" placeholder="Ingrese su nombre">
            </div>
            <div class="cos-profile-field">
                <label for="apellidos" class="block text-sm font-medium text-gray-700">Apellido</label>
                <input type="text" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="apellidos" name="apellidos" value="{{ old('apellidos', $user->apellidos) }}" placeholder="Ingrese su apellido">
            </div>
        </div>
        <div class="cos-profile-grid">
            @php
                $fechaNacimiento = '';
                if ($user->date_of_birth) {
                    try {
                        // Intenta primero el formato europeo
                        $fechaNacimiento = \Carbon\Carbon::createFromFormat('d/m/Y', $user->date_of_birth)->format('Y-m-d');
                    } catch (\Exception $e) {
                        try {
                            // Si falla, intenta parseo genérico (Y-m-d, etc.)
                            $fechaNacimiento = \Carbon\Carbon::parse($user->date_of_birth)->format('Y-m-d');
                        } catch (\Exception $e2) {
                            $fechaNacimiento = '';
                        }
                    }
                }
            @endphp
            <div class="cos-profile-field">
                <label for="date_of_birth" class="block text-sm font-medium text-gray-700">Fecha de Nacimiento</label>
                <input
                    type="date"
                    class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                    id="date_of_birth"
                    name="date_of_birth"
                    value="{{ old('date_of_birth', $fechaNacimiento) }}"
                    placeholder="Ingrese fecha de nacimiento"
                >
            </div>
            <div class="cos-profile-field">
                <label for="genero" class="block text-sm font-medium text-gray-700">Genero</label>
                <select
                    class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                    id="genero"
                    name="genero">

                    <option value="" {{ old('genero', $user->genero ?? '') === '' ? 'selected' : '' }}></option>
                    <option value="FEMENINO / FEMALE"
                        {{ old('genero', $user->genero ?? '') === 'FEMENINO' || old('genero', $user->genero ?? '') === 'FEMENINO / FEMALE' ? 'selected' : '' }}>
                        FEMENINO / FEMALE
                    </option>
                    <option value="MASCULINO / MALE"
                        {{ old('genero', $user->genero ?? '') === 'MASCULINO' || old('genero', $user->genero ?? '') === 'MASCULINO / MALE' ? 'selected' : '' }}>
                        MASCULINO / MALE
                    </option>
                    <option value="OTROS / OTHERS"
                        {{ in_array((string) old('genero', $user->genero ?? ''), ['OTRO', 'OTROS', 'OTROS / OTHERS', 'OTROS / OT']) ? 'selected' : '' }}>
                        OTROS / OTHERS
                    </option>
                </select>
            </div>
        </div>
        <div class="cos-profile-grid">
            <div class="cos-profile-field">
                <label for="veces_casado" class="block text-sm font-medium text-gray-700">Veces Casado</label>
                <input
                    type="number"
                    class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                    id="veces_casado"
                    name="veces_casado"
                    value="{{ old('veces_casado', $user->veces_casado ?? '') }}"
                    placeholder="Ingrese la cantidad de veces casado">
            </div>

            <div class="cos-profile-field d-none">
                <label for="edo_civil" class="block text-sm font-medium text-gray-700">Estado Civil</label>
                <select
                    class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                    id="edo_civil"
                    name="edo_civil"
                    disabled>
                    <option value="" {{ old('edo_civil', $user->edo_civil ?? '') === '' ? 'selected' : '' }}></option>
                    <option value="SOLTERO (A)" {{ old('edo_civil', $user->edo_civil ?? '') === 'SOLTERO (A)' ? 'selected' : '' }}>SOLTERO (A)</option>
                    <option value="CASADO (A)" {{ old('edo_civil', $user->edo_civil ?? '') === 'CASADO (A)' ? 'selected' : '' }}>CASADO (A)</option>
                    <option value="DIVORCIADO (A)" {{ old('edo_civil', $user->edo_civil ?? '') === 'DIVORCIADO (A)' ? 'selected' : '' }}>DIVORCIADO (A)</option>
                    <option value="VIUDO (A)" {{ old('edo_civil', $user->edo_civil ?? '') === 'VIUDO (A)' ? 'selected' : '' }}>VIUDO (A)</option>
                </select>
            </div>
        </div>
            </section>
            <section class="cos-profile-section" aria-labelledby="cos-profile-contact-title">
                <header class="cos-profile-section__header">
                    <span class="cos-profile-section__icon" aria-hidden="true"><i class="fas fa-address-book"></i></span>
                    <div>
                        <h3 id="cos-profile-contact-title">Contacto y contexto familiar</h3>
                        <p>Correo, teléfono y datos de la solicitud.</p>
                    </div>
                </header>
        <div class="cos-profile-grid">
            <div class="cos-profile-field">
                <label for="email" class="block text-sm font-medium text-gray-700">Correo</label>
                <input type="email" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="email" name="email" value="{{ old('email', $user->email) }}" placeholder="Ingrese su correo electrónico">
            </div>
            <div class="cos-profile-field">
                <label for="phone" class="block text-sm font-medium text-gray-700">Teléfono</label>
                <input type="tel" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="phone" name="phone" value="{{ old('phone', $user->phone) }}" placeholder="Ingrese su número de teléfono">
            </div>
        </div>

        <div class="cos-profile-grid">
            <div class="cos-profile-field">
                <label for="tiene_hermanos" class="block text-sm font-medium text-gray-700">¿Tiene Hermanos realizando procesos en Sefar Universal?</label>
                <select
                    class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                    id="tiene_hermanos"
                    name="tiene_hermanos">
                    <option value="" {{ old('tiene_hermanos', $user->tiene_hermanos ?? '') == '' ? 'selected' : '' }}>Seleccione una opción</option>
                    <option value="0" {{ old('tiene_hermanos', $user->tiene_hermanos ?? '') == 0 ? 'selected' : '' }}>No</option>
                    <option value="1" {{ old('tiene_hermanos', $user->tiene_hermanos ?? '') == 1 ? 'selected' : '' }}>Si</option>
                </select>
            </div>
        </div>
        @if($cosViewRoleId == 1 || $cosViewRoleId == 15 || $cosViewRoleId == 16)
        <div class="cos-profile-grid">
            <div class="cos-profile-field">
                <label for="detalle_de_la_solicitud" class="block text-sm font-medium text-gray-700">Detalles de la solicitud</label>
                <textarea
                    class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                    id="detalle_de_la_solicitud"
                    name="detalle_de_la_solicitud"
                    rows="3"
                    placeholder="Ingrese Detalles de la Solicitud"
                >{{ old('detalle_de_la_solicitud', $user->detalle_de_la_solicitud) }}</textarea>
            </div>
        </div>
        @endif
            </section>
            <section class="cos-profile-section" aria-labelledby="cos-profile-registration-title">
                <header class="cos-profile-section__header">
                    <span class="cos-profile-section__icon" aria-hidden="true"><i class="fas fa-clipboard-list"></i></span>
                    <div>
                        <h3 id="cos-profile-registration-title">Datos del registro</h3>
                        <p>Servicio principal, registro de pago y contrato.</p>
                    </div>
                </header>
        <div class="cos-profile-grid">
            <div class="cos-profile-field">
                <label class="block text-sm font-medium text-gray-700">{{ __('Payment status') }} del registro</label>
                @if($cosViewRoleId == 1)
                    <select name="pay" aria-label="{{ __('Payment status') }} del registro" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        @if ($user->pay == 0)
                            <option selected value=0>No ha pagado</option>
                        @else
                            <option value=0>No ha pagado</option>
                        @endif

                        @if ($user->pay == 1)
                            <option selected value=1>Pagó</option>
                        @else
                            <option value=1>Pagó</option>
                        @endif

                        @if ($user->pay == 2)
                            <option selected value=2>Pagó y completó información</option>
                        @else
                            <option value=2>Pagó y completó información</option>
                        @endif

                        @if ($user->pay == 3)
                            <option selected value=3>Pagó pero no se registró en Hubspot</option>
                        @else
                            <option value=3>Pagó pero no se registró en Hubspot</option>
                        @endif
                    </select>
                @else
                    <p>
                        @if ($user->pay == 0)
                            No ha pagado
                        @endif

                        @if ($user->pay == 1)
                            Pagó
                        @endif

                        @if ($user->pay == 2)
                            Pagó y completó información
                        @endif

                        @if ($user->pay == 3)
                            Pagó pero no se registró en Hubspot
                        @endif
                    </p>
                @endif
            </div>
            <div class="cos-profile-field">
                <label class="block text-sm font-medium text-gray-700">Servicio Principal</label>
                @if($cosViewRoleId == 1)
                <select name="servicio" aria-label="Servicio Principal" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                    <option></option>
                    @foreach ($servicios as $servicio)
                        <option {{ $user->servicio == $servicio->id_hubspot ? 'selected' : '' }} > {{$servicio->id_hubspot}}</option>
                    @endforeach
                </select>
                @else
                    <p>
                        {{$user->servicio}}
                    </p>
                @endif
            </div>
            <div class="cos-profile-field">
                <label class="block text-sm font-medium text-gray-700">Contrato</label>
                @if($cosViewRoleId == 1)
                    <select name="contrato" aria-label="Contrato" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        @if ($user->contrato == 0)
                            <option selected value=0>No ha firmado contrato</option>
                        @else
                            <option value=0>No ha firmado contrato</option>
                        @endif

                        @if ($user->contrato == 1)
                            <option selected value=1>Firmó Contrato</option>
                        @else
                            <option value=1>Firmó Contrato</option>
                        @endif
                    </select>
                @else
                    <p>
                        @if ($user->pay == 0)
                            No ha firmado contrato
                        @endif

                        @if ($user->pay == 1)
                            Firmó Contrato
                        @endif
                    </p>
                @endif
            </div>
        </div>

            </section>

        <fieldset disabled class="d-none">
        <h2 class="text-1xl font-extrabold tracking-tight text-gray-900 sm:text-2xl mt-4">
            <span class="ctvSefar block text-indigo-600">
            Datos del Pasaporte
            </span>
        </h2>

        <div class="cos-profile-grid">
            <div class="cos-profile-field">
                <label for="pasaporte" class="block text-sm font-medium text-gray-700">Número de Pasaporte</label>
                <input type="text" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="passport" name="passport" value="{{ old('passport', $user->passport) }}" placeholder="Ingrese su número de pasaporte">
            </div>
            <div class="cos-profile-field">
                <label for="nombre_en_pasaporte" class="block text-sm font-medium text-gray-700">Nombre en Pasaporte</label>
                <input
                    type="text"
                    class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                    id="nombre_en_pasaporte"
                    name="nombre_en_pasaporte"
                    value="{{ old('nombre_en_pasaporte', $user->nombre_en_pasaporte ?? '') }}"
                    placeholder="Ingrese el nombre tal como aparece en el pasaporte">
            </div>

            @can('genealogista')
                <div class="cos-profile-field">
                    <label for="genealogy_tree_id" class="block text-sm font-medium text-gray-700">ID secundario del árbol</label>
                    <input
                        type="text"
                        class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                        id="genealogy_tree_id"
                        name="genealogy_tree_id"
                        value="{{ old('genealogy_tree_id', $user->genealogy_tree_id) }}"
                        placeholder="IDCliente del árbol, si es distinto al pasaporte">
                    <small class="text-muted">Se consulta únicamente si no existe árbol con el pasaporte.</small>
                </div>
            @endcan

            <!-- Campo País de Expedición del Pasaporte -->
            <div class="cos-profile-field">
                <label for="pais_de_expedicion_del_pasaporte" class="block text-sm font-medium text-gray-700">País de Expedición del Pasaporte</label>
                <input
                    type="text"
                    class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                    id="pais_de_expedicion_del_pasaporte"
                    name="pais_de_expedicion_del_pasaporte"
                    value="{{ old('pais_de_expedicion_del_pasaporte', $user->pais_de_expedicion_del_pasaporte ?? '') }}"
                    placeholder="Ingrese el país de expedición del pasaporte">
            </div>

            <!-- Campo Fecha de Caducidad del Pasaporte -->
            <div class="cos-profile-field">
                <label for="fecha_de_caducidad_del_pasaporte" class="block text-sm font-medium text-gray-700">Fecha de Caducidad del Pasaporte</label>
                <input
                    type="date"
                    class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                    id="fecha_de_caducidad_del_pasaporte"
                    name="fecha_de_caducidad_del_pasaporte"
                    value="{{ old('fecha_de_caducidad_del_pasaporte', \Carbon\Carbon::parse($user->fecha_de_caducidad_del_pasaporte ?? now())->format('Y-m-d')) }}"
                    placeholder="Ingrese la fecha de caducidad del pasaporte">
            </div>
        </div>

        <h2 class="text-1xl font-extrabold tracking-tight text-gray-900 sm:text-2xl mt-4">
            <span class="ctvSefar block text-indigo-600">Direcciones</span>
        </h2>
        <div class="cos-profile-grid">
            <div class="cos-profile-field">
                <label for="pais_de_residencia" class="block text-sm font-medium text-gray-700">Pais de Residencia</label>
                <input type="text" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="pais_de_residencia" name="pais_de_residencia" value="{{ old('pais_de_residencia', $user->pais_de_residencia) }}" placeholder="Ingrese su Pais de Residencia">
            </div>
            <div class="cos-profile-field">
                <label for="city" class="block text-sm font-medium text-gray-700">Ciudad de Residencia</label>
                <input type="text" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="city" name="city" value="{{ old('city', $user->city) }}" placeholder="Ingrese su ciudad de Residencia">
            </div>
            <div class="cos-profile-field">
                <label for="anos_en_residencia_actual" class="block text-sm font-medium text-gray-700">Años en Residencia Actual</label>
                <input
                    type="text"
                    class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                    id="anos_en_residencia_actual"
                    name="anos_en_residencia_actual"
                    value="{{ old('anos_en_residencia_actual', $user->anos_en_residencia_actual ?? '') }}"
                    placeholder="Ingrese los años en la residencia actual">
            </div>
        </div>
        <div class="cos-profile-grid">
            <div class="cos-profile-field">
                <label for="address" class="block text-sm font-medium text-gray-700">Direccion actual</label>
                <input type="text" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="address" name="address" value="{{ old('address', $user->address) }}" placeholder="Ingrese su Dirección actual">
            </div>
        </div>

        <div class="cos-profile-grid">
            <div class="cos-profile-field">
                <label for="pais_de_nacimiento" class="block text-sm font-medium text-gray-700">Pais de Nacimiento</label>
                <input type="text" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="pais_de_nacimiento" name="pais_de_nacimiento" value="{{ old('pais_de_nacimiento', $user->pais_de_nacimiento) }}" placeholder="Ingrese su País de Nacimiento">
            </div>
            <div class="cos-profile-field">
                <label for="ciudad_de_nacimiento" class="block text-sm font-medium text-gray-700">Ciudad de Nacimiento</label>
                <input type="text" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="ciudad_de_nacimiento" name="ciudad_de_nacimiento" value="{{ old('ciudad_de_nacimiento', $user->ciudad_de_nacimiento) }}" placeholder="Ingrese su Ciudad de Nacimiento">
            </div>
        </div>
        <div class="cos-profile-grid">
            <div class="cos-profile-field">
                <label for="direccion_en_el_pais_de_origen" class="block text-sm font-medium text-gray-700">Dirección en el País de Origen</label>
                <input
                    type="text"
                    class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                    id="direccion_en_el_pais_de_origen"
                    name="direccion_en_el_pais_de_origen"
                    value="{{ old('direccion_en_el_pais_de_origen', $user->direccion_en_el_pais_de_origen ?? '') }}"
                    placeholder="Ingrese la dirección en el país de origen">
            </div>
        </div>
        <div class="cos-profile-grid">
            <div class="cos-profile-field">
                <label for="paises_donde_ha_residido_en_los_ultimos_5_anos" class="block text-sm font-medium text-gray-700">Países donde ha residido en los últimos 5 años</label>
                <textarea
                    class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                    id="paises_donde_ha_residido_en_los_ultimos_5_anos"
                    name="paises_donde_ha_residido_en_los_ultimos_5_anos"
                    rows="3"
                    placeholder="Ingrese los países donde ha residido en los últimos 5 años">{{ old('paises_donde_ha_residido_en_los_ultimos_5_anos', $user->paises_donde_ha_residido_en_los_ultimos_5_anos ?? '') }}</textarea>
            </div>
        </div>

        @if($cosViewRoleId == 1 || $cosViewRoleId == 15 || $cosViewRoleId == 4 || $cosViewRoleId == 17)

            <h2 class="text-1xl font-extrabold tracking-tight text-gray-900 sm:text-2xl mt-4">
                <span class="ctvSefar block text-indigo-600">Otros datos personales</span>
            </h2>
            <div class="cos-profile-grid">
                <div class="cos-profile-field">
                    <label for="conyuge_interesado_en_proceso" class="block text-sm font-medium text-gray-700">Conyuge Interesado</label>
                    <input type="hidden" name="conyuge_interesado_en_proceso" value="false">
                    <input
                        type="checkbox"
                        class="mt-1 py-2 px-3 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                        id="conyuge_interesado_en_proceso"
                        name="conyuge_interesado_en_proceso"
                       
                        value="true"
                        {{ filter_var(old('conyuge_interesado_en_proceso', $user->conyuge_interesado_en_proceso), FILTER_VALIDATE_BOOLEAN) ? 'checked' : '' }}
                    />
                </div>
                <div class="cos-profile-field">
                    <label for="nombre_completo_del_conyuge" class="block text-sm font-medium text-gray-700">Nombre de Conyuge</label>
                    <input type="text" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="nombre_completo_del_conyuge" name="nombre_completo_del_conyuge" value="{{ old('nombre_completo_del_conyuge', $user->nombre_completo_del_conyuge) }}" placeholder="Ingrese su Ciudad de Nacimiento">
                </div>
            </div>
            <div class="cos-profile-grid">
                <!-- Campo Partida de Nacimiento Simple -->
                <div class="cos-profile-field">
                    <label for="partida_de_nacimiento_simple" class="block text-sm font-medium text-gray-700">Partida de Nacimiento Simple</label>
                    <input
                        type="text"
                        class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                        id="partida_de_nacimiento_simple"
                        name="partida_de_nacimiento_simple"
                        value="{{ old('partida_de_nacimiento_simple', $user->partida_de_nacimiento_simple ?? '') }}"
                        placeholder="Ingrese información sobre la partida de nacimiento">
                </div>
            </div>
            <div class="cos-profile-grid">
                <!-- Campo Número de IBAN -->
                <div class="cos-profile-field">
                    <label for="no_de_iban" class="block text-sm font-medium text-gray-700">Número de IBAN</label>
                    <input
                        type="text"
                        class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                        id="no_de_iban"
                        name="no_de_iban"
                        value="{{ old('no_de_iban', $user->no_de_iban ?? '') }}"
                        placeholder="Ingrese el número de IBAN">
                </div>

                <!-- Campo Número de Identificación Nacional (NIF) -->
                <div class="cos-profile-field">
                    <label for="no_de_identificacion_nacional__nif_" class="block text-sm font-medium text-gray-700">Número de Identificación Nacional (NIF)</label>
                    <input
                        type="text"
                        class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                        id="no_de_identificacion_nacional__nif_"
                        name="no_de_identificacion_nacional__nif_"
                        value="{{ old('no_de_identificacion_nacional__nif_', $user->no_de_identificacion_nacional__nif_ ?? '') }}"
                        placeholder="Ingrese el número de identificación nacional (NIF)">
                </div>
            </div>

            <div class="cos-profile-grid">
                <!-- Campo Vínculo con Antepasados -->
                <div class="cos-profile-field">
                    <label class="block text-sm font-medium text-gray-700">Vínculo con Antepasados</label>
                    @php
                        $vinculosSeleccionados = explode(';', $user->vinculo_antepasados ?? '');
                    @endphp
                    <div class="mt-1">
                        <label class="flex items-center">
                            <input
                                type="checkbox"
                                name="vinculo_antepasados[]"
                                value="Padre/Madre"
                                {{ in_array('Padre/Madre', $vinculosSeleccionados) ? 'checked' : '' }}
                                class="mr-2">
                            Padre/Madre
                        </label>
                        <label class="flex items-center">
                            <input
                                type="checkbox"
                                name="vinculo_antepasados[]"
                                value="Abuelo(a)"
                                {{ in_array('Abuelo(a)', $vinculosSeleccionados) ? 'checked' : '' }}
                                class="mr-2">
                            Abuelo(a)
                        </label>
                        <label class="flex items-center">
                            <input
                                type="checkbox"
                                name="vinculo_antepasados[]"
                                value="Bisabuelo(a)"
                                {{ in_array('Bisabuelo(a)', $vinculosSeleccionados) ? 'checked' : '' }}
                                class="mr-2">
                            Bisabuelo(a)
                        </label>
                        <label class="flex items-center">
                            <input
                                type="checkbox"
                                name="vinculo_antepasados[]"
                                value="Tatarabuelo(a)"
                                {{ in_array('Tatarabuelo(a)', $vinculosSeleccionados) ? 'checked' : '' }}
                                class="mr-2">
                            Tatarabuelo(a)
                        </label>
                    </div>
                </div>
            </div>

            <h2 class="text-1xl font-extrabold tracking-tight text-gray-900 sm:text-2xl mt-4">
                <span class="ctvSefar block text-indigo-600">AIV</span>
            </h2>
            <div class="cos-profile-grid">
                <div class="cos-profile-field">
                    <label for="n6__aiv_recibido_en_espana" class="block text-sm font-medium text-gray-700">Fecha AIV recibido en España</label>
                    <input type="date" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="n6__aiv_recibido_en_espana" name="n6__aiv_recibido_en_espana" value="{{ old('n6__aiv_recibido_en_espana', $user->n6__aiv_recibido_en_espana) }}" placeholder="Fecha AIV Recibido en España">
                </div>
                <div class="cos-profile-field">
                    <label for="n2__aiv_notificacion_aprobado" class="block text-sm font-medium text-gray-700">AIV Notificación Aprobado</label>
                    <input type="text" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="n2__aiv_notificacion_aprobado" name="n2__aiv_notificacion_aprobado" value="{{ old('n2__aiv_notificacion_aprobado', $user->n2__aiv_notificacion_aprobado) }}" placeholder="Ingrese AIV Notificación Aprobado">
                </div>
            </div>

            <h2 class="text-1xl font-extrabold tracking-tight text-gray-900 sm:text-2xl mt-4">
                <span class="ctvSefar block text-indigo-600">AACS</span>
            </h2>
            <div class="cos-profile-grid">
                <div class="cos-profile-field">
                    <label for="n1__aacs_introducido_asociacion" class="block text-sm font-medium text-gray-700">AACS Introducido Asociacion</label>
                    <input type="text" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="n1__aacs_introducido_asociacion" name="n1__aacs_introducido_asociacion" value="{{ old('n1__aacs_introducido_asociacion', $user->n1__aacs_introducido_asociacion) }}" placeholder="Ingrese AACS INTRODUCIDO ASOCIACIÓN">
                </div>
            </div>
            <div class="cos-profile-grid">
                <div class="cos-profile-field">
                    <label for="n2__aacs_notificacion_aprobado" class="block text-sm font-medium text-gray-700">AACS Notificacion Aprobado</label>
                    <input type="text" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="n2__aacs_notificacion_aprobado" name="n2__aacs_notificacion_aprobado" value="{{ old('n2__aacs_notificacion_aprobado', $user->n2__aacs_notificacion_aprobado) }}" placeholder="Ingrese AACS Notificación Aprobado">
                </div>
                <div class="cos-profile-field">
                    <label for="n2__fecha_aacs_notificacion_aprobado" class="block text-sm font-medium text-gray-700">Fecha AACS Notificacion Aprobado</label>
                    <input type="date" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="n2__fecha_aacs_notificacion_aprobado" name="n2__fecha_aacs_notificacion_aprobado" value="{{ old('n2__fecha_aacs_notificacion_aprobado', $user->n2__fecha_aacs_notificacion_aprobado) }}" placeholder="Ingrese Fecha AACS Notificacion Aprobado">
                </div>
            </div>
            <div class="cos-profile-grid">
                <div class="cos-profile-field">
                    <label for="n4__aacs_retirado_asociacion" class="block text-sm font-medium text-gray-700">AACS Retirado Asociacion</label>
                    <input type="text" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="n4__aacs_retirado_asociacion" name="n4__aacs_retirado_asociacion" value="{{ old('n4__aacs_retirado_asociacion', $user->n4__aacs_retirado_asociacion) }}" placeholder="Ingrese aacs retirado asociacion">
                </div>
                <div class="cos-profile-field">
                    <label for="n6__aacs_recibido_en_espana" class="block text-sm font-medium text-gray-700">AACS Recibido en España</label>
                    <input type="text" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="n6__aacs_recibido_en_espana" name="n6__aacs_recibido_en_espana" value="{{ old('n6__aacs_recibido_en_espana', $user->n6__aacs_recibido_en_espana) }}" placeholder="AACS Recibido en España">
                </div>
            </div>

            <h2 class="text-1xl font-extrabold tracking-tight text-gray-900 sm:text-2xl mt-4">
                <span class="ctvSefar block text-indigo-600">CCSE</span>
            </h2>
            <div class="cos-profile-grid">
                <div class="cos-profile-field">
                    <label for="ccse_archivado_espana" class="block text-sm font-medium text-gray-700">CCSE Archivado España</label>
                    <input type="text" value="{{ old('ccse_archivado_espana', $user->ccse_archivado_espana) }}" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="ccse_archivado_espana" name="ccse_archivado_espana" placeholder="CCSE Archivado España">
                </div>
                <div class="cos-profile-field">
                    <label for="ccse_resultado" class="block text-sm font-medium text-gray-700">CCSE Resultado</label>
                    <input type="text" value="{{ old('ccse_resultado', $user->ccse_resultado) }}" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="ccse_resultado" name="ccse_resultado">
                </div>
            </div>

            <h2 class="text-1xl font-extrabold tracking-tight text-gray-900 sm:text-2xl mt-4">
                <span class="ctvSefar block text-indigo-600">CIF</span>
            </h2>
            <div class="cos-profile-grid">
                <div class="cos-profile-field">
                    <label for="cif" class="block text-sm font-medium text-gray-700">CIF</label>
                    <input type="text" value="{{ old('cif', $user->cif) }}" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="cif" name="cif" placeholder="CIF">
                </div>
            </div>

            <h2 class="text-1xl font-extrabold tracking-tight text-gray-900 sm:text-2xl mt-4">
                <span class="ctvSefar block text-indigo-600">Otros datos</span>
            </h2>
            <div class="cos-profile-grid">
                <div class="cos-profile-field">
                    <label for="n1__acta_notarial" class="block text-sm font-medium text-gray-700">Acta Notarial</label>
                    <input type="text" value="{{ old('n1__acta_notarial', $user->n1__acta_notarial) }}" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="n1__acta_notarial" name="n1__acta_notarial" placeholder="Ingrese Acta Notarial">
                </div>
                <div class="cos-profile-field">
                    <label for="n1__f__peticion_por_genealogia" class="block text-sm font-medium text-gray-700">Fecha Petición por Genealogía</label>
                    <input type="date" value="{{ old('n1__f__peticion_por_genealogia', $user->n1__f__peticion_por_genealogia) }}" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="n1__f__peticion_por_genealogia" name="n1__f__peticion_por_genealogia">
                </div>
                <div class="cos-profile-field">
                    <label for="n1__f__solicitado_por_genealogia" class="block text-sm font-medium text-gray-700">Fecha Solicitado por Genealogía</label>
                    <input type="date" value="{{ old('n1__f__solicitado_por_genealogia', $user->n1__f__solicitado_por_genealogia) }}" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="n1__f__solicitado_por_genealogia" name="n1__f__solicitado_por_genealogia">
                </div>
            </div>

            <div class="cos-profile-grid">
                <div class="cos-profile-field">
                    <label for="n2__f_solicitud_mayor_info" class="block text-sm font-medium text-gray-700">Fecha Solicitud Mayor Información</label>
                    <input type="date" value="{{ old('n2__f_solicitud_mayor_info', $user->n2__f_solicitud_mayor_info) }}" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="n2__f_solicitud_mayor_info" name="n2__f_solicitud_mayor_info">
                </div>
                <div class="cos-profile-field">
                    <label for="n2__f__de_solicitud_al_cliente" class="block text-sm font-medium text-gray-700">Fecha de solicitud al solicitante</label>
                    <input type="date" value="{{ old('n2__f__de_solicitud_al_cliente', $user->n2__f__de_solicitud_al_cliente) }}" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="n2__f__de_solicitud_al_cliente" name="n2__f__de_solicitud_al_cliente">
                </div>
                <div class="cos-profile-field">
                    <label for="n3__estatus_de_nacionalidad" class="block text-sm font-medium text-gray-700">Estatus de Nacionalidad</label>
                    <select class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="n3__estatus_de_nacionalidad" name="n3__estatus_de_nacionalidad">
                        <option value="" {{ old('n3__estatus_de_nacionalidad', $user->n3__estatus_de_nacionalidad ?? '') === '' ? 'selected' : '' }}></option>
                        <option value="Concedida" {{ old('n3__estatus_de_nacionalidad', $user->n3__estatus_de_nacionalidad ?? '') === 'Concedida' ? 'selected' : '' }}>Concedida</option>
                        <option value="En Tramitación" {{ old('n3__estatus_de_nacionalidad', $user->n3__estatus_de_nacionalidad ?? '') === 'En Tramitación' ? 'selected' : '' }}>En Tramitación</option>
                    </select>
                </div>
            </div>

            <div class="cos-profile-grid">
                <div class="cos-profile-field">
                    <label for="n3__f___recordatorio_filiacion" class="block text-sm font-medium text-gray-700">Fecha Recordatorio Filiación</label>
                    <input type="date" value="{{ old('n3__f___recordatorio_filiacion', $user->n3__f___recordatorio_filiacion) }}" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="n3__f___recordatorio_filiacion" name="n3__f___recordatorio_filiacion">
                </div>
                <div class="cos-profile-field">
                    <label for="n3__fcje_registro" class="block text-sm font-medium text-gray-700">FCJE Registro</label>
                    <input type="date" value="{{ old('n3__fcje_registro', $user->n3__fcje_registro) }}" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="n3__fcje_registro" name="n3__fcje_registro">
                </div>
                <div class="cos-profile-field">
                    <label for="n3__fecha_de_recordatorio" class="block text-sm font-medium text-gray-700">Fecha de Recordatorio</label>
                    <input type="date" value="{{ old('n3__fecha_de_recordatorio', $user->n3__fecha_de_recordatorio) }}" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="n3__fecha_de_recordatorio" name="n3__fecha_de_recordatorio">
                </div>
            </div>

            <div class="cos-profile-grid">
                <div class="cos-profile-field">
                    <label for="n4__aacs_retirado_asociacion" class="block text-sm font-medium text-gray-700">AACS Retirado Asociación</label>
                    <input type="text" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="n4__aacs_retirado_asociacion" name="n4__aacs_retirado_asociacion" value="{{ old('n4__aacs_retirado_asociacion', $user->n4__aacs_retirado_asociacion ?? '') }}" placeholder="Ingrese AACS Retirado Asociación">
                </div>
                <div class="cos-profile-field">
                    <label for="n4__f__entregado_genealogia" class="block text-sm font-medium text-gray-700">Fecha Entregado Genealogía</label>
                    <input type="date" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="n4__f__entregado_genealogia" name="n4__f__entregado_genealogia" value="{{ old('n4__f__entregado_genealogia', $user->n4__f__entregado_genealogia ?? '') }}">
                </div>
                <div class="cos-profile-field">
                    <label for="n4__f__enviada_a_genealogia" class="block text-sm font-medium text-gray-700">Fecha Enviada a Genealogía</label>
                    <input type="date" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="n4__f__enviada_a_genealogia" name="n4__f__enviada_a_genealogia" value="{{ old('n4__f__enviada_a_genealogia', $user->n4__f__enviada_a_genealogia ?? '') }}">
                </div>
            </div>

            <div class="cos-profile-grid">
                <div class="cos-profile-field">
                    <label for="n4__fcje_certifi__descargado" class="block text-sm font-medium text-gray-700">FCJE Certificado Descargado</label>
                    <input type="date" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="n4__fcje_certifi__descargado" name="n4__fcje_certifi__descargado" value="{{ old('n4__fcje_certifi__descargado', $user->n4__fcje_certifi__descargado ?? '') }}">
                </div>
                <div class="cos-profile-field">
                    <label for="n4__otros_nombres" class="block text-sm font-medium text-gray-700">Otros Nombres</label>
                    <input type="text" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="n4__otros_nombres" name="n4__otros_nombres" value="{{ old('n4__otros_nombres', $user->n4__otros_nombres ?? '') }}" placeholder="Ingrese Otros Nombres">
                </div>
                <div class="cos-profile-field">
                    <label for="n5__fecha_de_registro" class="block text-sm font-medium text-gray-700">Fecha de Registro</label>
                    <input type="date" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="n5__fecha_de_registro" name="n5__fecha_de_registro" value="{{ old('n5__fecha_de_registro', $user->n5__fecha_de_registro ?? '') }}">
                </div>
            </div>


            <div class="cos-profile-grid">
                <div class="cos-profile-field">
                    <label for="estado_de_datos_y_documentos_de_los_antepasados" class="block text-sm font-medium text-gray-700">Estado de datos y documentos de antepasados</label>
                    <select class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="estado_de_datos_y_documentos_de_los_antepasados" name="estado_de_datos_y_documentos_de_los_antepasados">
                        <option value="" {{ old('estado_de_datos_y_documentos_de_los_antepasados', $user->estado_de_datos_y_documentos_de_los_antepasados ?? '') === '' ? 'selected' : '' }}></option>
                        <option value="Conoce los datos y tiene los documentos" {{ old('estado_de_datos_y_documentos_de_los_antepasados', $user->estado_de_datos_y_documentos_de_los_antepasados ?? '') === 'Conoce los datos y tiene los documentos' ? 'selected' : '' }}>Conoce los datos y tiene los documentos</option>
                        <option value="Conoce los datos, pero no tiene los documentos" {{ old('estado_de_datos_y_documentos_de_los_antepasados', $user->estado_de_datos_y_documentos_de_los_antepasados ?? '') === 'Conoce los datos, pero no tiene los documentos' ? 'selected' : '' }}>Conoce los datos, pero no tiene los documentos</option>
                        <option value="No conoce los datos" {{ old('estado_de_datos_y_documentos_de_los_antepasados', $user->estado_de_datos_y_documentos_de_los_antepasados ?? '') === 'No conoce los datos' ? 'selected' : '' }}>No conoce los datos</option>
                    </select>
                </div>
            </div>

        @endif

        </fieldset>

        @include('crud.users.partials.teamleader-custom-fields', [
            'user' => $user,
            'teamleaderProfileCustomValues' => $teamleaderProfileCustomValues ?? [],
        ])

        </fieldset>

        @if($canEditCos)
            <div class="cos-save-bar">
                <span class="text-muted small"><i class="fas fa-history mr-1"></i> Cada cambio queda registrado con el usuario que lo realizó.</span>
                <button type="button" id="guardar-datos" class="cfrSefar btn btn-primary px-4"><i class="fas fa-save mr-1"></i> Guardar cambios</button>
            </div>
        @endif
    </form>
</div>
