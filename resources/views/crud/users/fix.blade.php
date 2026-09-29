@extends('adminlte::page')

@section('title', 'Migrar IDCliente de árbol')

@section('content_header')
    <div class="d-flex flex-wrap justify-content-between align-items-center">
        <div>
            <h1 class="mb-0"><i class="fas fa-project-diagram text-primary mr-2"></i>Migrar IDCliente de árbol</h1>
            <small class="text-muted">Actualiza un árbol completo y sus referencias locales asociadas.</small>
        </div>
    </div>
@stop

@section('content')
    @if ($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    @if (session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

    @php($preview = session('tree_id_migration_preview'))
    @php($result = session('tree_id_migration_result'))

    @if ($result)
        <div class="card card-outline card-success">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-check-circle mr-1"></i>Migración realizada</h3></div>
            <div class="card-body">
                <p class="mb-2">El árbol cambió de <strong>{{ $result['old_id'] }}</strong> a <strong>{{ $result['new_id'] }}</strong>.</p>
                <a class="btn btn-success btn-sm" href="{{ route('arboles.tree.index', ['IDCliente' => $result['new_id']]) }}"><i class="fas fa-sitemap mr-1"></i>Abrir árbol migrado</a>
            </div>
        </div>
    @endif

    <div class="row">
        <div class="col-xl-8">
            <form action="{{ route('fixpassportprocess') }}" method="POST" id="tree-id-migration-form">
                @csrf
                <div class="card card-outline card-primary">
                    <div class="card-header"><h3 class="card-title"><i class="fas fa-exchange-alt mr-1"></i>Identificadores del árbol</h3></div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-md-0">
                                    <label for="old_id">IDCliente actual</label>
                                    <input autocomplete="off" class="form-control" id="old_id" name="old_id" value="{{ old('old_id', request('old_id')) }}" maxlength="175" required>
                                    <small class="text-muted">El identificador que hoy tienen las personas del árbol.</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-0">
                                    <label for="new_id">IDCliente nuevo</label>
                                    <input autocomplete="off" class="form-control" id="new_id" name="new_id" value="{{ old('new_id', request('new_id')) }}" maxlength="175" required>
                                    <small class="text-muted">El identificador correcto que debe usar el árbol.</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer text-right">
                        <button class="btn btn-primary" name="action" value="preview"><i class="fas fa-search mr-1"></i>Revisar impacto</button>
                    </div>
                </div>

                @if ($preview)
                    <div class="card card-outline {{ $preview['can_migrate'] ? 'card-info' : 'card-danger' }}">
                        <div class="card-header"><h3 class="card-title"><i class="fas fa-clipboard-check mr-1"></i>Resultado de la revisión</h3></div>
                        <div class="card-body">
                            <p>Se revisó la migración de <strong>{{ $preview['old_id'] }}</strong> a <strong>{{ $preview['new_id'] }}</strong>.</p>
                            <div class="row">
                                @foreach ([
                                    'personas_arbol' => 'Personas del árbol',
                                    'usuarios' => 'Usuarios',
                                    'archivos' => 'Archivos',
                                    'familias' => 'Relaciones familiares',
                                    'grupos_familiares' => 'Grupos familiares',
                                    'miembros_grupo_familiar' => 'Miembros de grupos',
                                    'enlaces_secundarios_de_arbol' => 'Enlaces secundarios',
                                ] as $key => $label)
                                    <div class="col-sm-6 col-lg-4 mb-2"><span class="text-muted small d-block">{{ $label }}</span><strong>{{ $preview['counts'][$key] }}</strong></div>
                                @endforeach
                            </div>

                            @if ($preview['conflicts'])
                                <div class="alert alert-danger mb-0"><strong>No se aplicará ningún cambio.</strong><ul class="mb-0 mt-2">@foreach ($preview['conflicts'] as $conflict)<li>{{ $conflict }}</li>@endforeach</ul></div>
                            @else
                                <div class="alert alert-warning mb-0"><i class="fas fa-exclamation-triangle mr-1"></i>La migración cambiará estas referencias locales dentro de una sola transacción. HubSpot y Teamleader no se modifican.</div>
                            @endif
                        </div>
                        @if ($preview['can_migrate'])
                            <div class="card-footer">
                                <label for="confirmation">Para confirmar, escribe <strong>{{ $preview['new_id'] }}</strong></label>
                                <div class="input-group">
                                    <input autocomplete="off" class="form-control" id="confirmation" name="confirmation" value="{{ old('confirmation') }}" required>
                                    <div class="input-group-append"><button class="btn btn-danger" name="action" value="migrate"><i class="fas fa-exchange-alt mr-1"></i>Migrar árbol completo</button></div>
                                </div>
                            </div>
                        @endif
                    </div>
                @endif
            </form>
        </div>
        <div class="col-xl-4">
            <div class="card card-outline card-secondary">
                <div class="card-header"><h3 class="card-title"><i class="fas fa-shield-alt mr-1"></i>Validaciones</h3></div>
                <div class="card-body">
                    <ul class="pl-3 mb-0">
                        <li>Exige una persona raíz para confirmar que el origen es un árbol válido.</li>
                        <li>Bloquea la operación si el ID nuevo ya tiene un árbol o genera duplicados.</li>
                        <li>Mueve personas, archivos, relaciones familiares, grupos y enlaces secundarios.</li>
                        <li>Registra el cambio de pasaporte del usuario cuando corresponde.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
@stop
