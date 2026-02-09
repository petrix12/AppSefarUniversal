@extends('adminlte::page')

@section('title', 'Mi Perfil')

@section('content_header')
@stop

@section('content')
<x-app-layout>
    <div class="w-full max-w-full px-6 py-6 space-y-6">

        {{-- HEADER tipo LinkedIn --}}
        @livewire('profile.sales.profile-header')

        {{-- KPIs en toda la fila --}}
        @livewire('profile.sales.kpis')

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
</x-app-layout>
@stop

@section('css')
<link rel="stylesheet" href="/css/admin_custom.css">
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
