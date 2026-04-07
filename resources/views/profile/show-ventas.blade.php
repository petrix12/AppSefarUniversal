@extends('adminlte::page')

@section('title', 'Mi Perfil')

@section('content_header')
@stop

@section('content')
    <div class="w-full max-w-full px-6 py-6 space-y-6">

        {{-- HEADER tipo LinkedIn --}}
        @livewire('profile.sales.profile-header')

        {{-- KPIs en toda la fila --}}
        @livewire('profile.sales.kpis')

        <div class="mb-6">
            @livewire('profile.sales.pending-tasks')
        </div>

        {{-- CONTENEDOR FLEX: 2 columnas --}}
        <div class="two-col w-100">
            <div class="col-left">
                @livewire('profile.sales.shared-documents')
                @livewire('profile.sales.news')
            </div>

            <div class="col-right">
                @livewire('profile.sales.recent-customers')
            </div>
        </div>

        {{-- GRÁFICOS (desactivados temporalmente) --}}
        {{-- @livewire('profile.sales.charts-panel') --}}

    </div>

    {{-- MODAL PEQUEÑO Y CENTRADO --}}
    <div id="editProfileModal"
         class="hidden fixed inset-0 bg-black/60 flex items-center justify-center px-4"
         style="z-index: 9999; background-color: rgba(0, 0, 0, 0.7);">

        {{-- Contenedor del modal - altura máxima de 600px --}}
        <div class="bg-white w-full max-w-2xl rounded-xl shadow-2xl overflow-hidden flex flex-col"
             style="max-height: 600px;">

            {{-- Header fijo --}}
            <div class="flex-shrink-0 px-6 py-4 border-b border-slate-200 flex items-center justify-between bg-white">
                <h3 class="text-lg font-bold text-slate-900">Editar usuario</h3>
                <button
                    class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition-colors"
                    onclick="closeEditProfile()"
                    type="button"
                >
                    <i class="fas fa-times"></i>
                </button>
            </div>

            {{-- Contenido con scroll --}}
            <div class="flex-1 overflow-y-auto px-6 py-6">
                <div class="space-y-6">

                    @if (Laravel\Fortify\Features::canUpdateProfileInformation())
                        @livewire('profile.update-profile-information-form')
                        <x-section-border />
                    @endif

                    @if (Laravel\Fortify\Features::enabled(Laravel\Fortify\Features::updatePasswords()))
                        @livewire('profile.update-password-form')
                        <x-section-border />
                    @endif

                    @if (Laravel\Fortify\Features::canManageTwoFactorAuthentication())
                        @livewire('profile.two-factor-authentication-form')
                        <x-section-border />
                    @endif

                    @livewire('profile.logout-other-browser-sessions-form')

                    @if (Laravel\Jetstream\Jetstream::hasAccountDeletionFeatures())
                        <x-section-border />
                        @livewire('profile.delete-user-form')
                    @endif

                </div>
            </div>

            {{-- Footer fijo --}}
            <div class="flex-shrink-0 px-6 py-4 border-t border-slate-200 bg-slate-50 flex justify-end gap-3">
                <button
                    type="button"
                    class="px-4 py-2 rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-100 font-medium transition-colors"
                    onclick="closeEditProfile()"
                >
                    Cerrar
                </button>
            </div>

        </div>
    </div>
@stop

@section('css')
<link rel="stylesheet" href="/css/sefar.css">
<link rel="stylesheet" href="/css/app.css">
<style>
    .btn { border-radius: .6rem; }

    /* Evitar scroll del body cuando modal está abierto */
    body.modal-open {
        overflow: hidden;
    }

    /* Estilos para el scroll del modal */
    #editProfileModal .overflow-y-auto::-webkit-scrollbar {
        width: 6px;
    }

    #editProfileModal .overflow-y-auto::-webkit-scrollbar-track {
        background: #f1f5f9;
        border-radius: 10px;
    }

    #editProfileModal .overflow-y-auto::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 10px;
    }

    #editProfileModal .overflow-y-auto::-webkit-scrollbar-thumb:hover {
        background: #94a3b8;
    }

    .two-col{
        display: flex;
        gap: 24px;            /* equivalente a gap-6 */
        width: 100%;
        align-items: flex-start;
    }

        /* Izquierda: 3/4 */
    .two-col .col-left{
        flex: 2 1 0;          /* grow=3, shrink=1, basis=0 => 3:1 real */
        min-width: 0;         /* evita overflow raro por contenido */
    }
        /* Derecha: 1/4 */
    .two-col .col-right{
        flex: 1 1 0;          /* grow=1 */
        min-width: 260px;     /* opcional: para que no se aplaste */
    }
    .col-left {
        display: flex;
        flex-direction: column;
        gap: 24px; /* equivalente a gap-6 */
    }
</style>
@stop

@section('js')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>


@if(session('success'))

<div id="welcomeModal" style="
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.6);
    z-index: 9999;
    display:flex;
    align-items:center;
    justify-content:center;
    padding:20px;
