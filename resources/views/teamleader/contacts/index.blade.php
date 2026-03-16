{{-- resources/views/tl/contacts/index.blade.php --}}
@extends('adminlte::page')

@section('title', 'Contactos TL')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h1><i class="fas fa-address-book mr-2"></i>Contactos Teamleader</h1>
        <div>
            @if($lastSync)
                <small class="text-muted mr-3">
                    Último sync:
                    <strong>{{ $lastSync->finished_at?->diffForHumans() ?? 'en curso...' }}</strong>
                    — {{ number_format($lastSync->processed) }} procesados
                    @if($lastSync->failed > 0)
                        <span class="text-danger">/ {{ $lastSync->failed }} errores</span>
                    @endif
                </small>
            @endif
        </div>
    </div>
@endsection

@section('content')
<div class="card card-outline card-primary">
    <div class="card-header">
        {{-- Filtros --}}
        <form method="GET" action="{{ route('teamleader.contacts.index') }}" class="form-inline flex-wrap" style="gap:.5rem">
            <input
                type="text"
                name="search"
                value="{{ request('search') }}"
                class="form-control form-control-sm"
                placeholder="Buscar nombre, email, pasaporte..."
                style="min-width:260px"
            >

            <select name="status" class="form-control form-control-sm">
                <option value="">Todos los estados</option>
                <option value="active"  {{ request('status') === 'active'  ? 'selected' : '' }}>Activo</option>
                <option value="deleted" {{ request('status') === 'deleted' ? 'selected' : '' }}>Eliminado</option>
            </select>

            <select name="tag" class="form-control form-control-sm">
                <option value="">Todos los tags</option>
                @foreach($allTags as $tag)
                    <option value="{{ $tag }}" {{ request('tag') === $tag ? 'selected' : '' }}>
                        {{ $tag }}
                    </option>
                @endforeach
            </select>

            <button type="submit" class="btn btn-sm btn-primary">
                <i class="fas fa-search"></i> Filtrar
            </button>
            <a href="{{ route('teamleader.contacts.index') }}" class="btn btn-sm btn-secondary">
                <i class="fas fa-times"></i> Limpiar
            </a>
        </form>

        <div class="card-tools">
            <span class="badge badge-info">
                {{ number_format($contacts->total()) }} contactos
            </span>
        </div>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0">
                <thead class="thead-light">
                    <tr>
                        <th>Nombre</th>
                        <th>Email</th>
                        <th>Teléfono</th>
                        <th>Pasaporte</th>
                        <th>Tags</th>
                        <th>Estado</th>
                        <th>Actualizado TL</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($contacts as $contact)
                    <tr>
                        <td>
                            <a href="{{ route('teamleader.contacts.show', $contact->id) }}" class="font-weight-bold">
                                {{ $contact->full_name ?: '(Sin nombre)' }}
                            </a>
                        </td>
                        <td>
                            @if($contact->email)
                                <a href="mailto:{{ $contact->email }}">{{ $contact->email }}</a>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>{{ $contact->phone ?? '—' }}</td>
                        <td>
                            @if($contact->passport)
                                <code>{{ $contact->passport }}</code>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            @foreach(array_slice($contact->tags ?? [], 0, 3) as $tag)
                                <span class="badge badge-light border">{{ $tag }}</span>
                            @endforeach
                            @if(count($contact->tags ?? []) > 3)
                                <span class="badge badge-secondary">+{{ count($contact->tags) - 3 }}</span>
                            @endif
                        </td>
                        <td>
                            @if($contact->status === 'active')
                                <span class="badge badge-success">Activo</span>
                            @else
                                <span class="badge badge-danger">{{ $contact->status }}</span>
                            @endif
                        </td>
                        <td>
                            <small>{{ $contact->tl_updated_at?->format('d/m/Y') ?? '—' }}</small>
                        </td>
                        <td>
                            <a href="{{ route('teamleader.contacts.show', $contact->id) }}"
                               class="btn btn-xs btn-outline-primary">
                                <i class="fas fa-eye"></i>
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">
                            <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                            No se encontraron contactos
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($contacts->hasPages())
    <div class="card-footer">
        {{ $contacts->links() }}
    </div>
    @endif
</div>
@endsection

@section('css')
    <link rel="stylesheet" href="{{ asset('css/sefar.css') }}">
@stop

@section('js')
@stop
