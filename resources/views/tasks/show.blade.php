{{-- resources/views/tasks/show.blade.php --}}
@extends('adminlte::page')

@section('title', 'Detalle de tarea')

@push('css')
<link rel="stylesheet" href="{{ asset('css/sefar.css') }}">
<style>
    .step-card { transition: all .2s ease; }
    .step-card:hover { transform: translateY(-1px); }

    .radio-option {
        display: flex;
        align-items: center;
        gap: .75rem;
        padding: .875rem 1rem;
        border: 2px solid #e5e7eb;
        border-radius: .75rem;
        cursor: pointer;
        transition: all .15s ease;
        background: #fff;
    }
    .radio-option:hover { border-color: #93c5fd; background: #eff6ff; }
    .radio-option:has(input:checked) {
        border-color: #3b82f6;
        background: #eff6ff;
    }
    .radio-option.danger:has(input:checked) {
        border-color: #ef4444;
        background: #fef2f2;
    }
    .radio-option input[type="radio"] { accent-color: #3b82f6; width: 1.1rem; height: 1.1rem; }

    .field-reveal {
        overflow: hidden;
        transition: max-height .3s ease, opacity .3s ease;
        max-height: 0;
        opacity: 0;
    }
    .field-reveal.visible {
        max-height: 120px;
        opacity: 1;
    }

    .step-indicator {
        display: flex;
        align-items: center;
        gap: .5rem;
        font-size: .75rem;
        font-weight: 600;
        letter-spacing: .05em;
        text-transform: uppercase;
        margin-bottom: .35rem;
    }
    .step-dot {
        width: 1.5rem; height: 1.5rem;
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-size: .7rem; font-weight: 700;
    }
</style>
@endpush

@section('content')
<div class="min-h-screen bg-gray-50 py-8 px-4">
<div class="max-w-xl mx-auto space-y-5">

    {{-- ══ CABECERA ══════════════════════════════════════════════ --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">

        {{-- Breadcrumb --}}
        <a href="{{ route('tasks.index') }}"
           class="inline-flex items-center gap-1.5 text-xs text-gray-400 hover:text-blue-600
                  transition mb-4 group">
            <svg class="w-3.5 h-3.5 group-hover:-translate-x-0.5 transition-transform"
                 fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
            </svg>
            Mis tareas
        </a>

        <div class="flex items-start justify-between gap-4">
            <div class="min-w-0">
                <h1 class="text-xl font-bold text-gray-900 leading-tight truncate">
                    {{ $task->title }}
                </h1>
                <div class="flex flex-wrap items-center gap-x-3 gap-y-1 mt-2">
                    {{-- Contacto --}}
                    <span class="inline-flex items-center gap-1.5 text-sm text-gray-500">
                        <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor"
                             stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z
                                     M4.501 20.118a7.5 7.5 0 0114.998 0"/>
                        </svg>
                        <span class="font-medium text-gray-700">
                            {{ $task->contact->name ?? '—' }}
                        </span>
                    </span>
                    <span class="text-gray-300 hidden sm:inline">·</span>
                    {{-- Fecha --}}
                    <span class="inline-flex items-center gap-1.5 text-sm text-gray-500">
                        <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor"
                             stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25
                                     0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18
                                     0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021
                                     18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25
                                     2.25 0 0121 9v7.5"/>
                        </svg>
                        Vence {{ $task->due_date->format('d/m/Y') }}
                    </span>
                </div>
            </div>

            {{-- Badge estado --}}
            @php
                [$badgeBg, $badgeText, $badgeDot] = match($task->status) {
                    'pending'     => ['bg-amber-50',  'text-amber-700',  'bg-amber-400'],
                    'in_progress' => ['bg-blue-50',   'text-blue-700',   'bg-blue-400'],
                    'completed'   => ['bg-emerald-50','text-emerald-700','bg-emerald-400'],
                    'canceled'    => ['bg-red-50',    'text-red-700',    'bg-red-400'],
                    default       => ['bg-gray-50',   'text-gray-600',   'bg-gray-400'],
                };
                $badgeLabel = match($task->status) {
                    'pending'     => 'Pendiente',
                    'in_progress' => 'En progreso',
                    'completed'   => 'Completada',
                    'canceled'    => 'Cancelada',
                    default       => $task->status,
                };
            @endphp
            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full
                         text-xs font-semibold whitespace-nowrap shrink-0
                         {{ $badgeBg }} {{ $badgeText }}">
                <span class="w-1.5 h-1.5 rounded-full {{ $badgeDot }}"></span>
                {{ $badgeLabel }}
            </span>
        </div>
    </div>

    {{-- ══ ALERTAS ════════════════════════════════════════════════ --}}
    @if(session('success'))
    <div class="flex items-start gap-3 p-4 rounded-xl bg-emerald-50 border border-emerald-200">
        <span class="text-emerald-500 text-lg leading-none mt-0.5">✓</span>
        <p class="text-sm text-emerald-700 font-medium">{{ session('success') }}</p>
    </div>
    @endif
    @if(session('error'))
    <div class="flex items-start gap-3 p-4 rounded-xl bg-red-50 border border-red-200">
        <span class="text-red-500 text-lg leading-none mt-0.5">!</span>
        <p class="text-sm text-red-700 font-medium">{{ session('error') }}</p>
    </div>
    @endif

    {{-- ══ PROGRESO REGISTRADO ════════════════════════════════════ --}}
    @if(! $task->isClosed() || $task->call_effective !== null)
    @php
        $hasProgress = $task->call_effective !== null
                    || $task->reason_no_effective
                    || $task->interest_level !== null
                    || $task->reason_no_interest
                    || $task->product_of_interest
                    || $task->follow_up_date;
    @endphp
    @if($hasProgress)
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
        <h2 class="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-4">
            Progreso registrado
        </h2>
        <dl class="space-y-3">

            @if($task->call_effective !== null)
            <div class="flex items-center justify-between py-2 border-b border-gray-50">
                <dt class="text-sm text-gray-500 flex items-center gap-2">
                    <span>📞</span> Llamada efectiva
                </dt>
                <dd class="text-sm font-semibold
                    {{ $task->call_effective ? 'text-emerald-600' : 'text-red-500' }}">
                    {{ $task->call_effective ? 'Sí' : 'No' }}
                </dd>
            </div>
            @endif

            @if($task->reason_no_effective)
            <div class="flex items-start justify-between gap-4 py-2 border-b border-gray-50">
                <dt class="text-sm text-gray-500 shrink-0">↳ Motivo</dt>
                <dd class="text-sm text-gray-700 text-right">{{ $task->reason_no_effective }}</dd>
            </div>
            @endif

            @if($task->interest_level !== null)
            <div class="flex items-center justify-between py-2 border-b border-gray-50">
                <dt class="text-sm text-gray-500 flex items-center gap-2">
                    <span>💬</span> Mostró interés
                </dt>
                <dd class="text-sm font-semibold
                    {{ $task->interest_level ? 'text-emerald-600' : 'text-red-500' }}">
                    {{ $task->interest_level ? 'Sí' : 'No' }}
                </dd>
            </div>
            @endif

            @if($task->reason_no_interest)
            <div class="flex items-start justify-between gap-4 py-2 border-b border-gray-50">
                <dt class="text-sm text-gray-500 shrink-0">↳ Motivo</dt>
                <dd class="text-sm text-gray-700 text-right">{{ $task->reason_no_interest }}</dd>
            </div>
            @endif

            @if($task->product_of_interest)
            <div class="flex items-center justify-between py-2 border-b border-gray-50">
                <dt class="text-sm text-gray-500 flex items-center gap-2">
                    <span>🛍️</span> Producto
                </dt>
                <dd class="text-sm font-semibold text-gray-800">
                    {{ $task->product_of_interest }}
                </dd>
            </div>
            @endif

            @if($task->follow_up_date)
            <div class="flex items-center justify-between py-2">
                <dt class="text-sm text-gray-500 flex items-center gap-2">
                    <span>📅</span> Seguimiento
                </dt>
                <dd class="text-sm font-semibold text-blue-600">
                    {{ $task->follow_up_date->format('d/m/Y') }}
                </dd>
            </div>
            @endif

        </dl>
    </div>
    @endif
    @endif

    {{-- ══ FLUJO DE PASOS ════════════════════════════════════════ --}}
    @if(! $task->isClosed())

        {{-- ── PASO 1 ──────────────────────────────────────────── --}}
        @if($task->call_effective === null)
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 step-card">

            <div class="step-indicator text-blue-500">
                <span class="step-dot bg-blue-100 text-blue-600">1</span>
                Resultado de llamada
            </div>
            <h2 class="text-base font-bold text-gray-800 mb-5">
                ¿La llamada fue efectiva?
            </h2>

            <form method="POST" action="{{ route('tasks.submitFlow', $task) }}">
                @csrf
                <input type="hidden" name="step" value="call_result">

                <div class="space-y-2.5 mb-5">
                    <label class="radio-option">
                        <input type="radio" name="call_effective" value="1"
                               {{ old('call_effective') === '1' ? 'checked' : '' }}>
                        <div>
                            <p class="text-sm font-medium text-gray-800">Sí, fue efectiva</p>
                            <p class="text-xs text-gray-400 mt-0.5">El contacto respondió y conversamos</p>
                        </div>
                    </label>
                    <label class="radio-option danger">
                        <input type="radio" name="call_effective" value="0"
                               id="notEffective"
                               {{ old('call_effective') === '0' ? 'checked' : '' }}>
                        <div>
                            <p class="text-sm font-medium text-gray-800">No fue efectiva</p>
                            <p class="text-xs text-gray-400 mt-0.5">No contestó, número incorrecto, etc.</p>
                        </div>
                    </label>
                </div>

                <div id="reasonBox"
                     class="field-reveal {{ old('call_effective') === '0' ? 'visible' : '' }} mb-4">
                    <label class="block text-xs font-semibold text-gray-500 uppercase
                                  tracking-wide mb-1.5">
                        Motivo <span class="text-red-400">*</span>
                    </label>
                    <input type="text"
                           name="reason_no_effective"
                           value="{{ old('reason_no_effective') }}"
                           maxlength="255"
                           placeholder="Ej: No contestó, número equivocado…"
                           class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm
                                  bg-gray-50 focus:bg-white focus:outline-none
                                  focus:ring-2 focus:ring-blue-400 focus:border-transparent
                                  transition placeholder-gray-300">
                    @error('reason_no_effective')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                @error('call_effective')
                    <p class="text-red-500 text-xs mb-3">{{ $message }}</p>
                @enderror

                <button type="submit"
                        class="w-full bg-blue-600 hover:bg-blue-700 active:bg-blue-800
                               text-white text-sm font-semibold py-3 rounded-xl
                               transition flex items-center justify-center gap-2 shadow-sm
                               shadow-blue-200">
                    Guardar y continuar
                    <svg class="w-4 h-4" fill="none" stroke="currentColor"
                         stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/>
                    </svg>
                </button>
            </form>
        </div>

        {{-- ── PASO 2 ──────────────────────────────────────────── --}}
        @elseif($task->call_effective && $task->interest_level === null)
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 step-card">

            <div class="step-indicator text-violet-500">
                <span class="step-dot bg-violet-100 text-violet-600">2</span>
                Nivel de interés
            </div>
            <h2 class="text-base font-bold text-gray-800 mb-5">
                ¿El contacto mostró interés?
            </h2>

            <form method="POST" action="{{ route('tasks.submitFlow', $task) }}">
                @csrf
                <input type="hidden" name="step" value="interest">

                <div class="space-y-2.5 mb-5">
                    <label class="radio-option">
                        <input type="radio" name="interest_level" value="1"
                               {{ old('interest_level') === '1' ? 'checked' : '' }}>
                        <div>
                            <p class="text-sm font-medium text-gray-800">Sí, mostró interés</p>
                            <p class="text-xs text-gray-400 mt-0.5">Quiere saber más o evaluar la oferta</p>
                        </div>
                    </label>
                    <label class="radio-option danger">
                        <input type="radio" name="interest_level" value="0"
                               id="noInterest"
                               {{ old('interest_level') === '0' ? 'checked' : '' }}>
                        <div>
                            <p class="text-sm font-medium text-gray-800">No mostró interés</p>
                            <p class="text-xs text-gray-400 mt-0.5">Rechazó la oferta o no quiso continuar</p>
                        </div>
                    </label>
                </div>

                <div id="reasonNoInterestBox"
                     class="field-reveal {{ old('interest_level') === '0' ? 'visible' : '' }} mb-4">
                    <label class="block text-xs font-semibold text-gray-500 uppercase
                                  tracking-wide mb-1.5">
                        Motivo <span class="text-red-400">*</span>
                    </label>
                    <input type="text"
                           name="reason_no_interest"
                           value="{{ old('reason_no_interest') }}"
                           maxlength="255"
                           placeholder="Ej: Ya tiene el producto, no le interesa ahora…"
                           class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm
                                  bg-gray-50 focus:bg-white focus:outline-none
                                  focus:ring-2 focus:ring-violet-400 focus:border-transparent
                                  transition placeholder-gray-300">
                    @error('reason_no_interest')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                @error('interest_level')
                    <p class="text-red-500 text-xs mb-3">{{ $message }}</p>
                @enderror

                <button type="submit"
                        class="w-full bg-violet-600 hover:bg-violet-700 active:bg-violet-800
                               text-white text-sm font-semibold py-3 rounded-xl
                               transition flex items-center justify-center gap-2 shadow-sm
                               shadow-violet-200">
                    Guardar y continuar
                    <svg class="w-4 h-4" fill="none" stroke="currentColor"
                         stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/>
                    </svg>
                </button>
            </form>
        </div>

        {{-- ── PASO 3 ──────────────────────────────────────────── --}}
        @elseif($task->call_effective && $task->interest_level)
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 step-card">

            <div class="step-indicator text-emerald-500">
                <span class="step-dot bg-emerald-100 text-emerald-600">3</span>
                Producto y seguimiento
            </div>
            <h2 class="text-base font-bold text-gray-800 mb-5">
                ¿Qué producto le interesa?
            </h2>

            <form method="POST" action="{{ route('tasks.submitFlow', $task) }}">
                @csrf
                <input type="hidden" name="step" value="product_followup">

                <div class="mb-4">
                    <label class="block text-xs font-semibold text-gray-500 uppercase
                                  tracking-wide mb-1.5">
                        Producto de interés <span class="text-red-400">*</span>
                    </label>
                    <input type="text"
                           name="product_of_interest"
                           value="{{ old('product_of_interest') }}"
                           maxlength="255"
                           placeholder="Ej: Seguro de vida, Crédito hipotecario…"
                           class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm
                                  bg-gray-50 focus:bg-white focus:outline-none
                                  focus:ring-2 focus:ring-emerald-400 focus:border-transparent
                                  transition placeholder-gray-300">
                    @error('product_of_interest')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mb-6">
                    <label class="block text-xs font-semibold text-gray-500 uppercase
                                  tracking-wide mb-1.5">
                        Fecha de seguimiento
                        <span class="normal-case font-normal text-gray-400 ml-1">(opcional)</span>
                    </label>
                    <input type="date"
                           name="follow_up_date"
                           value="{{ old('follow_up_date') }}"
                           min="{{ today()->addDay()->toDateString() }}"
                           class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm
                                  bg-gray-50 focus:bg-white focus:outline-none
                                  focus:ring-2 focus:ring-emerald-400 focus:border-transparent
                                  transition text-gray-600">
                    <p class="text-xs text-gray-400 mt-1.5 flex items-center gap-1">
                        <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor"
                             stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708
                                     2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9
                                     0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z"/>
                        </svg>
                        Si indicas una fecha se creará una tarea de seguimiento automáticamente.
                    </p>
                    @error('follow_up_date')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit"
                        class="w-full bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800
                               text-white text-sm font-semibold py-3 rounded-xl
                               transition flex items-center justify-center gap-2 shadow-sm
                               shadow-emerald-200">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor"
                         stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M4.5 12.75l6 6 9-13.5"/>
                    </svg>
                    Completar tarea
                </button>
            </form>
        </div>
        @endif

    {{-- ══ TAREA CERRADA ══════════════════════════════════════════ --}}
    @else
    <div class="bg-white rounded-2xl border border-gray-100 p-8 text-center">
        <div class="w-14 h-14 rounded-full {{ $badgeBg }} flex items-center justify-center
                    mx-auto mb-4 text-2xl">
            {{ $task->status === 'completed' ? '✓' : '✕' }}
        </div>
        <p class="text-sm font-semibold text-gray-700">
            Tarea {{ strtolower($badgeLabel) }}
        </p>
        <p class="text-xs text-gray-400 mt-1">Esta tarea ya no admite más cambios.</p>
    </div>
    @endif

</div>
</div>
@endsection

@push('scripts')
<script>
    // ── Toggle animado con CSS transition ──────────────────────────
    function bindReveal(radioName, boxId) {
        document.querySelectorAll(`input[name="${radioName}"]`).forEach(radio => {
            radio.addEventListener('change', () => {
                const box = document.getElementById(boxId);
                if (!box) return;
                const show = radio.value === '0';
                box.classList.toggle('visible', show);
                // Enfocar el input cuando se revela
                if (show) setTimeout(() => box.querySelector('input')?.focus(), 320);
            });
        });
    }

    bindReveal('call_effective', 'reasonBox');
    bindReveal('interest_level', 'reasonNoInterestBox');
</script>
@endpush
