@extends('adminlte::page')

@php
    $isAdmin = auth()->user()->hasAnyRole(['Administrador', 'Admin']);
    $isCoordVentas = auth()->user()->hasRole('Coord. Ventas');
@endphp

@section('title', $isAdmin ? 'Gestión de Propuestas Estratégicas' : 'Canal Privado de Propuestas Estratégicas')

@section('content_header')
    <h1>{{ $isAdmin ? 'Gestión de Propuestas Estratégicas' : 'Canal Privado de Propuestas Estratégicas' }}</h1>
@stop

@section('content')
<div class="container-fluid">
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    {{-- Mensaje distinto según rol --}}
    @if($isCoordVentas)
        <div class="card mb-3">
            <div class="card-body">
                <p class="mb-2"><strong>Mensaje introductorio</strong></p>
                <p class="mb-2">
                    En esta red valoramos a quienes no solo ejecutan, sino que piensan, proponen y construyen.
                </p>
                <p class="mb-2">
                    Este espacio ha sido creado para que puedas compartir ideas, observaciones o mejoras de forma directa y confidencial.
                    Lo que escribas aquí será visible únicamente para ti y para el equipo directivo encargado de evaluar propuestas estratégicas.
                    Nadie más tendrá acceso.
                </p>
                <p class="mb-2">
                    Las mejores organizaciones crecen gracias a quienes se atreven a aportar con criterio.
                </p>
                <p class="mb-2">
                    Tu experiencia en el terreno, tu contacto con clientes y tu visión operativa contienen información que puede marcar la diferencia.
                </p>
                <p class="mb-2">
                    Si detectas una oportunidad de mejora, una optimización en procesos, una nueva línea comercial o una inquietud relevante, este es el lugar adecuado.
                </p>
                <p class="mb-2">
                    Cada propuesta es leída, analizada y respondida.
                </p>
                <p class="mb-0">
                    La excelencia se mide en resultados de ventas y visión a largo plazo.
                </p>
            </div>
        </div>
    @endif

    @if($isAdmin)
        <div class="card mb-3">
            <div class="card-body">
                <p class="mb-0">
                    Desde esta vista puedes gestionar todas las propuestas estratégicas enviadas por el equipo de Coordinación de Ventas.
                </p>
            </div>
        </div>
    @endif

    {{-- Resumen superior --}}
    <div class="card mb-3">
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center">
            <div>
                <strong>Total visibles:</strong> {{ $suggestions->total() }}

                @if($isCoordVentas)
                    <span class="ml-3"><strong>Mis propuestas:</strong> {{ $myCount }}</span>
                @endif
            </div>

            <div class="mt-2 mt-md-0">
                @if($isCoordVentas)
                    <a href="{{ route('strategic-suggestions.create') }}" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Nueva propuesta
                    </a>
                @endif
            </div>
        </div>
    </div>

    {{-- Filtros --}}
    <div class="card">
        <div class="card-body">
            <form method="GET" action="{{ route('strategic-suggestions.index') }}" class="row">
                <div class="col-md-6 mb-2">
                    <input
                        type="text"
                        name="q"
                        value="{{ $q }}"
                        class="form-control"
                        placeholder="{{ $isAdmin ? 'Buscar por asunto o contenido...' : 'Buscar en mis propuestas...' }}"
                    >
                </div>

                <div class="col-md-3 mb-2">
                    <select name="status" class="form-control">
                        <option value="">Todos los estados</option>
                        <option value="recibida" {{ $status === 'recibida' ? 'selected' : '' }}>Recibida</option>
                        <option value="en_revision" {{ $status === 'en_revision' ? 'selected' : '' }}>En revisión</option>
                        <option value="respondida" {{ $status === 'respondida' ? 'selected' : '' }}>Respondida</option>
                        <option value="cerrada" {{ $status === 'cerrada' ? 'selected' : '' }}>Cerrada</option>
                    </select>
                </div>

                <div class="col-md-3 mb-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Filtrar
                    </button>
                    <a href="{{ route('strategic-suggestions.index') }}" class="btn btn-secondary">
                        Limpiar
                    </a>
                </div>
            </form>
        </div>
    </div>

    {{-- Tabla --}}
    <div class="card mt-3">
        <div class="table-responsive">
            <table class="table table-bordered table-hover mb-0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Asunto</th>

                        @if($isAdmin)
                            <th>Coord. Ventas</th>
                        @endif

                        <th>Estado</th>
                        <th>Fecha</th>
                        <th>Respuestas</th>
                        <th style="width:120px;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($suggestions as $item)
                        <tr>
                            <td>{{ $item->id }}</td>
                            <td>{{ $item->subject }}</td>

                            @if($isAdmin)
                                <td>{{ $item->user->name ?? 'N/A' }}</td>
                            @endif

                            <td>
                                @php
                                    $badge = match($item->status) {
                                        'recibida' => 'secondary',
                                        'en_revision' => 'warning',
                                        'respondida' => 'info',
                                        'cerrada' => 'success',
                                        default => 'secondary'
                                    };
                                @endphp
                                <span class="badge badge-{{ $badge }}">{{ $item->statusLabel() }}</span>
                            </td>

                            <td>{{ optional($item->submitted_at)->format('Y-m-d H:i') }}</td>
                            <td>{{ $item->replies_count }}</td>
                            <td>
                                <a href="{{ route('strategic-suggestions.show', $item) }}" class="btn btn-sm btn-primary">
                                    Ver
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $isAdmin ? 7 : 6 }}" class="text-center text-muted">
                                {{ $isAdmin ? 'No hay propuestas registradas.' : 'Aún no has enviado propuestas.' }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="card-footer">
            {{ $suggestions->withQueryString()->links() }}
        </div>
    </div>
</div>
@stop

@section('css')
    <link rel="stylesheet" href="{{ asset('css/sefar.css') }}">
@stop
