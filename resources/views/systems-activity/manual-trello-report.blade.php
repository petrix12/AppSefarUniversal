@extends('adminlte::page')

@section('title', 'Informe manual de Trello')

@section('content_header')
    <div>
        <h1 class="mb-1">Informe manual de Trello</h1>
        <p class="text-muted mb-0">Selecciona la columna semanal para generar el informe con sus tarjetas.</p>
    </div>
@stop

@section('content')
    @if ($connectionError)
        <div class="alert alert-warning">
            No se pudo conectar con Trello. Configura <code>TRELLO_API_KEY</code> y <code>TRELLO_API_TOKEN</code> en <code>.env</code>, verifica <code>TRELLO_SYSTEMS_BOARD_SHORTLINK</code> y limpia la caché de configuración con <code>php artisan config:clear</code>.
            <div class="small mt-1">{{ $connectionError }}</div>
        </div>
    @endif

    @if ($errors->has('trello'))
        <div class="alert alert-danger">{{ $errors->first('trello') }}</div>
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