">

    <div style="
        background:white;
        max-width:700px;
        width:100%;
        border-radius:12px;
        overflow:hidden;
        box-shadow:0 20px 60px rgba(0,0,0,0.3);
        font-family: 'Arial', sans-serif;
    ">

        {{-- HEADER --}}
        <div style="padding:20px; border-bottom:1px solid #eee; text-align:center;">
            <h2 style="margin:0; font-size:22px; font-weight:700;">
                Bienvenido/a a Sefar Universal, {{ auth()->user()->name }}!
            </h2>
        </div>

        {{-- BODY --}}
        <div
            id="cartaScroll"
            style="padding:25px; max-height:70vh; overflow-y:auto; font-size:14px; line-height:1.6; color:#333;"
        >
            <p style="font-size:12px; color:#777; text-align:center; margin-bottom:18px;">
                Desplázate hasta el final para habilitar el botón de continuar
            </p>

            <p><b>Querida/o coordinador/a de Migración y Nacionalidad:</b></p>
            <br>

            <p>
                Es un honor darte la bienvenida a <b>Sefar Universal</b>. Mi nombre es <b>Crisanto Bello</b>, fundador
                y presidente de esta firma y abogado genealogista. Cuando inicié la defensa de las
                nacionalidades por linaje, lo hice como un ejercicio profesional aislado: asesoraba a
                familias para que reconocieran sus raíces, reconstruía expedientes y litigaba ante
                consulados y tribunales y ministerios europeos. La necesidad de crecer con ese trabajo y
                de proteger a más personas me llevó a convertir la práctica en una firma global. Hoy
                contamos con un equipo extendido en América, Europa, Asia, África y Oceanía y oficinas
                en <b>Estados Unidos, México, Colombia, Venezuela, España, Portugal, Italia y otros
                países.</b> Esta transformación jamás habría sido posible sin la entrega de nuestros
                coordinadores, genealogistas y abogados, sin vuestra pasión por cada historia y sin el
                método <b>Intuitu Personae</b> y la disciplina del <b>Derecho Genealogista</b>, que nos distingue.
            </p>

            <br>

            <p>
                Cada coordinador es socio estratégico. Te unirás a un equipo de <b>cientos</b> de abogados,
                genealogistas, historiadores, paleógrafos, archivólogos y bibliotecólogos que evalúan
                cada caso aplicando derecho comparado y genealogía para orientar a nuestros
                representados en la ruta hacia su libertad. Nuestra reputación está respaldada por más de
                <b>11 mil nacionalidades europeas aprobadas</b> y por un <b>historial del 100 % de casos de
                éxito</b>. No negociamos con promesas, sino con derechos reales: tus antepasados te
                quieren libre y nosotros convertimos esa genealogía en un derecho.
            </p>

            <br>

            <p>
                Hoy te invito a sumergirte en esta historia. Cada llamada que hagas, cada familia que
                escuches y cada expediente que lleves a buen puerto será parte de un legado. Serás testigo
                de cómo una familia colombiana descubre su linaje sefardí, como un joven venezolano
                consigue su pasaporte italiano por vía judicial o como una abuela colombiana recupera su
                identidad portuguesa perdida. Nuestro trabajo trasciende las fronteras y tú eres la pieza
                clave que hace posible esa transición.
            </p>

            <br>

            <p>
                Trabajemos juntos con disciplina y alegría. Pon tu talento al servicio de las personas y la
                libertad vendrá por añadidura. Bienvenido/a a tu casa.
            </p>

            <br>

            <p><b>Bendiciones,</b></p>
            <p>
                <b>Dr. Crisanto Bello</b><br>
                Presidente de Sefar Universal
            </p>
        </div>

        {{-- FOOTER --}}
        <div style="padding:15px; border-top:1px solid #eee; text-align:right;">
            <button
                id="btnContinuar"
                type="button"
                disabled
                onclick="cerrarCartaBienvenida()"
                style="
                    background:#ccc;
                    color:white;
                    border:none;
                    padding:10px 20px;
                    border-radius:6px;
                    cursor:not-allowed;
                    opacity:0.8;
                "
            >
                Continuar
            </button>
        </div>

    </div>
</div>

<script>
    (function () {
        const scrollBox = document.getElementById('cartaScroll');
        const btn = document.getElementById('btnContinuar');

        if (!scrollBox || !btn) return;

        function habilitarBotonSiLlegoAlFinal() {
            const llegoAlFinal =
                scrollBox.scrollTop + scrollBox.clientHeight >= scrollBox.scrollHeight - 10;

            if (llegoAlFinal) {
                btn.disabled = false;
                btn.style.background = '#3085d6';
                btn.style.cursor = 'pointer';
                btn.style.opacity = '1';
            }
        }

        scrollBox.addEventListener('scroll', habilitarBotonSiLlegoAlFinal);

        // Por si el contenido no llega a tener scroll en pantallas muy grandes
        habilitarBotonSiLlegoAlFinal();
    })();

    function cerrarCartaBienvenida() {
        const btn = document.getElementById('btnContinuar');
        if (btn && btn.disabled) return;

        const modal = document.getElementById('welcomeModal');
        if (modal) {
            modal.remove();
        }
    }
</script>
@endif

@if(session('error'))
<script>
    Swal.fire({
        icon: 'error',
        title: 'Error',
        text: '{{ session('error') }}',
        confirmButtonText: 'Cerrar',
        confirmButtonColor: '#d33'
    });
</script>
@endif

<script>
    function openEditProfile() {
        const modal = document.getElementById('editProfileModal');
        modal.classList.remove('hidden');
        document.body.classList.add('modal-open');
    }

    function closeEditProfile() {
        const modal = document.getElementById('editProfileModal');
        modal.classList.add('hidden');
        document.body.classList.remove('modal-open');
    }

    // Cerrar al click en el backdrop
    document.addEventListener('click', function(e) {
        const modal = document.getElementById('editProfileModal');
        if (!modal || modal.classList.contains('hidden')) return;
        if (e.target === modal) {
            closeEditProfile();
        }
    });

    // Cerrar con tecla ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const modal = document.getElementById('editProfileModal');
            if (modal && !modal.classList.contains('hidden')) {
                closeEditProfile();
            }
        }
    });
</script>
@stop
