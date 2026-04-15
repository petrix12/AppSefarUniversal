{{-- resources/views/tasks/show.blade.php --}}
@extends('adminlte::page')

@section('title', 'Detalle de tarea')

@push('css')
<link rel="stylesheet" href="{{ asset('css/sefar.css') }}">
<style>
    .step-card { transition: transform .2s ease; }
    .step-card:hover { transform: translateY(-1px); }

    .radio-option {
        display: flex;
        align-items: flex-start;
        gap: .75rem;
        padding: .875rem 1rem;
        border: 2px solid #e5e7eb;
        border-radius: .75rem;
        cursor: pointer;
        transition: border-color .15s ease, background .15s ease;
        background: #fff;
        margin-bottom: 0;  /* override Bootstrap label margin */
    }
    .radio-option:hover { border-color: #93c5fd; background: #eff6ff; }
    .radio-option input[type="radio"] {
        margin-top: 2px;
        accent-color: #3b82f6;
        width: 1rem;
        height: 1rem;
        flex-shrink: 0;
    }
    .radio-option.is-checked-blue  { border-color: #3b82f6; background: #eff6ff; }
    .radio-option.is-checked-red   { border-color: #ef4444; background: #fef2f2; }

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

    .step-badge {
        display: inline-flex;
        align-items: center;
        gap: .4rem;
        font-size: .7rem;
        font-weight: 700;
        letter-spacing: .08em;
        text-transform: uppercase;
        margin-bottom: .4rem;
    }
    .step-badge .step-num {
        width: 1.4rem; height: 1.4rem;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: .65rem;
        font-weight: 800;
    }

    /* Tarjeta base */
    .detail-card {
        background: #fff;
        border: 1px solid #f0f0f0;
        border-radius: 1rem;
        box-shadow: 0 1px 4px rgba(0,0,0,.05);
    }

    /* Separador sutil en la dl */
    .progress-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: .55rem 0;
        border-bottom: 1px solid #f9fafb;
        font-size: .85rem;
    }
    .progress-row:last-child { border-bottom: none; }
    .progress-row dt { color: #9ca3af; }
    .progress-row dd { font-weight: 600; color: #1f2937; margin: 0; }

    /* Input estilo moderno */
    .modern-input {
        width: 100%;
        border: 1px solid #e5e7eb;
        border-radius: .75rem;
        padding: .6rem 1rem;
        font-size: .875rem;
        background: #f9fafb;
        transition: background .15s, border-color .15s, box-shadow .15s;
        color: #111827;
    }
    .modern-input:focus {
        outline: none;
        background: #fff;
        border-color: #93c5fd;
        box-shadow: 0 0 0 3px rgba(59,130,246,.15);
    }
    .modern-input::placeholder { color: #d1d5db; }

    /* Botones */
    .btn-flow {
        width: 100%;
        padding: .75rem 1rem;
        border-radius: .75rem;
        font-size: .875rem;
        font-weight: 700;
        border: none;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: .5rem;
        transition: filter .15s, transform .1s;
    }
    .btn-flow:hover  { filter: brightness(1.08); }
    .btn-flow:active { transform: scale(.98); }
    .btn-blue  { background: #2563eb; color: #fff; box-shadow: 0 2px 8px rgba(37,99,235,.25); }
    .btn-violet{ background: #7c3aed; color: #fff; box-shadow: 0 2px 8px rgba(124,58,237,.25); }
    .btn-green { background: #059669; color: #fff; box-shadow: 0 2px 8px rgba(5,150,105,.25); }

    /* Alerta */
    .alert-banner {
        display: flex;
        align-items: flex-start;
        gap: .75rem;
        padding: .875rem 1rem;
        border-radius: .75rem;
        font-size: .875rem;
        font-weight: 500;
    }
    .alert-banner i { margin-top: 1px; font-size: 1rem; }
</style>
@endpush

@section('content')
<div style="background:#f8fafc; min-height:100vh; padding: 2rem 1rem;">
<div style="max-width: 560px; margin: 0 auto;">

    {{-- ══ CABECERA ══════════════════════════════════════════════ --}}
    <div class="detail-card p-4 mb-4">

        {{-- Breadcrumb --}}
        <a href="{{ route('tasks.index') }}"
           style="display:inline-flex; align-items:center; gap:.4rem;
                  font-size:.78rem; color:#9ca3af; text-decoration:none;
                  margin-bottom:1rem; transition:color .15s;"
           onmouseover="this.style.color='#2563eb'"
           onmouseout="this.style.color='#9ca3af'">
            <i class="fas fa-chevron-left" style="font-size:.7rem;"></i>
            Mis tareas
        </a>

        <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:1rem;">
            <div style="min-width:0;">
                <h1 style="font-size:1.15rem; font-weight:800; color:#111827;
                            margin:0 0 .5rem; line-height:1.3;">
                    {{ $task->title }}
                </h1>
                <div>
                    <p style="font-size:.875rem; color:#374151; margin:0;">
                        {{ $task->description ?? 'Sin descripción' }}
                    </p>
                </div>
                <div style="display:flex; flex-wrap:wrap; gap:.5rem .75rem; font-size:.8rem; color:#6b7280;">
                    @if ( $task->contact)
                    <span>
                        <i class="fas fa-user" style="color:#d1d5db; margin-right:.3rem;"></i>
                        <strong style="color:#374151;">{{ $task->contact?->name ?? '—' }}</strong>
                    </span>
                    @endif
                    <span style="color:#e5e7eb;">|</span>
                    <span>
                        <i class="fas fa-calendar-alt" style="color:#d1d5db; margin-right:.3rem;"></i>
                        Vence {{ $task->due_date->format('d/m/Y') }}
                    </span>
                </div>
                @if ( $task->contact)
                <div style="margin-top:.5rem;">

                    <a href="{{ url('/users/' . $task->contact?->id . '/edit') }}"
                    target="_blank"
                    style="
                            display:inline-flex;
                            align-items:center;
                            gap:.4rem;
                            font-size:.75rem;
                            font-weight:600;
                            color:#2563eb;
                            text-decoration:none;
                            padding:.25rem .5rem;
                            border-radius:.4rem;
                            background:#eff6ff;
                            border:1px solid #dbeafe;
                    "
                    onmouseover="this.style.background='#dbeafe'"
                    onmouseout="this.style.background='#eff6ff'"
                    >
                        <i class="fas fa-external-link-alt" style="font-size:.7rem;"></i>
                        Ir al COS del Cliente
                    </a>

                </div>
                @endif
            </div>

            {{-- Badge estado --}}
            @php
                [$badgeBg, $badgeColor, $dotColor] = match($task->status) {
                    'pending'     => ['#fffbeb', '#b45309', '#f59e0b'],
                    'in_progress' => ['#eff6ff', '#1d4ed8', '#3b82f6'],
                    'completed'   => ['#ecfdf5', '#065f46', '#10b981'],
                    'canceled'    => ['#fef2f2', '#b91c1c', '#ef4444'],
                    default       => ['#f9fafb', '#6b7280', '#9ca3af'],
                };
                $badgeLabel = match($task->status) {
                    'pending'     => 'Pendiente',
                    'in_progress' => 'En progreso',
                    'completed'   => 'Completada',
                    'canceled'    => 'Cancelada',
                    default       => $task->status,
                };
            @endphp
            <span style="display:inline-flex; align-items:center; gap:.4rem;
                         padding:.35rem .85rem; border-radius:999px; white-space:nowrap;
                         font-size:.75rem; font-weight:700; flex-shrink:0;
                         background:{{ $badgeBg }}; color:{{ $badgeColor }};">
                <span style="width:.5rem; height:.5rem; border-radius:50%;
                              background:{{ $dotColor }}; display:inline-block;"></span>
                {{ $badgeLabel }}
            </span>
        </div>
    </div>

    {{-- ══ ALERTAS ════════════════════════════════════════════════ --}}
    @if(session('success'))
    <div class="alert-banner mb-4"
         style="background:#ecfdf5; border:1px solid #a7f3d0; color:#065f46;">
        <i class="fas fa-check-circle" style="color:#10b981;"></i>
        <span>{{ session('success') }}</span>
    </div>
    @endif
    @if(session('error'))
    <div class="alert-banner mb-4"
         style="background:#fef2f2; border:1px solid #fecaca; color:#b91c1c;">
        <i class="fas fa-exclamation-circle" style="color:#ef4444;"></i>
        <span>{{ session('error') }}</span>
    </div>
    @endif

    {{-- ══ PROGRESO REGISTRADO ════════════════════════════════════ --}}
    @php
        $hasProgress = $task->call_effective !== null
                    || $task->reason_no_effective
                    || $task->interest_level !== null
                    || $task->reason_no_interest
                    || $task->product_of_interest
                    || $task->follow_up_date;
    @endphp
    @if($hasProgress)
    <div class="detail-card p-4 mb-4">
        <p style="font-size:.7rem; font-weight:700; letter-spacing:.08em;
                  text-transform:uppercase; color:#9ca3af; margin-bottom:.75rem;">
            Progreso registrado
        </p>
        <dl style="margin:0;">
            @if($task->call_effective !== null)
            <div class="progress-row">
                <dt><i class="fas fa-phone-alt mr-1"></i> Llamada efectiva</dt>
                <dd style="color:{{ $task->call_effective ? '#059669' : '#dc2626' }};">
                    {{ $task->call_effective ? 'Sí' : 'No' }}
                </dd>
            </div>
            @endif
            @if($task->reason_no_effective)
            <div class="progress-row">
                <dt style="padding-left:.75rem;">↳ Motivo</dt>
                <dd style="font-weight:400; color:#374151; text-align:right; max-width:60%;">
                    {{ $task->reason_no_effective }}
                </dd>
            </div>
            @endif
            @if($task->interest_level !== null)
            <div class="progress-row">
                <dt><i class="fas fa-comment-dots mr-1"></i> Mostró interés</dt>
                <dd style="color:{{ $task->interest_level ? '#059669' : '#dc2626' }};">
                    {{ $task->interest_level ? 'Sí' : 'No' }}
                </dd>
            </div>
            @endif
            @if($task->reason_no_interest)
            <div class="progress-row">
                <dt style="padding-left:.75rem;">↳ Motivo</dt>
                <dd style="font-weight:400; color:#374151; text-align:right; max-width:60%;">
                    {{ $task->reason_no_interest }}
                </dd>
            </div>
            @endif
            @if($task->product_of_interest)
            <div class="progress-row">
                <dt><i class="fas fa-box mr-1"></i> Producto</dt>
                <dd>{{ $task->product_of_interest }}</dd>
            </div>
            @endif
            @if($task->follow_up_date)
            <div class="progress-row">
                <dt><i class="fas fa-calendar-check mr-1"></i> Seguimiento</dt>
                <dd style="color:#2563eb;">{{ $task->follow_up_date->format('d/m/Y') }}</dd>
            </div>
            @endif
        </dl>
    </div>
    @endif

    {{-- ══ FLUJO DE PASOS ════════════════════════════════════════ --}}
    @if(! $task->isClosed())

        {{-- ── PASO 1 ──────────────────────────────────────────── --}}
        @if($task->call_effective === null)
        <div class="detail-card p-4 step-card">
            <div class="step-badge" style="color:#2563eb;">
                <span class="step-num" style="background:#dbeafe; color:#1d4ed8;">1</span>
                Resultado de llamada
            </div>
            <h2 style="font-size:1rem; font-weight:700; color:#111827; margin:0 0 1.25rem;">
                ¿La llamada fue efectiva?
            </h2>

            <form method="POST" action="{{ route('tasks.submitFlow', $task) }}">
                @csrf
                <input type="hidden" name="step" value="call_result">

                <div style="display:flex; flex-direction:column; gap:.6rem; margin-bottom:1.25rem;">
                    <label class="radio-option" id="opt-yes-call">
                        <input type="radio" name="call_effective" value="1"
                               {{ old('call_effective') === '1' ? 'checked' : '' }}>
                        <div>
                            <p style="font-size:.875rem; font-weight:600; color:#111827; margin:0;">
                                Sí, fue efectiva
                            </p>
                            <p style="font-size:.75rem; color:#9ca3af; margin:.15rem 0 0;">
                                El contacto respondió y conversamos
                            </p>
                        </div>
                    </label>
                    <label class="radio-option" id="opt-no-call">
                        <input type="radio" name="call_effective" value="0"
                               {{ old('call_effective') === '0' ? 'checked' : '' }}>
                        <div>
                            <p style="font-size:.875rem; font-weight:600; color:#111827; margin:0;">
                                No fue efectiva
                            </p>
                            <p style="font-size:.75rem; color:#9ca3af; margin:.15rem 0 0;">
                                No contestó, número incorrecto, etc.
                            </p>
                        </div>
                    </label>
                </div>

                <div id="reasonBox"
                     class="field-reveal {{ old('call_effective') === '0' ? 'visible' : '' }}"
                     style="margin-bottom:1rem;">
                    <label style="display:block; font-size:.75rem; font-weight:700;
                                  letter-spacing:.06em; text-transform:uppercase;
                                  color:#6b7280; margin-bottom:.4rem;">
                        Motivo <span style="color:#ef4444;">*</span>
                    </label>
                    <input type="text"
                           name="reason_no_effective"
                           value="{{ old('reason_no_effective') }}"
                           maxlength="255"
                           placeholder="Ej: No contestó, número equivocado…"
                           class="modern-input">
                    @error('reason_no_effective')
                        <p style="color:#ef4444; font-size:.75rem; margin-top:.25rem;">{{ $message }}</p>
                    @enderror
                </div>

                @error('call_effective')
                    <p style="color:#ef4444; font-size:.75rem; margin-bottom:.5rem;">{{ $message }}</p>
                @enderror

                <button type="submit" class="btn-flow btn-blue">
                    <i class="fas fa-arrow-right"></i>
                    Guardar y continuar
                </button>
            </form>
        </div>

        {{-- ── PASO 2 ──────────────────────────────────────────── --}}
        @elseif($task->call_effective && $task->interest_level === null)
        <div class="detail-card p-4 step-card">
            <div class="step-badge" style="color:#7c3aed;">
                <span class="step-num" style="background:#ede9fe; color:#6d28d9;">2</span>
                Nivel de interés
            </div>
            <h2 style="font-size:1rem; font-weight:700; color:#111827; margin:0 0 1.25rem;">
                ¿El contacto mostró interés?
            </h2>

            <form method="POST" action="{{ route('tasks.submitFlow', $task) }}">
                @csrf
                <input type="hidden" name="step" value="interest">

                <div style="display:flex; flex-direction:column; gap:.6rem; margin-bottom:1.25rem;">
                    <label class="radio-option" id="opt-yes-int">
                        <input type="radio" name="interest_level" value="1"
                               {{ old('interest_level') === '1' ? 'checked' : '' }}>
                        <div>
                            <p style="font-size:.875rem; font-weight:600; color:#111827; margin:0;">
                                Sí, mostró interés
                            </p>
                            <p style="font-size:.75rem; color:#9ca3af; margin:.15rem 0 0;">
                                Quiere saber más o evaluar la oferta
                            </p>
                        </div>
                    </label>
                    <label class="radio-option" id="opt-no-int">
                        <input type="radio" name="interest_level" value="0"
                               {{ old('interest_level') === '0' ? 'checked' : '' }}>
                        <div>
                            <p style="font-size:.875rem; font-weight:600; color:#111827; margin:0;">
                                No mostró interés
                            </p>
                            <p style="font-size:.75rem; color:#9ca3af; margin:.15rem 0 0;">
                                Rechazó la oferta o no quiso continuar
                            </p>
                        </div>
                    </label>
                </div>

                <div id="reasonNoInterestBox"
                     class="field-reveal {{ old('interest_level') === '0' ? 'visible' : '' }}"
                     style="margin-bottom:1rem;">
                    <label style="display:block; font-size:.75rem; font-weight:700;
                                  letter-spacing:.06em; text-transform:uppercase;
                                  color:#6b7280; margin-bottom:.4rem;">
                        Motivo <span style="color:#ef4444;">*</span>
                    </label>
                    <input type="text"
                           name="reason_no_interest"
                           value="{{ old('reason_no_interest') }}"
                           maxlength="255"
                           placeholder="Ej: Ya tiene el producto, no le interesa ahora…"
                           class="modern-input">
                    @error('reason_no_interest')
                        <p style="color:#ef4444; font-size:.75rem; margin-top:.25rem;">{{ $message }}</p>
                    @enderror
                </div>

                @error('interest_level')
                    <p style="color:#ef4444; font-size:.75rem; margin-bottom:.5rem;">{{ $message }}</p>
                @enderror

                <button type="submit" class="btn-flow btn-violet">
                    <i class="fas fa-arrow-right"></i>
                    Guardar y continuar
                </button>
            </form>
        </div>

        {{-- ── PASO 3 ──────────────────────────────────────────── --}}
        @elseif($task->call_effective && $task->interest_level)
        <div class="detail-card p-4 step-card">
            <div class="step-badge" style="color:#059669;">
                <span class="step-num" style="background:#d1fae5; color:#065f46;">3</span>
                Producto y seguimiento
            </div>
            <h2 style="font-size:1rem; font-weight:700; color:#111827; margin:0 0 1.25rem;">
                ¿Qué producto le interesa?
            </h2>

            <form method="POST" action="{{ route('tasks.submitFlow', $task) }}">
                @csrf
                <input type="hidden" name="step" value="product_followup">

                <div style="margin-bottom:1rem;">
                    <label style="display:block; font-size:.75rem; font-weight:700;
                                  letter-spacing:.06em; text-transform:uppercase;
                                  color:#6b7280; margin-bottom:.4rem;">
                        Producto de interés <span style="color:#ef4444;">*</span>
                    </label>
                    <input type="text"
                           name="product_of_interest"
                           value="{{ old('product_of_interest') }}"
                           maxlength="255"
                           placeholder="Ej: Seguro de vida, Crédito hipotecario…"
                           class="modern-input">
                    @error('product_of_interest')
                        <p style="color:#ef4444; font-size:.75rem; margin-top:.25rem;">{{ $message }}</p>
                    @enderror
                </div>

                <div style="margin-bottom:1.5rem;">
                    <label style="display:block; font-size:.75rem; font-weight:700;
                                  letter-spacing:.06em; text-transform:uppercase;
                                  color:#6b7280; margin-bottom:.4rem;">
                        Fecha de seguimiento
                        <span style="font-weight:400; font-size:.72rem;
                                     text-transform:none; color:#9ca3af; margin-left:.25rem;">
                            (opcional)
                        </span>
                    </label>
                    <input type="date"
                           name="follow_up_date"
                           value="{{ old('follow_up_date') }}"
                           min="{{ today()->addDay()->toDateString() }}"
                           class="modern-input">
                    <p style="font-size:.72rem; color:#9ca3af; margin-top:.35rem;">
                        <i class="fas fa-info-circle mr-1"></i>
                        Si indicas una fecha se creará una tarea de seguimiento automáticamente.
                    </p>
                    @error('follow_up_date')
                        <p style="color:#ef4444; font-size:.75rem; margin-top:.25rem;">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit" class="btn-flow btn-green">
                    <i class="fas fa-check"></i>
                    Completar tarea
                </button>
            </form>
        </div>
        @endif

    {{-- ══ TAREA CERRADA ══════════════════════════════════════════ --}}
    @else
    <div class="detail-card p-5" style="text-align:center;">
        <span style="display:inline-flex; align-items:center; justify-content:center;
                     width:3rem; height:3rem; border-radius:50%; font-size:1.25rem;
                     background:{{ $badgeBg }}; color:{{ $badgeColor }}; margin-bottom:.75rem;">
            <i class="fas {{ $task->status === 'completed' ? 'fa-check' : 'fa-times' }}"></i>
        </span>
        <p style="font-size:.9rem; font-weight:700; color:#374151; margin:0;">
            Tarea {{ strtolower($badgeLabel) }}
        </p>
        <p style="font-size:.8rem; color:#9ca3af; margin:.25rem 0 0;">
            Esta tarea ya no admite más cambios.
        </p>
    </div>
    @endif

</div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    // ── Highlight de tarjeta radio seleccionada ─────────────────
    function bindRadioHighlight(radioName, yesId, noId) {
        document.querySelectorAll(`input[name="${radioName}"]`).forEach(radio => {
            // Aplicar estado inicial
            applyHighlight(radioName, yesId, noId);

            radio.addEventListener('change', () => {
                applyHighlight(radioName, yesId, noId);
            });
        });
    }

    function applyHighlight(radioName, yesId, noId) {
        const radios = document.querySelectorAll(`input[name="${radioName}"]`);
        const yesEl = document.getElementById(yesId);
        const noEl  = document.getElementById(noId);
        if (!yesEl || !noEl) return;

        // Limpiar
        [yesEl, noEl].forEach(el => {
            el.style.borderColor = '#e5e7eb';
            el.style.background  = '#fff';
        });

        radios.forEach(r => {
            if (!r.checked) return;
            const target = r.value === '1' ? yesEl : noEl;
            target.style.borderColor = r.value === '1' ? '#3b82f6' : '#ef4444';
            target.style.background  = r.value === '1' ? '#eff6ff' : '#fef2f2';
        });
    }

    // ── Reveal de campo condicional ─────────────────────────────
    function bindReveal(radioName, boxId) {
        document.querySelectorAll(`input[name="${radioName}"]`).forEach(radio => {
            radio.addEventListener('change', () => {
                const box = document.getElementById(boxId);
                if (!box) return;
                const show = radio.value === '0';
                box.classList.toggle('visible', show);
                if (show) setTimeout(() => box.querySelector('input')?.focus(), 320);
            });
        });
    }

    bindRadioHighlight('call_effective',  'opt-yes-call', 'opt-no-call');
    bindRadioHighlight('interest_level',  'opt-yes-int',  'opt-no-int');
    bindReveal('call_effective', 'reasonBox');
    bindReveal('interest_level', 'reasonNoInterestBox');
})();
</script>
@endpush
