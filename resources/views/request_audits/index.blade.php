@extends('adminlte::page')

@section('title', 'Auditoría de accesos')

@section('content_header')
  <h1>Auditoría de accesos</h1>
@endsection

@section('content')
  <div class="mb-3 d-flex align-items-center">
    <label for="filtro" class="mr-2 mb-0">Filtrar por:</label>

    <form method="GET" action="{{ route('request-audits.index') }}">
      <select id="filtro" name="filtro" class="form-control w-auto d-inline-block" onchange="this.form.submit()">
        <option value="semana" @selected($filtro === 'semana')>Semana</option>
        <option value="mes" @selected($filtro === 'mes')>Mes</option>
        <option value="anio" @selected($filtro === 'anio')>Año</option>
        <option value="todo" @selected($filtro === 'todo')>Todo</option>
      </select>
    </form>
  </div>

  <div class="row">
    <div class="col-md-8">
      <div class="card">
        <div class="card-body">
          <canvas id="visitasChart"></canvas>
        </div>
      </div>
    </div>

    <div class="col-md-4">
      <div class="card">
        <div class="card-body">
          <h4 id="totalVisitas">Total registros: {{ $totalVisitas }}</h4>
        </div>
      </div>
    </div>
  </div>

  <div class="card mt-3">
    <div class="card-header">
      <b>Detalle agrupado por usuario</b>
    </div>

    <div class="card-body">
      @if($visitasAgrupadas->isEmpty())
        <div class="alert alert-info mb-0">
          No hay registros para el filtro seleccionado.
        </div>
      @else
        <div id="accordionUsuarios">
          @foreach($visitasAgrupadas as $index => $grupo)
            @php
              $collapseId = 'collapseUsuario'.$index;
              $headingId = 'headingUsuario'.$index;
            @endphp

            <div class="card mb-2">
              <div class="card-header" id="{{ $headingId }}">
                <h4 class="card-title w-100 mb-0">
                  <button
                    class="btn btn-link btn-block text-left d-flex justify-content-between align-items-center"
                    type="button"
                    data-toggle="collapse"
                    data-target="#{{ $collapseId }}"
                    aria-expanded="{{ $index === 0 ? 'true' : 'false' }}"
                    aria-controls="{{ $collapseId }}"
                    style="text-decoration: none;"
                  >
                    <span>
                      <strong>{{ $grupo['label'] }}</strong>
                      @if($grupo['email'])
                        <small class="text-muted ml-2">({{ $grupo['email'] }})</small>
                      @endif
                    </span>

                    <span class="badge badge-primary">
                      {{ $grupo['total'] }} registros
                    </span>
                  </button>
                </h4>
              </div>

              <div
                id="{{ $collapseId }}"
                class="collapse {{ $index === 0 ? 'show' : '' }}"
                aria-labelledby="{{ $headingId }}"
                data-parent="#accordionUsuarios"
              >
                <div class="card-body table-responsive">
                  <table class="table table-striped table-hover">
                    <thead>
                      <tr>
                        <th>#</th>
                        <th>Método</th>
                        <th>Ruta</th>
                        <th>URL</th>
                        <th>IP</th>
                        <th>Fecha</th>
                      </tr>
                    </thead>
                    <tbody>
                      @foreach($grupo['items'] as $i => $item)
                        <tr>
                          <td>{{ $i + 1 }}</td>
                          <td>{{ $item->method ?? '-' }}</td>
                          <td>{{ $item->route_name ?? '-' }}</td>
                          <td style="max-width: 420px; word-break: break-word;">
                            {{ $item->url ?? $item->path ?? '-' }}
                          </td>
                          <td>{{ $item->ip_address ?? '-' }}</td>
                          <td>{{ optional($item->visited_at)->format('Y-m-d H:i:s') }}</td>
                        </tr>
                      @endforeach
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          @endforeach
        </div>
      @endif
    </div>
  </div>
@endsection

@push('js')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
  const chartData = @json($visitasPorUsuario);

  const labels = chartData.map(x => x.label);
  const values = chartData.map(x => x.total);

  const ctx = document.getElementById('visitasChart').getContext('2d');

  new Chart(ctx, {
    type: 'bar',
    data: {
      labels,
      datasets: [{
        label: 'Registros por usuario',
        data: values
      }]
    },
    options: {
      responsive: true,
      plugins: {
        legend: { display: false }
      },
      scales: {
        y: { beginAtZero: true }
      }
    }
  });
</script>
@endpush

@section('css')
  <link rel="stylesheet" href="{{ asset('css/sefar.css') }}">
  <style>
    .card-title button:focus {
      box-shadow: none;
    }
  </style>
@endsection
