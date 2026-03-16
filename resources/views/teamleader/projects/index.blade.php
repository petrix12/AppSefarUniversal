@extends('adminlte::page')

@section('title', 'Proyectos | Teamleader')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <small class="text-muted text-uppercase font-weight-bold">Teamleader</small>
            <h1 class="m-0">Proyectos</h1>
        </div>
        <span class="badge badge-primary" style="font-size: 0.9rem; padding: 8px 16px;">
            {{ $projects->total() }} proyectos
        </span>
    </div>
@stop

@section('content')

    {{-- FILTROS --}}
    <div class="card card-outline card-primary mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('teamleader.projects.index') }}">
                <div class="row align-items-end">

                    <div class="col-md-4 mb-2">
                        <label class="text-muted small mb-1">Buscar por nombre</label>
                        <input
                            type="text"
                            name="search"
                            value="{{ request('search') }}"
                            placeholder="Ej: María García..."
                            class="form-control form-control-sm"
                        >
                    </div>

                    <div class="col-md-2 mb-2">
                        <label class="text-muted small mb-1">Estado</label>
                        <select name="status" class="form-control form-control-sm">
                            <option value="">Todos</option>
                            @foreach($statuses as $status)
                                <option value="{{ $status }}" {{ request('status') === $status ? 'selected' : '' }}>
                                    {{ ucfirst(str_replace('_', ' ', $status)) }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-2 mb-2">
                        <label class="text-muted small mb-1">Tipo de cliente</label>
                        <select name="customer_type" class="form-control form-control-sm">
                            <option value="">Todos</option>
                            <option value="contact" {{ request('customer_type') === 'contact' ? 'selected' : '' }}>Contacto</option>
                            <option value="company" {{ request('customer_type') === 'company' ? 'selected' : '' }}>Empresa</option>
                        </select>
                    </div>

                    <div class="col-md-4 mb-2">
                        <label class="text-muted small mb-1">&nbsp;</label>
                        <div class="d-flex">
                            <button type="submit" class="btn btn-primary btn-sm mr-2">
                                <i class="fas fa-search mr-1"></i> Filtrar
                            </button>
                            <a href="{{ route('teamleader.projects.index') }}" class="btn btn-secondary btn-sm">
                                <i class="fas fa-times mr-1"></i> Limpiar
                            </a>
                        </div>
                    </div>

                </div>
            </form>
        </div>
    </div>

    {{-- TABLA --}}
    <div class="card card-outline card-primary">
        <div class="card-body p-0">
            <table class="table table-hover table-striped mb-0">
                <thead class="thead-light">
                    <tr>
                        <th>Proyecto</th>
                        <th>Estado</th>
                        <th>Tipo cliente</th>
                        <th>Inicio</th>
                        <th>Vencimiento</th>
                        <th>Presupuesto</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($projects as $project)
                        @php
                            $badgeColors = [
                                'active'    => 'success',
                                'on_hold'   => 'warning',
                                'cancelled' => 'danger',
                                'completed' => 'primary',
                            ];
                            $badge = $badgeColors[$project->status] ?? 'secondary';
                        @endphp
                        <tr>

                            <td>
                                <span class="font-weight-bold">{{ $project->title }}</span>
                                <br>
                                <small class="text-muted" style="font-family: monospace; font-size: 0.7rem;">
                                    {{ $project->id }}
                                </small>
                            </td>

                            <td>
                                <span class="badge badge-{{ $badge }}">
                                    {{ ucfirst(str_replace('_', ' ', $project->status)) }}
                                </span>
                            </td>

                            <td class="text-capitalize">
                                {{ $project->customer_type ?? '—' }}
                            </td>

                            <td>
                                {{ $project->starts_on
                                    ? \Carbon\Carbon::parse($project->starts_on)->format('d/m/Y')
                                    : '—' }}
                            </td>

                            <td>
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
                            </td>

                            <td>
                                @if($project->budget_amount)
                                    {{ number_format($project->budget_amount, 2) }}
                                    {{ $project->budget_currency }}
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>

                            <td class="text-right">
                                <a href="{{ route('teamleader.projects.show', $project->id) }}"
                                   class="btn btn-xs btn-outline-primary">
                                    <i class="fas fa-eye mr-1"></i> Ver
                                </a>
                            </td>

                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-5">
                                <i class="fas fa-folder-open fa-2x mb-2 d-block"></i>
                                No se encontraron proyectos.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($projects->hasPages())
            <div class="card-footer">
                {{ $projects->links() }}
            </div>
        @endif
    </div>

@stop

@section('css')
    <link rel="stylesheet" href="{{ asset('css/sefar.css') }}">
@stop

@section('js')
@stop
