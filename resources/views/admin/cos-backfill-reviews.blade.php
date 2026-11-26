@extends('adminlte::page')

@section('title', 'Casos en revisión del COS')

@section('content_header')
    <div class="d-flex flex-wrap align-items-center justify-content-between">
        <h1 class="mr-3">Casos en revisión del COS</h1>
        <a href="{{ route('admin.integrations.crm-sync') }}#cos-backfill-panel" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-arrow-left mr-1" aria-hidden="true"></i> Volver al avance
        </a>
    </div>
@stop

@section('content')
    <div class="card">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.integrations.crm-sync.cos-reviews') }}" class="form-row align-items-end mb-3">
                <div class="form-group col-12 col-sm-4 col-lg-3 mb-2">
                    <label for="review-user">ID del representado</label>
                    <input type="number" min="1" step="1" class="form-control" id="review-user" name="representado"
                           value="{{ $filters['representado'] ?? '' }}">
                </div>
                <div class="form-group col-12 col-sm-6 col-lg-5 mb-2">
                    <label for="review-reason">Motivo</label>
                    <select class="form-control" id="review-reason" name="motivo">
                        <option value="">Todos los motivos</option>
                        @foreach($reasons as $key => $label)
                            <option value="{{ $key }}" @selected(($filters['motivo'] ?? '') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto mb-2">
                    <button type="submit" class="btn sefar-button cfrSefar">Filtrar</button>
                </div>
                @if(!empty($filters['motivo']) || !empty($filters['representado']))
                    <div class="col-auto mb-2">
                        <a href="{{ route('admin.integrations.crm-sync.cos-reviews') }}" class="btn btn-outline-secondary">Limpiar</a>
                    </div>
                @endif
            </form>

            <div class="d-flex flex-wrap justify-content-between mb-2">
                <p class="small text-muted mb-1">Casos en esta selección <strong>{{ number_format($cases->total(), 0, ',', '.') }}</strong></p>
                @if($undetailed > 0)
                    <p class="small text-muted mb-1">Casos contados sin detalle disponible <strong>{{ number_format($undetailed, 0, ',', '.') }}</strong></p>
                @endif
            </div>

            <div class="table-responsive">
                <table class="table table-hover">
                    <thead><tr><th scope="col">Representado</th><th scope="col">Motivo</th><th scope="col">Detectado</th><th scope="col">Ficha</th></tr></thead>
                    <tbody>
                        @forelse($cases as $case)
                            @php($name = $names->get($case['user_id']))
                            <tr>
                                <td><span class="font-weight-bold">{{ $name ?? 'Ficha no disponible' }}</span><span class="small text-muted d-block">ID {{ $case['user_id'] }}</span></td>
                                <td>{{ $reasons[$case['reason']] ?? 'Revisión de HubSpot' }}</td>
                                <td><time datetime="{{ $case['recorded_at'] }}">{{ \Illuminate\Support\Carbon::parse($case['recorded_at'])->setTimezone(config('app.timezone'))->format('d/m/Y H:i T') }}</time></td>
                                <td>
                                    @if($name !== null && auth()->user()->can('crud.users.index'))
                                        <a href="{{ route('crud.users.edit', ['user' => $case['user_id']]) }}" class="btn btn-sm btn-outline-primary text-nowrap"
                                           aria-label="Abrir COS del representado {{ $case['user_id'] }}">Abrir COS</a>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-muted">No hay casos disponibles para esta selección.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $cases->links() }}
        </div>
    </div>
@stop
