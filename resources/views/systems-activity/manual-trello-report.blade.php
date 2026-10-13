@extends('adminlte::page')

@section('title', 'Informe manual de Trello')

@section('content_header')
    <div>
        <h1 class="mb-1">Informe manual de Trello</h1>
        <p class="text-muted mb-0">Selecciona la columna semanal para generar el informe con sus tarjetas.</p>
    </div>
@stop

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if ($connectionError)
        <div class="alert alert-warning">
            {{ $connectionError }}
        </div>
    @endif

    @if ($errors->has('trello'))
        <div class="alert alert-danger">{{ $errors->first('trello') }}</div>
    @endif

    @if ($oauthConfigured)
        <div class="card">
            <div class="card-body d-flex justify-content-between align-items-center flex-wrap">
                <div class="mb-2 mb-md-0">
                    <strong>Conexión con Trello:</strong>
                    @if ($trelloConnected)
                        <span class="text-success">Conectada</span>
                    @else
                        <span class="text-muted">Pendiente</span>
                    @endif
                </div>
                <a href="{{ route('trello.oauth.start') }}" class="btn btn-outline-primary">
                    {{ $trelloConnected ? 'Volver a conectar Trello' : 'Conectar con Trello' }}
                </a>
            </div>
        </div>
    @endif

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('systems-activity.manual-trello.generate') }}" class="form-inline">
                @csrf
                <label for="list_id" class="mr-2">Columna de Trello</label>
                <select id="list_id" name="list_id" class="form-control mr-2 flex-grow-1" required @disabled(empty($lists))>
                    <option value="">Selecciona una columna</option>
                    @foreach ($lists as $list)
                        <option value="{{ $list['id'] }}" @selected(old('list_id', session('selected_list_id')) === $list['id'])>
                            {{ $list['name'] }}
                        </option>
                    @endforeach
                </select>
                <button type="submit" class="btn btn-primary" @disabled(empty($lists))>Generar informe</button>
            </form>
        </div>
    </div>

    @if ($report)
        <div class="card">
            <div class="card-header"><h3 class="card-title">Informe generado</h3></div>
            <div class="card-body" style="white-space: pre-wrap;">{{ $report }}</div>
        </div>
    @endif
@stop
