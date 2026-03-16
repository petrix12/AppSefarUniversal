@extends('adminlte::page')

@section('title', $project->title)

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <small class="text-muted text-uppercase font-weight-bold">
                <a href="{{ route('teamleader.projects.index') }}" class="text-muted">
                    Teamleader / Proyectos
                </a>
            </small>
            <h1 class="m-0">{{ $project->title }}</h1>
        </div>
        @php
            $badgeColors = [
                'active'    => 'success',
                'on_hold'   => 'warning',
                'cancelled' => 'danger',
                'completed' => 'primary',
            ];
            $badge = $badgeColors[$project->status] ?? 'secondary';
        @endphp
        <span class="badge badge-{{ $badge }}" style="font-size: 0.9rem; padding: 8px 16px;">
            {{ ucfirst(str_replace('_', ' ', $project->status)) }}
        </span>
    </div>
@stop

@section('content')

@php
    // ── Helpers de datos ──────────────────────────────────────────────
    $rawData      = is_array($project->raw_data)      ? $project->raw_data      : [];
    $participants = is_array($project->participants)   ? $project->participants  : json_decode($project->participants, true) ?? [];
    $milestones   = is_array($project->milestones)     ? $project->milestones    : json_decode($project->milestones,   true) ?? [];
    $tags         = is_array($project->tags)           ? $project->tags          : json_decode($project->tags,         true) ?? [];

    $customFields = is_array($project->custom_fields)
        ? $project->custom_fields
        : json_decode($project->custom_fields, true) ?? [];

    $customFieldsWithValue = array_filter(
        $customFields,
        fn($cf) => !is_null($cf['value']) && $cf['value'] !== '' && $cf['value'] !== []
    );

    // Datos de budget desde raw_data
    $budget = $rawData['budget'] ?? [];
@endphp

