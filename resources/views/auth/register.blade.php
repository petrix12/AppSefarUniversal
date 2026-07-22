@php
    $selectedServicio = old('servicio', request('servicio'));
    $selectedReferido = old('referido', request('referido'));
    $defaultNombre = old('nombres', request('firstname'));
    $defaultApellido = old('apellidos', request('lastname'));
    $defaultEmail = old('email', request('email'));
    $defaultPhone = old('phone', request('phone'));
    $defaultPassport = old('passport', request('numero_de_pasaporte'));
    $defaultPais = old('pais_de_nacimiento', request('pais_de_nacimiento'));
    $defaultAlzada = old('cantidad_alzada', request('cantidad_alzada', 1));
    $alertasPayload = $alertas ?? collect();
    $serviciosDisponibles = collect($servicios ?? []);
    $normalizarServicio = function ($servicio) {
        $texto = trim(($servicio->categoria ?? '') . ' ' . ($servicio->nombre ?? '') . ' ' . ($servicio->id_hubspot ?? ''));
        $metadata = $servicio->metadata ?? [];

        if (is_string($metadata)) {
            $decoded = json_decode($metadata, true);
            $metadata = is_array($decoded) ? $decoded : [];
        }

        if (is_array($metadata)) {
            $texto .= ' ' . ($metadata['public_title'] ?? '');
            $texto .= ' ' . implode(' ', $metadata['aliases'] ?? []);
        }
        $texto = strtr($texto, [
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N',
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        ]);

        return strtolower(\Illuminate\Support\Str::ascii($texto));
    };
    $metadataServicio = function ($servicio) {
        $metadata = $servicio->metadata ?? [];

        if (is_string($metadata)) {
            $decoded = json_decode($metadata, true);
            $metadata = is_array($decoded) ? $decoded : [];
        }

        return is_array($metadata) ? $metadata : [];
    };
    $contieneServicio = function ($servicio, array $patrones) use ($normalizarServicio) {
        $texto = $normalizarServicio($servicio);

        foreach ($patrones as $patron) {
            if (str_contains($texto, $patron)) {
                return true;
            }
        }

        return false;
    };
    $esServicioEspanol = fn ($servicio) => $contieneServicio($servicio, [
        'espana', 'espanol', 'espanola', 'carta de naturaleza',
    ]);
    $esServicioPortugues = fn ($servicio) => $contieneServicio($servicio, [
        'portugal', 'portugues', 'portuguesa',
    ]);
    $esServicioItaliano = fn ($servicio) => $contieneServicio($servicio, [
        'italia', 'italian', 'italiana',
    ]);
    $serviciosAgrupados = collect([
        'Nacionalidad Espanola' => $serviciosDisponibles->filter($esServicioEspanol)->values(),
        'Nacionalidad Portuguesa' => $serviciosDisponibles->filter($esServicioPortugues)->values(),
        'Nacionalidad Italiana' => $serviciosDisponibles->filter($esServicioItaliano)->values(),
        'Otros servicios' => $serviciosDisponibles->reject(fn ($servicio) =>
            $esServicioEspanol($servicio) || $esServicioPortugues($servicio) || $esServicioItaliano($servicio)
        )->values(),
    ])->filter(fn ($grupo) => $grupo->isNotEmpty());
    $grupoRegistroMeta = [
        'Nacionalidad Espanola' => [
            'label' => 'Espana',
            'title' => 'Nacionalidad espanola',
            'summary' => 'Rutas por origen sefardi, carta de naturaleza, subsanacion, conyuge y familiares.',
            'accent' => 'spain',
            'flag' => 'spain',
        ],
        'Nacionalidad Portuguesa' => [
            'label' => 'Portugal',
            'title' => 'Nacionalidad portuguesa',
            'summary' => 'Rutas por origen sefardi, formalizacion, subsanaciones y familiares.',
            'accent' => 'portugal',
            'flag' => 'portugal',
        ],
        'Nacionalidad Italiana' => [
            'label' => 'Italia',
            'title' => 'Nacionalidad italiana',
            'summary' => 'Analisis de ascendencia, validacion de linaje y preparacion documental italiana.',
            'accent' => 'italy',
            'flag' => 'italy',
        ],
    ];
    $serviciosNacionalidadInicio = collect([
        'Nacionalidad Italiana' => $serviciosAgrupados->get('Nacionalidad Italiana', collect()),
        'Nacionalidad Espanola' => $serviciosAgrupados->get('Nacionalidad Espanola', collect()),
        'Nacionalidad Portuguesa' => $serviciosAgrupados->get('Nacionalidad Portuguesa', collect()),
    ]);
