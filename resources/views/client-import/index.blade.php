@extends('adminlte::page')

@section('title', 'Migrar clientes')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center flex-wrap">
        <div>
            <h1 class="mb-0"><i class="fas fa-file-import mr-2"></i>Migrar clientes a la App</h1>
            <small class="text-muted">Busca un contacto en HubSpot o Teamleader y crea/vincula su usuario local.</small>
        </div>
        <a href="{{ route('crud.users.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-users mr-1"></i>Usuarios
        </a>
    </div>
@endsection

@section('content')
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if($searchError)
        <div class="alert alert-danger">{{ $searchError }}</div>
    @endif

    <div class="card card-outline card-primary">
        <div class="card-header">
            <h3 class="card-title mb-0">Buscar contacto externo</h3>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('client-import.index') }}">
                <div class="row">
                    <div class="col-md-3">
                        <label>Fuente</label>
                        <select name="source" class="form-control">
                            <option value="hubspot" {{ request('source', 'hubspot') === 'hubspot' ? 'selected' : '' }}>HubSpot</option>
                            <option value="teamleader" {{ request('source') === 'teamleader' ? 'selected' : '' }}>Teamleader</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label>Buscar por</label>
                        <select name="search_by" class="form-control">
                            <option value="email" {{ request('search_by', 'email') === 'email' ? 'selected' : '' }}>Email</option>
                            <option value="passport" {{ request('search_by') === 'passport' ? 'selected' : '' }}>Pasaporte</option>
                            <option value="id" {{ request('search_by') === 'id' ? 'selected' : '' }}>ID externo</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label>Valor</label>
                        <input name="search" value="{{ request('search') }}" class="form-control" placeholder="Email, pasaporte o ID">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary btn-block">
                            <i class="fas fa-search mr-1"></i>Buscar
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    @if(request()->filled('search') && !$candidate && !$searchError)
        <div class="alert alert-warning">
            No se encontro ningun contacto con esos datos.
        </div>
    @endif

    @if($candidate)
        <div class="card card-outline card-success">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
                <h3 class="card-title mb-0">
                    <i class="fas fa-user-check mr-1"></i>Contacto encontrado en {{ $candidate['source_label'] }}
                </h3>
                <span class="badge badge-light border">ID: {{ $candidate['external_id'] }}</span>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <dl class="row mb-0">
                            <dt class="col-sm-4">Nombre</dt>
                            <dd class="col-sm-8">{{ $candidate['name'] ?: '-' }}</dd>
                            <dt class="col-sm-4">Email</dt>
                            <dd class="col-sm-8">{{ $candidate['email'] ?: '-' }}</dd>
                            <dt class="col-sm-4">Telefono</dt>
                            <dd class="col-sm-8">{{ $candidate['phone'] ?: '-' }}</dd>
                            <dt class="col-sm-4">Pasaporte</dt>
                            <dd class="col-sm-8">{{ $candidate['passport'] ?: '-' }}</dd>
                        </dl>
                    </div>
                    <div class="col-md-6">
                        <dl class="row mb-0">
                            <dt class="col-sm-4">Servicio</dt>
                            <dd class="col-sm-8">{{ $candidate['servicio'] ?: '-' }}</dd>
                            <dt class="col-sm-4">Pais nac.</dt>
                            <dd class="col-sm-8">{{ $candidate['pais_de_nacimiento'] ?: '-' }}</dd>
                            <dt class="col-sm-4">Ciudad nac.</dt>
                            <dd class="col-sm-8">{{ $candidate['ciudad_de_nacimiento'] ?: '-' }}</dd>
                            <dt class="col-sm-4">Referido</dt>
                            <dd class="col-sm-8">{{ $candidate['referido_por'] ?: '-' }}</dd>
                        </dl>
                    </div>
                </div>

                @if($existingUser)
                    <div class="alert alert-info mt-3 mb-0">
                        Ya existe un usuario local que coincide:
                        <a href="{{ route('crud.users.edit', $existingUser) }}" class="font-weight-bold">
                            {{ $existingUser->name }} #{{ $existingUser->id }}
                        </a>.
                        Puedes vincular los IDs externos y completar campos vacios.
                    </div>
                @elseif(!$candidate['email'])
                    <div class="alert alert-warning mt-3 mb-0">
                        Este contacto no tiene email. No se puede crear usuario local hasta completar ese dato en la fuente.
                    </div>
                @endif
            </div>
            <div class="card-footer d-flex justify-content-end">
                @if($candidate['email'] || $existingUser)
                    <form method="POST" action="{{ route('client-import.store') }}">
                        @csrf
                        <input type="hidden" name="source" value="{{ request('source', 'hubspot') }}">
                        <input type="hidden" name="search_by" value="{{ request('search_by', 'email') }}">
                        <input type="hidden" name="search" value="{{ request('search') }}">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-file-import mr-1"></i>
                            {{ $existingUser ? 'Vincular/actualizar usuario' : 'Migrar a la App' }}
                        </button>
                    </form>
                @endif
            </div>
        </div>
    @endif
@endsection
