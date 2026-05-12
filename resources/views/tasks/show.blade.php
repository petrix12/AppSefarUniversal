{{-- resources/views/tasks/show.blade.php --}}
@extends('adminlte::page')

@section('title', 'Detalle de tarea')

@push('css')
<link rel="stylesheet" href="{{ asset('css/sefar.css') }}">
<style>
    .task-shell {
        background: #f8fafc;
        min-height: 100vh;
        padding: 2rem 1rem;
    }
    .task-wrap {
        max-width: 620px;
        margin: 0 auto;
    }
    .detail-card {
        background: #fff;
        border: 1px solid #f0f0f0;
        border-radius: 1rem;
        box-shadow: 0 1px 4px rgba(0,0,0,.05);
    }
    .section-label {
        font-size: .7rem;
        font-weight: 700;
        letter-spacing: .08em;
        text-transform: uppercase;
        color: #9ca3af;
        margin-bottom: .75rem;
    }
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
    .choice-row {
        display: flex;
        flex-wrap: wrap;
        gap: .5rem;
    }
    .choice-pill {
        display: inline-flex;
        align-items: center;
        gap: .45rem;
        min-height: 2.45rem;
        padding: .55rem .75rem;
        border: 1px solid #e5e7eb;
        border-radius: .75rem;
        background: #fff;
        color: #374151;
        font-size: .82rem;
        font-weight: 700;
        cursor: pointer;
    }
    .choice-pill input {
        margin: 0;
        accent-color: #2563eb;
    }
    .field-label {
        display: block;
        font-size: .75rem;
        font-weight: 700;
        letter-spacing: .06em;
        text-transform: uppercase;
        color: #6b7280;
        margin-bottom: .4rem;
    }
    .hint-box {
        display: flex;
        align-items: flex-start;
        gap: .6rem;
        border: 1px solid #bfdbfe;
        border-radius: .75rem;
        background: #eff6ff;
        color: #1e40af;
        font-size: .8rem;
        line-height: 1.45;
        padding: .75rem .85rem;
        margin-bottom: 1rem;
    }
    .alert-banner {
        display: flex;
        align-items: flex-start;
        gap: .75rem;
        padding: .875rem 1rem;
        border-radius: .75rem;
        font-size: .875rem;
        font-weight: 500;
    }
    .progress-row {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        padding: .6rem 0;
        border-bottom: 1px solid #f9fafb;
        font-size: .85rem;
    }
    .progress-row:last-child { border-bottom: none; }
    .progress-row dt { color: #9ca3af; }
    .progress-row dd {
        margin: 0;
        color: #1f2937;
        font-weight: 600;
        text-align: right;
        max-width: 65%;
    }
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
    .btn-flow:hover { filter: brightness(1.08); }
    .btn-flow:active { transform: scale(.98); }
    .btn-green {
        background: #059669;
        color: #fff;
        box-shadow: 0 2px 8px rgba(5,150,105,.25);
    }
</style>
@endpush

@section('content')
@php
    $saleStatusOptions = \App\Models\Task::saleStatusOptions();
    $salesTagOptions = \App\Models\Task::salesTagOptions();
    $contactMethodOptions = \App\Models\Task::contactMethodOptions();
    $selectedContactMethods = old('contact_methods', $task->contact_methods ?: ($task->call_effective !== null ? [\App\Models\Task::CONTACT_METHOD_CALL] : []));
    $selectedSalesTags = old('sales_tags', $task->sales_tags ?? []);
    $respondedValue = old('customer_responded', is_null($task->customer_responded) ? null : ($task->customer_responded ? '1' : '0'));
    $interestValue = old('interest_level', is_null($task->interest_level) ? null : ($task->interest_level ? '1' : '0'));
    $hasProgress = !empty($task->contact_methods)
        || $task->customer_responded !== null
        || $task->call_effective !== null
        || $task->reason_no_effective
        || $task->interest_level !== null
        || $task->sale_status
        || !empty($task->sales_tags)
        || $task->reason_no_interest
        || $task->product_of_interest
        || $task->follow_up_date;
@endphp

<div class="task-shell">
<div class="task-wrap">
    <div class="detail-card p-4 mb-4">
        <a href="{{ route('tasks.index') }}"
           style="display:inline-flex; align-items:center; gap:.4rem; font-size:.78rem; color:#9ca3af; text-decoration:none; margin-bottom:1rem;">
            <i class="fas fa-chevron-left" style="font-size:.7rem;"></i>
            Mis tareas
        </a>

        <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:1rem;">
            <div style="min-width:0;">
                <h1 style="font-size:1.15rem; font-weight:800; color:#111827; margin:0 0 .5rem; line-height:1.3;">
                    {{ $task->title }}
                </h1>
                <p style="font-size:.875rem; color:#374151; margin:0;">
                    {{ $task->description ?? 'Sin descripcion' }}
                </p>

                <div style="display:flex; flex-wrap:wrap; align-items:center; gap:.5rem .75rem; font-size:.8rem; color:#6b7280; margin-top:.5rem;">
                    @if($task->contact)
                        <span>
                            <i class="fas fa-user" style="color:#d1d5db; margin-right:.3rem;"></i>
                            <strong style="color:#374151;">{{ $task->contact->name }}</strong>
                        </span>
                        @if($task->contact->email)
                            <span>
                                <i class="fas fa-envelope" style="color:#d1d5db; margin-right:.3rem;"></i>
                                <a href="mailto:{{ $task->contact->email }}" style="color:#2563eb; text-decoration:none;">
                                    {{ $task->contact->email }}
                                </a>
                            </span>
                        @endif
                        @if($task->contact->phone)
                            <span>
                                <i class="fas fa-phone" style="color:#d1d5db; margin-right:.3rem;"></i>
                                <a href="tel:{{ $task->contact->phone }}" style="color:#374151; text-decoration:none;">
                                    {{ $task->contact->phone }}
                                </a>
                            </span>
                        @endif
                    @endif
                    <span>
                        <i class="fas fa-calendar-alt" style="color:#d1d5db; margin-right:.3rem;"></i>
                        Vence {{ $task->due_date->format('d/m/Y') }}
                    </span>
                </div>

                @if($task->contact)
                    <div style="margin-top:.6rem;">
                        <a href="{{ url('/users/' . $task->contact->id . '/edit') }}"
                           target="_blank"
                           style="display:inline-flex; align-items:center; gap:.4rem; font-size:.75rem; font-weight:600; color:#2563eb; text-decoration:none; padding:.25rem .5rem; border-radius:.4rem; background:#eff6ff; border:1px solid #dbeafe;">
                            <i class="fas fa-external-link-alt" style="font-size:.7rem;"></i>
                            Ir al COS del Cliente
                        </a>
                    </div>
                @endif
            </div>

            @php
                [$badgeBg, $badgeColor, $dotColor] = match($task->status) {
                    'pending' => ['#fffbeb', '#b45309', '#f59e0b'],
                    'in_progress' => ['#eff6ff', '#1d4ed8', '#3b82f6'],
                    'completed' => ['#ecfdf5', '#065f46', '#10b981'],
                    'canceled' => ['#fef2f2', '#b91c1c', '#ef4444'],
                    default => ['#f9fafb', '#6b7280', '#9ca3af'],
                };
                $badgeLabel = match($task->status) {
                    'pending' => 'Pendiente',
                    'in_progress' => 'En progreso',
                    'completed' => 'Completada',
                    'canceled' => 'Cancelada',
                    default => $task->status,
                };
            @endphp
            <span style="display:inline-flex; align-items:center; gap:.4rem; padding:.35rem .85rem; border-radius:999px; white-space:nowrap; font-size:.75rem; font-weight:700; flex-shrink:0; background:{{ $badgeBg }}; color:{{ $badgeColor }};">
                <span style="width:.5rem; height:.5rem; border-radius:50%; background:{{ $dotColor }}; display:inline-block;"></span>
                {{ $badgeLabel }}
            </span>
        </div>
    </div>

    @if(session('success'))
        <div class="alert-banner mb-4" style="background:#ecfdf5; border:1px solid #a7f3d0; color:#065f46;">
            <i class="fas fa-check-circle" style="color:#10b981;"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if(session('error'))
        <div class="alert-banner mb-4" style="background:#fef2f2; border:1px solid #fecaca; color:#b91c1c;">
            <i class="fas fa-exclamation-circle" style="color:#ef4444;"></i>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    <div class="detail-card p-4 mb-4">
        <p class="section-label">Gestion comercial</p>

        <div class="hint-box">
            <i class="fas fa-info-circle" style="margin-top:.15rem;"></i>
            <span>
                Si contactaste al cliente por WhatsApp o email/correo, la tarea cuenta como efectiva aunque aun estes esperando respuesta. Si solo llamaste y no respondio, quedara como llamada sin respuesta, pero cerrar esta tarea no reasigna el cliente automaticamente.
            </span>
        </div>

        <form method="POST" action="{{ route('tasks.updateSalesTracking', $task) }}">
            @csrf
            @method('PUT')

            <div style="margin-bottom:1rem;">
                <label class="field-label">Por donde lo contactaste? <span style="color:#ef4444;">*</span></label>
                <div class="choice-row">
                    @foreach($contactMethodOptions as $value => $meta)
                        <label class="choice-pill">
                            <input type="checkbox" name="contact_methods[]" value="{{ $value }}" {{ in_array($value, $selectedContactMethods ?? [], true) ? 'checked' : '' }}>
                            <i class="fas fa-{{ $meta['icon'] }}"></i>
                            <span>{{ $meta['label'] }}</span>
                        </label>
                    @endforeach
                </div>
                @error('contact_methods')
                    <p style="color:#ef4444; font-size:.75rem; margin-top:.25rem;">{{ $message }}</p>
                @enderror
            </div>

            <div style="margin-bottom:1rem;">
                <label class="field-label">Te respondio? <span style="color:#ef4444;">*</span></label>
                <div class="choice-row">
                    <label class="choice-pill">
                        <input type="radio" name="customer_responded" value="1" {{ $respondedValue === '1' ? 'checked' : '' }}>
                        <span>Si respondio</span>
                    </label>
                    <label class="choice-pill">
                        <input type="radio" name="customer_responded" value="0" {{ $respondedValue === '0' ? 'checked' : '' }}>
                        <span>No, esperando respuesta</span>
                    </label>
                </div>
                @error('customer_responded')
                    <p style="color:#ef4444; font-size:.75rem; margin-top:.25rem;">{{ $message }}</p>
                @enderror
            </div>

            <div style="margin-bottom:1rem;">
                <label class="field-label">Estatus de la venta</label>
                <select name="sale_status" class="modern-input">
                    <option value="">Sin estatus</option>
                    @foreach($saleStatusOptions as $value => $label)
                        <option value="{{ $value }}" {{ old('sale_status', $task->sale_status) === $value ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
                @error('sale_status')
                    <p style="color:#ef4444; font-size:.75rem; margin-top:.25rem;">{{ $message }}</p>
                @enderror
            </div>

            <div style="margin-bottom:1rem;">
                <label class="field-label">Etiquetas</label>
                <div class="choice-row">
                    @foreach($salesTagOptions as $value => $meta)
                        <label class="choice-pill">
                            <input type="checkbox" name="sales_tags[]" value="{{ $value }}" {{ in_array($value, $selectedSalesTags ?? [], true) ? 'checked' : '' }}>
                            <span>{{ $meta['label'] }}</span>
                        </label>
                    @endforeach
                </div>
                @error('sales_tags')
                    <p style="color:#ef4444; font-size:.75rem; margin-top:.25rem;">{{ $message }}</p>
                @enderror
            </div>

            <div style="margin-bottom:1rem;">
                <label class="field-label">Mostro interes?</label>
                <div class="choice-row">
                    <label class="choice-pill">
                        <input type="radio" name="interest_level" value="1" {{ $interestValue === '1' ? 'checked' : '' }}>
                        <span>Si</span>
                    </label>
                    <label class="choice-pill">
                        <input type="radio" name="interest_level" value="0" {{ $interestValue === '0' ? 'checked' : '' }}>
                        <span>No</span>
                    </label>
                </div>
                @error('interest_level')
                    <p style="color:#ef4444; font-size:.75rem; margin-top:.25rem;">{{ $message }}</p>
                @enderror
            </div>

            <div style="margin-bottom:1rem;">
                <label class="field-label">Producto de interes</label>
                <input type="text" name="product_of_interest" value="{{ old('product_of_interest', $task->product_of_interest) }}" maxlength="255" placeholder="Ej: Sefardi Portugal" class="modern-input">
                @error('product_of_interest')
                    <p style="color:#ef4444; font-size:.75rem; margin-top:.25rem;">{{ $message }}</p>
                @enderror
            </div>

            <div style="margin-bottom:1rem;">
                <label class="field-label">Motivo / observacion si no respondio</label>
                <input type="text" name="reason_no_effective" value="{{ old('reason_no_effective', $task->reason_no_effective) }}" maxlength="255" placeholder="Ej: llamada sin respuesta, se envio WhatsApp y correo" class="modern-input">
                @error('reason_no_effective')
                    <p style="color:#ef4444; font-size:.75rem; margin-top:.25rem;">{{ $message }}</p>
                @enderror
            </div>

            <div style="margin-bottom:1rem;">
                <label class="field-label">Motivo si no hay interes</label>
                <input type="text" name="reason_no_interest" value="{{ old('reason_no_interest', $task->reason_no_interest) }}" maxlength="255" placeholder="Ej: no desea continuar ahora" class="modern-input">
                @error('reason_no_interest')
                    <p style="color:#ef4444; font-size:.75rem; margin-top:.25rem;">{{ $message }}</p>
                @enderror
            </div>

            <div style="margin-bottom:1.5rem;">
                <label class="field-label">Fecha de seguimiento</label>
                <input type="date" name="follow_up_date" value="{{ old('follow_up_date', optional($task->follow_up_date)->toDateString()) }}" min="{{ today()->addDay()->toDateString() }}" class="modern-input">
                <p style="font-size:.72rem; color:#9ca3af; margin-top:.35rem;">
                    <i class="fas fa-info-circle mr-1"></i>
                    Si indicas una fecha se creara una tarea de seguimiento automaticamente.
                </p>
                @error('follow_up_date')
                    <p style="color:#ef4444; font-size:.75rem; margin-top:.25rem;">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit" class="btn-flow btn-green">
                <i class="fas fa-check"></i>
                {{ $task->isClosed() ? 'Actualizar gestion' : 'Guardar gestion y completar tarea' }}
            </button>
        </form>
    </div>

    @if($hasProgress)
        <div class="detail-card p-4 mb-4">
            <p class="section-label">Progreso registrado</p>
            <dl style="margin:0;">
                <div class="progress-row">
                    <dt><i class="fas fa-share-alt mr-1"></i> Vias usadas</dt>
                    <dd>{{ implode(', ', $task->contactMethodLabels()) ?: 'Sin registrar' }}</dd>
                </div>
                @if($task->customer_responded !== null)
                    <div class="progress-row">
                        <dt><i class="fas fa-reply mr-1"></i> Respondio</dt>
                        <dd style="color:{{ $task->customer_responded ? '#059669' : '#b45309' }};">
                            {{ $task->customer_responded ? 'Si' : 'Esperando respuesta' }}
                        </dd>
                    </div>
                @endif
                @if($task->call_effective !== null)
                    <div class="progress-row">
                        <dt><i class="fas fa-check-circle mr-1"></i> Resultado de gestion</dt>
                        <dd style="color:{{ $task->call_effective ? '#059669' : '#dc2626' }};">
                            {{ $task->call_effective ? 'Efectiva' : 'No efectiva' }}
                        </dd>
                    </div>
                @endif
                @if($task->reason_no_effective)
                    <div class="progress-row">
                        <dt><i class="fas fa-comment-alt mr-1"></i> Observacion</dt>
                        <dd>{{ $task->reason_no_effective }}</dd>
                    </div>
                @endif
                @if($task->interest_level !== null)
                    <div class="progress-row">
                        <dt><i class="fas fa-comment-dots mr-1"></i> Mostro interes</dt>
                        <dd style="color:{{ $task->interest_level ? '#059669' : '#dc2626' }};">
                            {{ $task->interest_level ? 'Si' : 'No' }}
                        </dd>
                    </div>
                @endif
                @if($task->sale_status)
                    <div class="progress-row">
                        <dt><i class="fas fa-chart-line mr-1"></i> Estatus venta</dt>
                        <dd>{{ $task->saleStatusLabel() }}</dd>
                    </div>
                @endif
                @if(!empty($task->sales_tags))
                    <div class="progress-row">
                        <dt><i class="fas fa-tags mr-1"></i> Etiquetas</dt>
                        <dd>
                            @foreach($task->sales_tags as $tag)
                                @if(isset($salesTagOptions[$tag]))
                                    <span class="badge badge-{{ $salesTagOptions[$tag]['class'] }} mb-1">
                                        {{ $salesTagOptions[$tag]['label'] }}
                                    </span>
                                @endif
                            @endforeach
                        </dd>
                    </div>
                @endif
                @if($task->reason_no_interest)
                    <div class="progress-row">
                        <dt><i class="fas fa-comment-slash mr-1"></i> Motivo sin interes</dt>
                        <dd>{{ $task->reason_no_interest }}</dd>
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
</div>
</div>
@endsection