@endphp

<x-guest-layout>
    <link rel="stylesheet" href="{{ asset('css/register-checkout.css') }}?v={{ filemtime(public_path('css/register-checkout.css')) }}">
    <script src="//cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://js.stripe.com/v3/"></script>
    <script src="https://cdn.jsdelivr.net/npm/three@0.160.0/build/three.min.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/gsap.min.js" defer></script>

    <main
        class="su-register"
        data-register-checkout
        data-current-step="nacionalidades"
        data-prepare-url="{{ route('register.checkout.prepare') }}"
        data-summary-url="{{ route('register.checkout.summary') }}"
        data-coupon-url="{{ route('revisarcupon') }}"
        data-payment-url="{{ route('procesar-pago-stripe') }}"
        data-thanks-url="{{ route('gracias') }}"
        data-getinfo-url="{{ route('clientes.getinfo') }}"
        data-login-url="{{ route('login') }}"
        data-csrf="{{ csrf_token() }}"
        data-stripe-default-key="{{ env('STRIPE_KEY') }}"
        data-stripe-port-key="{{ env('STRIPE_KEY_PORT') }}"
    >
        <div id="registerConstellationScene" class="su-register__scene" aria-hidden="true"></div>
        <div id="ajaxload" class="su-register__loader" hidden>
            <div class="su-register__spinner"></div>
            <p>Procesando...</p>
        </div>

        <script id="registerAlertas" type="application/json">@json($alertasPayload)</script>
        <div id="registerAlertContainer" class="su-register__alerts"></div>

        <section class="su-register__header" data-animate-panel>
            <a href="{{ url('/') }}" class="su-register__brand" aria-label="Sefar Universal">
                <img src="/vendor/adminlte/dist/img/LogoSefar.png" alt="Sefar Universal">
            </a>
            <div>
                <p class="su-register__eyebrow">Registro guiado y pago seguro</p>
                <h1>Selecciona la nacionalidad que deseas</h1>
                <p class="su-register__intro">
                    Te acompanamos paso a paso para elegir el servicio correcto, abrir tu registro y pagar de forma segura.
                </p>
            </div>
        </section>

        <form id="registerCheckoutForm" class="su-register__grid su-register__grid--wizard" novalidate>
            @csrf
            <input type="hidden" name="rol" value="cliente">
            <input type="hidden" name="pay" value="0">

            <div class="su-register__main">
                <nav class="su-wizard-progress" aria-label="Progreso del registro" data-animate-panel>
                    <span class="is-active" data-wizard-progress="nacionalidades">Nacionalidad</span>
                    <span data-wizard-progress="bienvenida">Bienvenida</span>
                    <span data-wizard-progress="cliente">Datos</span>
                    <span data-wizard-progress="servicio">Servicio</span>
                    <span data-wizard-progress="pago">Pago</span>
                    <span data-wizard-progress="confirmacion">Confirmacion</span>
                </nav>

                <div class="su-wizard-stage" data-wizard-stage>
                    <section class="su-panel su-wizard-step is-active" data-wizard-step="nacionalidades" data-animate-panel>
                        <div class="su-nationality-intro">
                            <div>
                                <h2>Selecciona la nacionalidad que deseas</h2>
                                <p>
                                    Elige una opcion para iniciar tu registro con el servicio principal correspondiente.
                                </p>
                            </div>
                        </div>

                        <div class="su-nationality-grid">
                            @foreach($serviciosNacionalidadInicio as $grupo => $serviciosDelGrupo)
                                @php
                                    $meta = $grupoRegistroMeta[$grupo];
                                @endphp
                                <article class="su-nationality-card su-nationality-card--{{ $meta['accent'] }}">
                                    <div class="su-nationality-flag su-nationality-flag--{{ $meta['flag'] }}" aria-hidden="true">
                                        <span></span>
                                        <span></span>
                                        <span></span>
                                    </div>
                                    <div class="su-nationality-card__head">
                                        <h3>{{ $meta['title'] }}</h3>
                                        <p>{{ $meta['summary'] }}</p>
                                    </div>

                                    <div class="su-nationality-services">
                                        @forelse($serviciosDelGrupo as $servicio)
                                            @php
                                                $serviceMeta = $metadataServicio($servicio);
                                                $serviceTitle = $serviceMeta['public_title'] ?? $servicio->nombre;
                                                $servicePitch = $serviceMeta['pitch'] ?? null;
                                            @endphp
                                            <button
                                                type="button"
                                                class="su-service-choice"
                                                data-service-pick="{{ $servicio->id_hubspot }}"
                                                data-service-pick-continue="1"
                                            >
                                                <strong>{{ $serviceTitle }}</strong>
                                                @if($servicePitch)
                                                    <em>{{ $servicePitch }}</em>
                                                @endif
                                                @if($servicio->precio)
                                                    <span class="su-service-choice__price">{{ number_format($servicio->precio, 0, ',', '.') }} {{ $servicio->moneda ?? 'EUR' }}</span>
                                                @else
                                                    <span class="su-service-choice__price">Importe a confirmar</span>
                                                @endif
                                            </button>
                                        @empty
                                            <button type="button" class="su-service-choice su-service-choice--help" data-open-contact-modal>
                                                <span>Necesito orientacion sobre esta nacionalidad</span>
                                                <small>Un asesor puede ayudarte a elegir el servicio correcto.</small>
                                            </button>
                                        @endforelse
                                    </div>
                                </article>
                            @endforeach
                        </div>

                        <div class="su-contact-strip su-contact-strip--center">
                            <strong>Tienes dudas con el proceso?</strong>
                            <span>Nosotros podemos ayudarte.</span>
                            <button type="button" class="su-secondary-btn" data-open-contact-modal>Solicitar orientacion</button>
                        </div>
                    </section>

                    <section class="su-panel su-wizard-step" data-wizard-step="bienvenida" hidden>
                        <div class="su-welcome">
                            <div>
                                <span class="su-step">2</span>
                                <p class="su-panel__kicker">Inicio del registro</p>
                                <h2>Un proceso claro desde el primer minuto</h2>
                                <p>
                                    Este registro nos permite crear tu usuario, identificar el servicio inicial y dejar listo el pago del registro.
                                    Luego continuas con el formulario de informacion de tu caso, sin repetir lo que ya completaste aqui.
                                </p>
                            </div>

                            <div class="su-welcome__proof">
                                <strong>Que haremos ahora</strong>
                                <span>Confirmar tus datos principales.</span>
                                <span>Seleccionar el servicio de registro.</span>
                                <span>Aplicar cupon o procesar pago seguro.</span>
                                <span>Continuar con el siguiente formulario sin repetir informacion.</span>
                                <span>Si recargas la pagina, conservamos tu avance en este navegador.</span>
                            </div>
                        </div>

                        <div class="su-wizard-actions">
                            <button type="button" class="su-secondary-btn" data-wizard-prev>Volver</button>
                            <button type="button" class="su-primary-btn su-primary-btn--inline" data-wizard-next>Comenzar registro</button>
                        </div>
                    </section>

                    <section class="su-panel su-wizard-step" data-wizard-step="cliente" hidden>
                        <div class="su-panel__head">
                            <span class="su-step">3</span>
                            <div>
                                <h2>Datos del cliente</h2>
                                <p>Usaremos esta informacion para crear tu usuario y abrir tu proceso.</p>
                            </div>
                        </div>

                        <div class="su-step-layout">
                            <div class="su-step-layout__form">
                                <div class="su-form-grid">
                                    <label class="su-field">
                                        <span>Nombre</span>
                                        <input data-registration-field data-client-field required type="text" name="nombres" value="{{ $defaultNombre }}" autocomplete="given-name">
                                    </label>

                                    <label class="su-field">
                                        <span>Apellido</span>
                                        <input data-registration-field data-client-field required type="text" name="apellidos" value="{{ $defaultApellido }}" autocomplete="family-name">
                                    </label>

                                    <label class="su-field">
                                        <span>Correo electronico</span>
                                        <input data-registration-field data-client-field required type="email" name="email" value="{{ $defaultEmail }}" autocomplete="email">
                                    </label>

                                    <label class="su-field">
                                        <span>Telefono</span>
                                        <input data-registration-field data-client-field type="tel" name="phone" value="{{ $defaultPhone }}" autocomplete="tel">
                                    </label>

                                    <label class="su-field">
                                        <span>Pasaporte o identificacion</span>
                                        <input data-registration-field data-client-field required minlength="5" type="text" name="passport" value="{{ $defaultPassport }}" autocomplete="off">
                                    </label>

                                    <label class="su-field">
                                        <span>Pais de nacimiento</span>
                                        <input data-registration-field data-client-field required type="text" name="pais_de_nacimiento" value="{{ $defaultPais }}" autocomplete="country-name">
                                    </label>
                                </div>
                            </div>

                            <aside class="su-step-guide">
                                <p class="su-panel__kicker">Por que lo pedimos</p>
                                <h3>Datos minimos para identificarte sin friccion</h3>
                                <p>
                                    Estos campos ayudan a evitar duplicados, asociar correctamente tu compra y preparar el siguiente formulario.
                                    No pedimos documentos aqui; eso ocurre mas adelante, cuando ya tengas tu registro creado.
                                </p>
                                <div class="su-guide-list">
                                    <span>Tu correo sera tu acceso principal.</span>
                                    <span>El pasaporte o identificacion evita confusiones entre casos familiares.</span>
                                    <span>El telefono permite contactarte si hay una incidencia de pago.</span>
                                </div>
                            </aside>
                        </div>

                        <div class="su-wizard-actions">
                            <button type="button" class="su-secondary-btn" data-wizard-prev>Volver</button>
                            <button type="button" class="su-primary-btn su-primary-btn--inline" data-wizard-next>Continuar</button>
                        </div>
                    </section>

                    <section class="su-panel su-wizard-step" data-wizard-step="servicio" hidden>
                        <div class="su-panel__head">
                            <span class="su-step">4</span>
                            <div>
                                <h2>Elige tu servicio</h2>
                                <p>Mostramos primero los servicios principales de nacionalidad para que puedas escoger con facilidad.</p>
                            </div>
                        </div>

                        <div class="su-step-layout">
                            <div class="su-step-layout__form">
                                <div class="su-form-grid">
                                    <label class="su-field su-field--wide">
                                        <span>Servicio principal</span>
                                        <select data-registration-field data-service-field required name="servicio" id="servicioSelect">
                                            <option value="">Selecciona un servicio</option>
                                            @foreach($serviciosAgrupados as $grupo => $serviciosDelGrupo)
                                                <optgroup label="{{ $grupo }}">
                                                    @foreach($serviciosDelGrupo as $servicio)
                                                        @php
                                                            $serviceMeta = $metadataServicio($servicio);
                                                            $serviceTitle = $serviceMeta['public_title'] ?? $servicio->nombre;
                                                        @endphp
                                                        <option
                                                            value="{{ $servicio->id_hubspot }}"
                                                            data-name="{{ $serviceTitle }}"
                                                            data-price="{{ $servicio->precio }}"
                                                            data-currency="{{ $servicio->moneda ?? 'EUR' }}"
                                                            data-category="{{ $servicio->categoria }}"
                                                            data-pitch="{{ $serviceMeta['pitch'] ?? '' }}"
                                                            data-best-for="{{ $serviceMeta['best_for'] ?? '' }}"
                                                            data-proof="{{ $serviceMeta['proof'] ?? '' }}"
                                                            data-landing-url="{{ $serviceMeta['landing_url'] ?? '' }}"
                                                            {{ $selectedServicio === $servicio->id_hubspot ? 'selected' : '' }}
                                                        >
                                                            {{ $serviceTitle }} @if($servicio->precio) - {{ number_format($servicio->precio, 0, ',', '.') }} {{ $servicio->moneda ?? 'EUR' }} @endif
                                                        </option>
                                                    @endforeach
                                                </optgroup>
                                            @endforeach
                                        </select>
                                        <small>Si tienes dudas, elige el servicio que mas se parece a tu caso y nuestro equipo lo revisara.</small>
                                    </label>

                                    <div class="su-service-insight su-field--wide" data-service-insight hidden>
                                        <p class="su-panel__kicker">Por que esta via</p>
                                        <h3 data-service-insight-title></h3>
                                        <p data-service-insight-pitch></p>
                                        <div class="su-service-insight__details">
                                            <span data-service-insight-best></span>
                                            <span data-service-insight-proof></span>
                                        </div>
                                        <a href="#" target="_blank" rel="noopener" data-service-insight-link>Ver explicacion completa</a>
                                    </div>

                                    <label class="su-field">
                                        <span>Referido por</span>
                                        <select data-registration-field data-service-field name="referido">
                                            <option value="">Selecciona una opcion</option>
                                            <option value="soporteit+familiares@sefarvzla.com" {{ $selectedReferido === 'soporteit+familiares@sefarvzla.com' ? 'selected' : '' }}>Amigo, conocido o familiar</option>
                                            <option value="soporteit+buscadores@sefarvzla.com" {{ $selectedReferido === 'soporteit+buscadores@sefarvzla.com' ? 'selected' : '' }}>Anuncio en buscadores</option>
                                            <option value="soporteit+google@sefarvzla.com" {{ $selectedReferido === 'soporteit+google@sefarvzla.com' ? 'selected' : '' }}>Google</option>
                                            <option value="soporteit+rrss@sefarvzla.com" {{ $selectedReferido === 'soporteit+rrss@sefarvzla.com' ? 'selected' : '' }}>Redes sociales</option>
                                            <option value="soporteit+otros@sefarvzla.com" {{ $selectedReferido === 'soporteit+otros@sefarvzla.com' ? 'selected' : '' }}>Otros</option>
                                        </select>
                                    </label>

                                    <label class="su-field" data-service-conditional="alzada">
                                        <span>Cantidad para recurso de alzada</span>
                                        <input data-registration-field data-service-field type="number" min="0" max="50" name="cantidad_alzada" value="{{ $defaultAlzada }}">
                                    </label>

                                    <label class="su-field" data-service-conditional="nationality">
                                        <span>Antepasados conocidos</span>
                                        <select data-registration-field data-service-field name="antepasados">
                                            <option value="0" {{ old('antepasados', 0) == 0 ? 'selected' : '' }}>No confirmado</option>
                                            <option value="1" {{ old('antepasados') == 1 ? 'selected' : '' }}>Si, antepasado identificado</option>
                                            <option value="2" {{ old('antepasados') == 2 ? 'selected' : '' }}>Requiere validacion</option>
                                        </select>
                                    </label>

                                    <label class="su-field" data-service-conditional="nationality">
                                        <span>Vinculo con antepasados</span>
                                        <select data-registration-field data-service-field name="vinculo_antepasados">
                                            <option value="0" {{ old('vinculo_antepasados', 0) == 0 ? 'selected' : '' }}>Por definir</option>
                                            <option value="1" {{ old('vinculo_antepasados') == 1 ? 'selected' : '' }}>Linea paterna</option>
                                            <option value="2" {{ old('vinculo_antepasados') == 2 ? 'selected' : '' }}>Linea materna</option>
                                            <option value="3" {{ old('vinculo_antepasados') == 3 ? 'selected' : '' }}>Ambas lineas</option>
                                        </select>
                                    </label>

                                    <label class="su-field" data-service-conditional="nationality">
                                        <span>Estado de datos y documentos</span>
                                        <select data-registration-field data-service-field name="estado_de_datos_y_documentos_de_los_antepasados">
                                            <option value="">Selecciona una opcion</option>
                                            <option value="No tengo datos suficientes" {{ old('estado_de_datos_y_documentos_de_los_antepasados') === 'No tengo datos suficientes' ? 'selected' : '' }}>No tengo datos suficientes</option>
                                            <option value="Tengo datos parciales" {{ old('estado_de_datos_y_documentos_de_los_antepasados') === 'Tengo datos parciales' ? 'selected' : '' }}>Tengo datos parciales</option>
                                            <option value="Tengo documentos" {{ old('estado_de_datos_y_documentos_de_los_antepasados') === 'Tengo documentos' ? 'selected' : '' }}>Tengo documentos</option>
                                        </select>
                                    </label>

                                    <label class="su-field" data-service-conditional="spanish">
                                        <span>Antepasados espanoles</span>
                                        <select data-registration-field data-service-field name="tiene_antepasados_espanoles">
                                            <option value="">No aplica o no se</option>
                                            <option value="0" {{ old('tiene_antepasados_espanoles') === '0' ? 'selected' : '' }}>No</option>
                                            <option value="1" {{ old('tiene_antepasados_espanoles') === '1' ? 'selected' : '' }}>Si</option>
                                        </select>
                                    </label>

                                    <label class="su-field" data-service-conditional="italian">
                                        <span>Antepasados italianos</span>
                                        <select data-registration-field data-service-field name="tiene_antepasados_italianos">
                                            <option value="">No aplica o no se</option>
                                            <option value="0" {{ old('tiene_antepasados_italianos') === '0' ? 'selected' : '' }}>No</option>
                                            <option value="1" {{ old('tiene_antepasados_italianos') === '1' ? 'selected' : '' }}>Si</option>
                                        </select>
                                    </label>

                                    <label class="su-field">
                                        <span>Familiares en proceso</span>
                                        <select data-registration-field data-service-field required id="tieneHermanos" name="tiene_hermanos">
                                            <option value="">Selecciona</option>
                                            <option value="0" {{ old('tiene_hermanos') === '0' ? 'selected' : '' }}>No</option>
                                            <option value="1" {{ old('tiene_hermanos') === '1' ? 'selected' : '' }}>Si</option>
                                        </select>
                                    </label>

                                    <label class="su-field {{ old('tiene_hermanos') === '1' ? '' : 'is-hidden' }}" id="familiarField">
                                        <span>Nombre del familiar</span>
                                        <input data-registration-field data-service-field type="text" id="nombreFamiliar" name="nombre_de_familiar_realizando_procesos" value="{{ old('nombre_de_familiar_realizando_procesos') }}">
                                    </label>
                                </div>
                            </div>

                            <aside class="su-step-guide">
                                <p class="su-panel__kicker">Como ayuda esto</p>
                                <h3>Queremos orientarte desde el inicio</h3>
                                <p>
                                    No necesitas conocer todos los detalles legales para registrarte. Con esta seleccion podemos mostrar el importe correcto
                                    y guiarte hacia el siguiente paso de forma ordenada.
                                </p>
                                <div class="su-guide-list">
                                    <span>El servicio define el registro que se va a pagar.</span>
                                    <span>Las preguntas adicionales nos ayudan a entender mejor tu situacion.</span>
                                    <span>Si tienes familiares en proceso, podemos relacionar mejor la atencion.</span>
                                </div>
                            </aside>
                        </div>

                        <div class="su-wizard-actions">
                            <button type="button" class="su-secondary-btn" data-wizard-prev>Volver</button>
                            <button type="button" class="su-primary-btn su-primary-btn--inline" data-wizard-next>Continuar al pago</button>
                        </div>
                    </section>

                    <section class="su-panel su-wizard-step" data-wizard-step="pago" hidden>
                        <div class="su-panel__head">
                            <span class="su-step">5</span>
                            <div>
                                <h2>Confirma tu registro</h2>
                                <p>Revisa el servicio elegido, aplica un cupon si tienes uno y completa el pago para activar tu registro.</p>
                            </div>
                        </div>

                        <div class="su-step-layout">
                            <div class="su-step-layout__form">
                                <div class="su-coupon-row">
                                    <label class="su-field">
                                        <span>Codigo de cupon</span>
                                        <input autocomplete="off" name="coupon" id="coupon" type="text" placeholder="CUPON">
                                    </label>
                                    <button type="button" id="valcoupon" class="su-secondary-btn">Validar cupon</button>
                                </div>

                                <label class="su-field">
                                    <span>Codigo de referido opcional</span>
                                    <input autocomplete="off" name="referral_code" id="referral_code" type="text" placeholder="Codigo del coordinador">
                                </label>

                                <div class="su-form-grid">
                                    <label class="su-field">
                                        <span>Nombre de facturacion</span>
                                        <input data-payment-field required id="first_name" name="first_name" type="text" autocomplete="given-name">
                                    </label>
                                    <label class="su-field">
                                        <span>Apellido de facturacion</span>
                                        <input data-payment-field required id="last_name" name="last_name" type="text" autocomplete="family-name">
                                    </label>
                                    <label class="su-field">
                                        <span>Email de facturacion</span>
                                        <input data-payment-field required id="email" name="billing_email" type="email" autocomplete="email">
                                    </label>
                                    <label class="su-field">
                                        <span>Telefono de facturacion</span>
                                        <input id="phone" name="billing_phone" type="tel" autocomplete="tel">
                                    </label>
                                    <label class="su-field su-field--wide">
                                        <span>Direccion</span>
                                        <input data-payment-field required id="address_line1" name="address_line1" type="text" autocomplete="address-line1">
                                    </label>
                                    <label class="su-field">
                                        <span>Direccion 2</span>
                                        <input id="address_line2" name="address_line2" type="text" autocomplete="address-line2">
                                    </label>
                                    <label class="su-field">
                                        <span>Ciudad</span>
                                        <input data-payment-field required id="city" name="city" type="text" autocomplete="address-level2">
                                    </label>
                                    <label class="su-field">
                                        <span>Estado o provincia</span>
                                        <input id="state" name="state" type="text" autocomplete="address-level1">
                                    </label>
                                    <label class="su-field">
                                        <span>Codigo postal</span>
                                        <input data-payment-field required id="postal_code" name="postal_code" type="text" autocomplete="postal-code">
                                    </label>
                                    <label class="su-field">
                                        <span>Pais</span>
                                        <select data-payment-field required id="country" name="country" autocomplete="country">
                                            <option value="">Selecciona</option>
                                            <option value="ES" selected>Espana</option>
                                            <option value="PT">Portugal</option>
                                            <option value="IT">Italia</option>
                                            <option value="US">Estados Unidos</option>
                                            <option value="MX">Mexico</option>
                                            <option value="AR">Argentina</option>
                                            <option value="CO">Colombia</option>
                                            <option value="VE">Venezuela</option>
                                            <option value="CL">Chile</option>
                                            <option value="PE">Peru</option>
                                        </select>
                                    </label>
                                </div>

                                <label class="su-field">
                                    <span>Nombre en la tarjeta</span>
                                    <input data-payment-field required id="name_on_card" name="name_on_card" type="text" autocomplete="cc-name">
                                </label>

                                <div class="su-card-element">
                                    <span>Numero de tarjeta, vencimiento y CVC</span>
                                    <div id="card-element"></div>
                                    <div id="card-errors" role="alert"></div>
                                </div>

                                <div class="su-terms">
                                    <label>
                                        <input data-registration-field required type="checkbox" name="acepta_comunicaciones" value="1">
                                        <span>Acepto recibir comunicaciones de Sefar Universal.</span>
                                    </label>
                                    <label>
                                        <input data-registration-field required type="checkbox" name="acepta_datos" value="1">
                                        <span>Acepto que Sefar Universal almacene y procese mis datos personales.</span>
                                    </label>
                                </div>
                            </div>

                            <aside class="su-step-guide su-step-guide--secure">
                                <p class="su-panel__kicker">Sobre esta fase</p>
                                <h3>Este pago activa el inicio de tu proceso</h3>
                                <p>
                                    En este paso confirmas el servicio seleccionado y registras el pago inicial. Los datos de facturacion
                                    nos ayudan a identificar correctamente la compra y asociarla a tu usuario.
                                </p>
                                <div class="su-guide-list">
                                    <span>Si tienes un cupon, aplicalo antes de pagar para actualizar el total.</span>
                                    <span>El pago se procesa mediante una conexion cifrada.</span>
                                    <span>No almacenamos el numero de tarjeta ni el codigo de seguridad.</span>
                                    <span>Si el banco rechaza la operacion, te indicaremos el motivo disponible.</span>
                                    <span>Al confirmarse, podras continuar con la informacion detallada de tu caso.</span>
                                </div>
                                <p class="su-secure-note">Antes de confirmar, revisa que el resumen coincida con el servicio que quieres iniciar.</p>
                            </aside>
                        </div>

                        <div class="su-wizard-actions">
                            <button type="button" class="su-secondary-btn" data-wizard-prev>Volver</button>
                            <button class="su-primary-btn su-primary-btn--inline" type="submit" id="submit-button">
                                <span id="button-text">Crear registro y pagar</span>
                                <span id="spinner" class="su-button-spinner" hidden></span>
                            </button>
                        </div>
                    </section>

                    <section class="su-panel su-wizard-step" data-wizard-step="confirmacion" hidden>
                        <div class="su-confirmation">
                            <span class="su-step">6</span>
                            <p class="su-panel__kicker">Pago confirmado</p>
                            <h2>Tu registro quedo activo</h2>
                            <p id="confirmationMessage">
                                Estamos preparando el siguiente formulario para completar los datos detallados de tu caso.
                            </p>
                            <div class="su-guide-list su-guide-list--inline">
                                <span>Registro creado.</span>
                                <span>Pago asociado.</span>
                                <span>Siguiente paso listo.</span>
                            </div>
                            <a id="confirmationAction" class="su-primary-btn su-primary-btn--inline" href="{{ route('clientes.getinfo') }}">Continuar</a>
                        </div>
                    </section>
                </div>
            </div>

            <aside class="su-summary" data-animate-panel>
                <div class="su-summary__inner">
                    <p class="su-summary__label">Resumen de compra</p>
                    <h2 id="summaryServiceName">Selecciona un servicio</h2>
                    <div id="summaryItems" class="su-summary__items">
                        <div class="su-summary__empty">El total se confirmara antes de cobrar.</div>
                    </div>
                    <div class="su-summary__total">
                        <span>Total</span>
                        <strong id="summaryTotal">0,00 EUR</strong>
                    </div>
                    <div class="su-summary__status" id="checkoutStatus">Pendiente de registro</div>
                    <div class="su-summary__steps">
                        <span class="is-active" data-step-dot="register">Registro</span>
                        <span data-step-dot="coupon">Cupon</span>
                        <span data-step-dot="payment">Pago</span>
                        <span data-step-dot="next">Continuar</span>
                    </div>
                </div>
            </aside>
        </form>

        <div id="registerContactModal" class="su-contact-modal" hidden role="dialog" aria-modal="true" aria-labelledby="registerContactTitle">
            <div class="su-contact-modal__backdrop" data-close-contact-modal></div>
            <div class="su-contact-modal__dialog">
                <button type="button" class="su-contact-modal__close" data-close-contact-modal aria-label="Cerrar ayuda">x</button>
                <p class="su-panel__kicker">Orientacion personalizada</p>
                <h2 id="registerContactTitle">Tienes dudas con el proceso?</h2>
                <p>
                    Podemos ayudarte a identificar la nacionalidad o servicio que corresponde a tu caso antes de completar el registro.
                </p>

                <div class="su-contact-options">
                    <a href="mailto:info@sefaruniversal.com?subject=Necesito%20orientacion%20con%20mi%20registro%20Sefar">
                        <strong>Escribir por correo</strong>
                        <span>info@sefaruniversal.com</span>
                    </a>
                    <a href="tel:+16032621727">
                        <strong>Estados Unidos</strong>
                        <span>+1 603 262 1727</span>
                    </a>
                    <a href="tel:+34911980993">
                        <strong>Espana</strong>
                        <span>+34 911 980 993</span>
                    </a>
                    <a href="tel:+582127201170">
                        <strong>Venezuela</strong>
                        <span>+58 212 720 1170</span>
                    </a>
                </div>

                <div class="su-contact-modal__actions">
                    <button type="button" class="su-secondary-btn" data-close-contact-modal>Seguir revisando</button>
                    <button type="button" class="su-primary-btn su-primary-btn--inline" data-close-contact-modal>Volver al registro</button>
                </div>
            </div>
        </div>
    </main>

    <script src="{{ asset('js/register-checkout.js') }}?v={{ filemtime(public_path('js/register-checkout.js')) }}" defer></script>
</x-guest-layout>
