@extends('adminlte::page')

@section('title', 'Modelos de IA')

@section('content_header')
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center">
        <h1>Modelos de IA por proceso</h1>
        @include('admin.integrations._tabs')
    </div>
@stop

@section('content')
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    @if($catalogError)
        <div class="alert alert-info">{{ $catalogError }}</div>
    @endif

    <div class="card card-outline card-primary">
        <div class="card-header">
            <h3 class="card-title mb-0">Configuración de OpenRouter</h3>
        </div>
        <div class="card-body">
            <p class="text-muted">Selecciona un modelo principal y modelos de respaldo para cada proceso. La configuración queda guardada en la base de datos y no requiere editar <code>.env</code>. El catálogo muestra primero los modelos de menor latencia reportada por OpenRouter.</p>

            @if($models)
                <datalist id="openrouter-model-catalog">
                    @foreach($models as $model)
                        <option value="{{ $model['id'] }}" label="{{ $model['name'] }} · ${{ number_format($model['input_price'], 3) }}/M entrada · ${{ number_format($model['output_price'], 3) }}/M salida"></option>
                    @endforeach
                </datalist>
            @endif

            @foreach($processSettings as $process => $setting)
                <form method="POST" action="{{ route('admin.integrations.ai-models.update') }}" class="border rounded p-3 mb-3">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="process" value="{{ $process }}">
                    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-start mb-3">
                        <div class="mb-2 mb-lg-0">
                            <h4 class="h5 mb-1">{{ $setting['label'] }}</h4>
                            <p class="text-muted mb-0">{{ $setting['description'] }}</p>
                        </div>
                        <span class="badge badge-light">{{ $process }}</span>
                    </div>

                    <div class="row">
                        <div class="col-lg-5 form-group">
                            <label for="model-{{ $process }}">Modelo principal</label>
                            <input id="model-{{ $process }}" name="model" list="openrouter-model-catalog" class="form-control" value="{{ old('process') === $process ? old('model') : $setting['model'] }}" placeholder="proveedor/modelo" required>
                            <small class="form-text text-muted">Puedes elegir del catálogo o pegar un ID de OpenRouter.</small>
                        </div>
                        <div class="col-lg-5 form-group">
                            <label for="fallback-{{ $process }}">Modelos de respaldo</label>
                            <input id="fallback-{{ $process }}" name="fallback_models" class="form-control" value="{{ old('process') === $process ? old('fallback_models') : implode(', ', $setting['fallback_models']) }}" placeholder="proveedor/modelo, proveedor/modelo">
                            <small class="form-text text-muted">Se intentan en orden cuando el principal falla o no responde.</small>
                        </div>
                        <div class="col-lg-1 col-6 form-group">
                            <label for="timeout-{{ $process }}">Timeout</label>
                            <div class="input-group">
                                <input id="timeout-{{ $process }}" type="number" min="15" max="180" name="timeout_seconds" class="form-control" value="{{ old('process') === $process ? old('timeout_seconds') : $setting['timeout_seconds'] }}" required>
                                <div class="input-group-append"><span class="input-group-text">s</span></div>
                            </div>
                        </div>
                        <div class="col-lg-1 col-6 form-group">
                            <label for="tokens-{{ $process }}">Tokens</label>
                            <input id="tokens-{{ $process }}" type="number" min="64" max="4000" name="max_tokens" class="form-control" value="{{ old('process') === $process ? old('max_tokens') : $setting['max_tokens'] }}" required>
                        </div>
                    </div>

                    <div class="text-right">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i>Guardar configuración</button>
                    </div>
                </form>
            @endforeach
        </div>
    </div>
@stop
