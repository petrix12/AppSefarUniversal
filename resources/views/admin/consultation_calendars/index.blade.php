@extends('adminlte::page')

@section('title', 'Calendarios de Consultoria')

@section('content_header')
    <h1>Calendarios de Consultoria</h1>
@stop

@section('content')
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="mb-3 text-right">
        <a href="{{ route('admin.consultation-calendars.create') }}" class="btn btn-primary">
            <i class="fas fa-plus"></i> Nuevo calendario
        </a>
    </div>

    <div class="card">
        <div class="card-body table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Servicio</th>
                        <th>Zona horaria</th>
                        <th>Slot</th>
                        <th>Disponibilidad</th>
                        <th>Estado</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($calendars as $calendar)
                        <tr>
                            <td>{{ $calendar->nombre }}</td>
                            <td>{{ optional($calendar->servicio)->nombre ?? 'General' }}</td>
                            <td>{{ $calendar->timezone }}</td>
                            <td>{{ $calendar->slot_duration_minutes }} min</td>
                            <td>
                                @foreach($calendar->availabilityRules as $rule)
                                    <span class="badge badge-light">
                                        {{ ['Dom','Lun','Mar','Mie','Jue','Vie','Sab'][$rule->weekday] ?? $rule->weekday }}
                                        {{ substr($rule->starts_at, 0, 5) }}-{{ substr($rule->ends_at, 0, 5) }}
                                    </span>
                                @endforeach
                            </td>
                            <td>
                                @if($calendar->activo)
                                    <span class="badge badge-success">Activo</span>
                                @else
                                    <span class="badge badge-secondary">Inactivo</span>
                                @endif
                            </td>
                            <td class="text-right">
                                <a href="{{ route('admin.consultation-calendars.edit', $calendar) }}" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <form action="{{ route('admin.consultation-calendars.destroy', $calendar) }}" method="POST" class="d-inline">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger" onclick="return confirm('Eliminar calendario?')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted">No hay calendarios registrados.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@stop

@section('css')
    <link rel="stylesheet" href="{{ asset('css/sefar.css') }}">
@stop