<div class="row">

    {{-- ── COLUMNA IZQUIERDA ───────────────────────────────────────── --}}
    <div class="col-md-8">

        {{-- INFO GENERAL --}}
        <div class="card card-outline card-primary">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-info-circle mr-2"></i> Información General
                </h3>
            </div>
            <div class="card-body">
                <div class="row">

                    <div class="col-md-6 mb-3">
                        <small class="text-muted d-block">ID Teamleader</small>
                        <span style="font-family: monospace; font-size: 0.8rem;">{{ $project->id }}</span>
                    </div>

                    <div class="col-md-6 mb-3">
                        <small class="text-muted d-block">Referencia</small>
                        <span>{{ $rawData['reference'] ?? '—' }}</span>
                    </div>

                    <div class="col-md-6 mb-3">
                        <small class="text-muted d-block">Tipo de cliente</small>
                        <span class="text-capitalize">{{ $project->customer_type ?? '—' }}</span>
                    </div>

                    <div class="col-md-6 mb-3">
                        <small class="text-muted d-block">ID Cliente</small>
                        <span style="font-family: monospace; font-size: 0.8rem;">
                            {{ $project->customer_id ?? '—' }}
                        </span>
                    </div>

                    <div class="col-md-6 mb-3">
                        <small class="text-muted d-block">Fecha de inicio</small>
                        <span>
                            {{ $project->starts_on
                                ? \Carbon\Carbon::parse($project->starts_on)->format('d/m/Y')
                                : '—' }}
                        </span>
                    </div>

                    <div class="col-md-6 mb-3">
                        <small class="text-muted d-block">Fecha de vencimiento</small>
                        @if($project->due_on)
                            @php $due = \Carbon\Carbon::parse($project->due_on); @endphp
                            <span class="{{ $due->isPast() && $project->status === 'active' ? 'text-danger font-weight-bold' : '' }}">
                                {{ $due->format('d/m/Y') }}
                                @if($due->isPast() && $project->status === 'active')
                                    <i class="fas fa-exclamation-circle ml-1"></i>
                                @endif
                            </span>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </div>

                    <div class="col-md-6 mb-3">
                        <small class="text-muted d-block">Sincronizado en</small>
                        <span>{{ \Carbon\Carbon::parse($project->created_at)->format('d/m/Y H:i') }}</span>
                    </div>

                    <div class="col-md-6 mb-3">
                        <small class="text-muted d-block">Última actualización</small>
                        <span>{{ \Carbon\Carbon::parse($project->updated_at)->format('d/m/Y H:i') }}</span>
                    </div>

                    @if($project->description)
                        <div class="col-12">
                            <small class="text-muted d-block">Descripción</small>
                            <p class="mb-0">{{ $project->description }}</p>
                        </div>
                    @endif

                </div>
            </div>
        </div>

        {{-- PRESUPUESTO --}}
        @if(!empty($budget))
            <div class="card card-outline card-success">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-euro-sign mr-2"></i> Presupuesto
                    </h3>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th>Concepto</th>
                                <th class="text-right">Importe</th>
                                <th>Moneda</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $budgetRows = [
                                    'Provisto'     => $budget['provided']   ?? null,
                                    'Asignado'     => $budget['allocated']  ?? null,
                                    'Gastado'      => $budget['spent']['total'] ?? null,
                                    'Restante'     => $budget['remaining']  ?? null,
                                    'Previsto'     => $budget['forecasted'] ?? null,
                                ];
                            @endphp
                            @foreach($budgetRows as $label => $item)
                                @if($item)
                                    <tr>
                                        <td>{{ $label }}</td>
                                        <td class="text-right font-weight-bold">
                                            {{ number_format($item['amount'], 2) }}
                                        </td>
                                        <td>{{ $item['currency'] }}</td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- PARTICIPANTES --}}
        @if(count($participants) > 0)
            <div class="card card-outline card-info">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-users mr-2"></i> Participantes
                    </h3>
                    <div class="card-tools">
                        <span class="badge badge-info">{{ count($participants) }}</span>
                    </div>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th>Rol</th>
                                <th>ID Participante</th>
                                <th>Tipo</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($participants as $participant)
                                @php
                                    $rolColors = [
                                        'decision_maker' => 'warning',
                                        'member'         => 'info',
                                        'follower'       => 'secondary',
                                    ];
                                    $rolBadge = $rolColors[$participant['role']] ?? 'secondary';
                                @endphp
                                <tr>
                                    <td>
                                        <span class="badge badge-{{ $rolBadge }}">
                                            {{ ucfirst(str_replace('_', ' ', $participant['role'])) }}
                                        </span>
                                    </td>
                                    <td style="font-family: monospace; font-size: 0.75rem;">
                                        {{ $participant['participant']['id'] }}
                                    </td>
                                    <td class="text-capitalize">
                                        {{ $participant['participant']['type'] }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- HITOS --}}
        @if(count($milestones) > 0)
            <div class="card card-outline card-warning">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-flag mr-2"></i> Hitos
                    </h3>
                    <div class="card-tools">
                        <span class="badge badge-warning">{{ count($milestones) }}</span>
                    </div>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th>#</th>
                                <th>ID Hito</th>
                                <th>Tipo</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($milestones as $i => $milestone)
                                <tr>
                                    <td>{{ $i + 1 }}</td>
                                    <td style="font-family: monospace; font-size: 0.75rem;">
                                        {{ $milestone['id'] }}
                                    </td>
                                    <td class="text-capitalize">{{ $milestone['type'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

    </div>

    {{-- ── COLUMNA DERECHA ──────────────────────────────────────────── --}}
    <div class="col-md-4">

        {{-- CAMPOS PERSONALIZADOS --}}
        <div class="card card-outline card-secondary">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-tags mr-2"></i> Campos personalizados
                </h3>
                <div class="card-tools">
                    <span class="badge badge-secondary">
                        {{ count($customFieldsWithValue) }} con valor
                    </span>
                </div>
            </div>
            <div class="card-body p-0">
                @if(count($customFieldsWithValue) > 0)
                    <table class="table table-sm mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th style="width: 50%">Campo</th>
                                <th>Valor</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($customFieldsWithValue as $cf)
                                @php
                                    $defId    = $cf['definition']['id'];
                                    $def      = $definitions[$defId] ?? null;
                                    $defLabel = $def?->label ?? $defId;
                                    $defType  = $def?->type  ?? 'text';
                                    $value    = $cf['value'];
                                @endphp
                                <tr>
                                    <td class="font-weight-bold text-muted small">
                                        {{ $defLabel }}
                                    </td>
                                    <td class="small">
                                        @if(is_array($value))
                                            {{ implode(', ', $value) }}
                                        @elseif($defType === 'date')
                                            {{ \Carbon\Carbon::parse($value)->format('d/m/Y') }}
                                        @elseif($defType === 'money')
                                            {{ number_format((float)$value, 2) }} €
                                        @elseif($defType === 'boolean')
                                            <span class="badge badge-{{ $value ? 'success' : 'secondary' }}">
                                                {{ $value ? 'Sí' : 'No' }}
                                            </span>
                                        @else
                                            {{ $value }}
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                        Sin campos con valor
                    </div>
                @endif
            </div>
        </div>

        {{-- ACTUALS --}}
        @php
            $actuals = $rawData['actuals'] ?? [];
        @endphp
        @if(!empty($actuals))
            <div class="card card-outline card-secondary">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-chart-bar mr-2"></i> Actuals
                    </h3>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <tbody>
                            @php
                                $actualRows = [
                                    'Costes'            => $actuals['costs']           ?? null,
                                    'Resultado'         => $actuals['result']          ?? null,
                                    'Facturable'        => $actuals['billable_amount'] ?? null,
                                ];
                            @endphp
                            @foreach($actualRows as $label => $item)
                                @if($item)
                                    <tr>
                                        <td class="text-muted small">{{ $label }}</td>
                                        <td class="text-right font-weight-bold">
                                            {{ number_format($item['amount'], 2) }} {{ $item['currency'] }}
                                        </td>
                                    </tr>
                                @endif
                            @endforeach
                            @if(isset($actuals['profit_percentage']))
                                <tr>
                                    <td class="text-muted small">% Beneficio</td>
                                    <td class="text-right font-weight-bold">
                                        {{ $actuals['profit_percentage'] }}%
                                    </td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- ETIQUETAS --}}
        <div class="card card-outline card-secondary">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-hashtag mr-2"></i> Etiquetas
                </h3>
            </div>
            <div class="card-body">
                @if(count($tags) > 0)
                    @foreach($tags as $tag)
                        <span class="badge badge-light border mr-1 mb-1">{{ $tag }}</span>
                    @endforeach
                @else
                    <span class="text-muted small">Sin etiquetas</span>
                @endif
            </div>
        </div>

        {{-- ACCIONES --}}
        <div class="card card-outline card-secondary">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-cog mr-2"></i> Acciones
                </h3>
            </div>
            <div class="card-body">
                <a href="{{ route('teamleader.projects.index') }}"
                   class="btn btn-secondary btn-block mb-2">
                    <i class="fas fa-arrow-left mr-2"></i> Volver al listado
                </a>
            </div>
        </div>

    </div>
</div>

@stop

@section('css')
    <link rel="stylesheet" href="{{ asset('css/sefar.css') }}">
@stop

@section('js')
@stop
