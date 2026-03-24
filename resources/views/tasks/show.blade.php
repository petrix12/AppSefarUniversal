{{-- resources/views/tasks/show.blade.php --}}
@extends('layouts.app')

@section('title', 'Detalle de tarea')

@section('content')
<div class="max-w-2xl mx-auto py-8 px-4">

    {{-- ── Encabezado ──────────────────────────────────── --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">{{ $task->title }}</h1>
            <p class="text-sm text-gray-500 mt-1">
                Contacto:
                <span class="font-medium text-gray-700">
                    {{ $task->contact->name ?? '—' }}
                </span>
                &nbsp;·&nbsp;
                Vence: {{ $task->due_date->format('d/m/Y') }}
            </p>
        </div>

        {{-- Badge de estado --}}
        @php
            $badgeClass = match($task->status) {
                'pending'     => 'bg-yellow-100 text-yellow-800',
                'in_progress' => 'bg-blue-100   text-blue-800',
                'completed'   => 'bg-green-100  text-green-800',
                'canceled'    => 'bg-red-100    text-red-800',
                default       => 'bg-gray-100   text-gray-800',
            };
            $badgeLabel = match($task->status) {
                'pending'     => 'Pendiente',
                'in_progress' => 'En progreso',
                'completed'   => 'Completada',
                'canceled'    => 'Cancelada',
                default       => $task->status,
            };
        @endphp
        <span class="px-3 py-1 rounded-full text-sm font-semibold {{ $badgeClass }}">
            {{ $badgeLabel }}
        </span>
    </div>

    {{-- ── Alertas de sesión ───────────────────────────── --}}
    @if(session('success'))
        <div class="mb-4 p-3 rounded-lg bg-green-50 border border-green-200 text-green-700 text-sm">
            ✅ {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="mb-4 p-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm">
            ⚠️ {{ session('error') }}
        </div>
    @endif

    {{-- ── Resumen de datos ya registrados ────────────── --}}
    @if(! $task->isClosed() || $task->call_effective !== null)
    <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-5 mb-6 space-y-2 text-sm text-gray-600">
        <h2 class="font-semibold text-gray-700 mb-3">📋 Progreso registrado</h2>

        {{-- Llamada efectiva --}}
        @if($task->call_effective !== null)
            <div class="flex gap-2">
                <span class="w-40 text-gray-400">Llamada efectiva:</span>
                <span class="{{ $task->call_effective ? 'text-green-600' : 'text-red-600' }} font-medium">
                    {{ $task->call_effective ? 'Sí' : 'No' }}
                </span>
            </div>
        @endif

        {{-- Motivo no efectiva --}}
        @if($task->reason_no_effective)
            <div class="flex gap-2">
                <span class="w-40 text-gray-400">Motivo:</span>
                <span>{{ $task->reason_no_effective }}</span>
            </div>
        @endif

        {{-- Interés --}}
        @if($task->interest_level !== null)
            <div class="flex gap-2">
                <span class="w-40 text-gray-400">Interés:</span>
                <span class="{{ $task->interest_level ? 'text-green-600' : 'text-red-600' }} font-medium">
                    {{ $task->interest_level ? 'Sí' : 'No' }}
                </span>
            </div>
        @endif

        {{-- Motivo sin interés --}}
        @if($task->reason_no_interest)
            <div class="flex gap-2">
                <span class="w-40 text-gray-400">Motivo:</span>
                <span>{{ $task->reason_no_interest }}</span>
            </div>
        @endif

        {{-- Producto de interés --}}
        @if($task->product_of_interest)
            <div class="flex gap-2">
                <span class="w-40 text-gray-400">Producto:</span>
                <span class="font-medium text-gray-800">{{ $task->product_of_interest }}</span>
            </div>
        @endif

        {{-- Fecha de seguimiento --}}
        @if($task->follow_up_date)
            <div class="flex gap-2">
                <span class="w-40 text-gray-400">Seguimiento:</span>
                <span>{{ $task->follow_up_date->format('d/m/Y') }}</span>
            </div>
        @endif
    </div>
    @endif

    {{-- ══════════════════════════════════════════════════
         FLUJO DE PASOS  (solo si la tarea está abierta)
    ══════════════════════════════════════════════════════ --}}
    @if(! $task->isClosed())

        {{-- ── PASO 1 · Resultado de llamada ───────────── --}}
        @if($task->call_effective === null)
        <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-6">
            <h2 class="font-semibold text-gray-700 mb-4">📞 Paso 1 — ¿La llamada fue efectiva?</h2>

            <form method="POST" action="{{ route('tasks.submitFlow', $task) }}">
                @csrf
                <input type="hidden" name="step" value="call_result">

                <div class="space-y-3 mb-5">
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="radio" name="call_effective" value="1"
                               class="accent-blue-600"
                               {{ old('call_effective') === '1' ? 'checked' : '' }}>
                        <span class="text-sm text-gray-700">Sí, fue efectiva</span>
                    </label>
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="radio" name="call_effective" value="0"
                               id="notEffective"
                               class="accent-blue-600"
                               {{ old('call_effective') === '0' ? 'checked' : '' }}>
                        <span class="text-sm text-gray-700">No fue efectiva</span>
                    </label>
                </div>

                {{-- Motivo (visible solo si "No") --}}
                <div id="reasonBox" class="mb-5 {{ old('call_effective') === '0' ? '' : 'hidden' }}">
                    <label class="block text-sm text-gray-600 mb-1">
                        Motivo de no efectividad <span class="text-red-500">*</span>
                    </label>
                    <input type="text"
                           name="reason_no_effective"
                           value="{{ old('reason_no_effective') }}"
                           maxlength="255"
                           placeholder="Ej: No contestó, número equivocado…"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm
                                  focus:outline-none focus:ring-2 focus:ring-blue-400">
                    @error('reason_no_effective')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                @error('call_effective')
                    <p class="text-red-500 text-xs mb-3">{{ $message }}</p>
                @enderror

                <button type="submit"
                        class="w-full bg-blue-600 hover:bg-blue-700 text-white text-sm
                               font-semibold py-2 rounded-lg transition">
                    Guardar y continuar →
                </button>
            </form>
        </div>

        {{-- ── PASO 2 · Interés ─────────────────────────── --}}
        @elseif($task->call_effective && $task->interest_level === null)
        <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-6">
            <h2 class="font-semibold text-gray-700 mb-4">💬 Paso 2 — ¿Hubo interés?</h2>

            <form method="POST" action="{{ route('tasks.submitFlow', $task) }}">
                @csrf
                <input type="hidden" name="step" value="interest">

                <div class="space-y-3 mb-5">
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="radio" name="interest_level" value="1"
                               class="accent-blue-600"
                               {{ old('interest_level') === '1' ? 'checked' : '' }}>
                        <span class="text-sm text-gray-700">Sí, mostró interés</span>
                    </label>
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="radio" name="interest_level" value="0"
                               id="noInterest"
                               class="accent-blue-600"
                               {{ old('interest_level') === '0' ? 'checked' : '' }}>
                        <span class="text-sm text-gray-700">No mostró interés</span>
                    </label>
                </div>

                <div id="reasonNoInterestBox" class="mb-5 {{ old('interest_level') === '0' ? '' : 'hidden' }}">
                    <label class="block text-sm text-gray-600 mb-1">
                        Motivo de no interés <span class="text-red-500">*</span>
                    </label>
                    <input type="text"
                           name="reason_no_interest"
                           value="{{ old('reason_no_interest') }}"
                           maxlength="255"
                           placeholder="Ej: Ya tiene el producto, no le interesa ahora…"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm
                                  focus:outline-none focus:ring-2 focus:ring-blue-400">
                    @error('reason_no_interest')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                @error('interest_level')
                    <p class="text-red-500 text-xs mb-3">{{ $message }}</p>
                @enderror

                <button type="submit"
                        class="w-full bg-blue-600 hover:bg-blue-700 text-white text-sm
                               font-semibold py-2 rounded-lg transition">
                    Guardar y continuar →
                </button>
            </form>
        </div>

        {{-- ── PASO 3 · Producto + Seguimiento ─────────── --}}
        @elseif($task->call_effective && $task->interest_level)
        <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-6">
            <h2 class="font-semibold text-gray-700 mb-4">🛍️ Paso 3 — Producto y seguimiento</h2>

            <form method="POST" action="{{ route('tasks.submitFlow', $task) }}">
                @csrf
                <input type="hidden" name="step" value="product_followup">

                <div class="mb-4">
                    <label class="block text-sm text-gray-600 mb-1">
                        Producto de interés <span class="text-red-500">*</span>
                    </label>
                    <input type="text"
                           name="product_of_interest"
                           value="{{ old('product_of_interest') }}"
                           maxlength="255"
                           placeholder="Ej: Seguro de vida, Crédito hipotecario…"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm
                                  focus:outline-none focus:ring-2 focus:ring-blue-400">
                    @error('product_of_interest')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mb-5">
                    <label class="block text-sm text-gray-600 mb-1">
                        Fecha de seguimiento
                        <span class="text-gray-400">(opcional)</span>
                    </label>
                    <input type="date"
                           name="follow_up_date"
                           value="{{ old('follow_up_date') }}"
                           min="{{ today()->addDay()->toDateString() }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm
                                  focus:outline-none focus:ring-2 focus:ring-blue-400">
                    <p class="text-xs text-gray-400 mt-1">
                        Si indicas una fecha se creará una tarea de seguimiento automáticamente.
                    </p>
                    @error('follow_up_date')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit"
                        class="w-full bg-green-600 hover:bg-green-700 text-white text-sm
                               font-semibold py-2 rounded-lg transition">
                    ✅ Completar tarea
                </button>
            </form>
        </div>
        @endif

    {{-- ══ Tarea ya cerrada ══════════════════════════════════ --}}
    @else
        <div class="bg-gray-50 border border-gray-200 rounded-xl p-5 text-center text-gray-500 text-sm">
            Esta tarea ya fue <span class="font-semibold">{{ $badgeLabel }}</span>
            y no admite más cambios.
        </div>
    @endif

    {{-- ── Volver ───────────────────────────────────────── --}}
    <div class="mt-6 text-center">
        <a href="{{ route('tasks.table') }}"
           class="text-sm text-blue-600 hover:underline">
            ← Volver a mis tareas
        </a>
    </div>

</div>
@endsection

{{-- ── JS mínimo: mostrar/ocultar campos condicionales ──── --}}
@push('scripts')
<script>
    // Paso 1 – razón de no efectividad
    document.querySelectorAll('input[name="call_effective"]').forEach(radio => {
        radio.addEventListener('change', () => {
            const box = document.getElementById('reasonBox');
            if (box) box.classList.toggle('hidden', radio.value !== '0');
        });
    });

    // Paso 2 – razón de no interés
    document.querySelectorAll('input[name="interest_level"]').forEach(radio => {
        radio.addEventListener('change', () => {
            const box = document.getElementById('reasonNoInterestBox');
            if (box) box.classList.toggle('hidden', radio.value !== '0');
        });
    });
</script>
@endpush
