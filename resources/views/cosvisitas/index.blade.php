@extends('adminlte::page')

@section('title', 'Visitas COS')

@section('content_header')
  <h1>Visitas COS</h1>
@endsection

@section('content')
  <div class="mb-3 d-flex align-items-center">
    <label for="filtro" class="mr-2 mb-0">Filtrar por:</label>

    <form method="GET" action="{{ url('cosvisitas') }}">
      <select id="filtro" name="filtro" class="form-control w-auto d-inline-block" onchange="this.form.submit()">
        <option value="semana" @selected($filtro==='semana')>Semana</option>
        <option value="mes" @selected($filtro==='mes')>Mes</option>
        <option value="anio" @selected($filtro==='anio')>Año</option>
        <option value="todo" @selected($filtro==='todo')>Todo</option>
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
          <h4 id="totalVisitas">Total visitas: {{ $totalVisitas }}</h4>
        </div>
      </div>
    </div>
  </div>

  <div class="card mt-3">
    <div class="card-header"><b>Detalle</b></div>
    <div class="card-body table-responsive">
      <table class="table table-striped">
        <thead>
          <tr>
            <th>#</th>
            <th>Usuario</th>
            <th>Cliente</th>
            <th>Fecha de visita</th>
          </tr>
        </thead>
        <tbody>
          @foreach($visitas as $i => $v)
            @php
              $u = $v->user;
              $c = $v->cliente;

              $usuario = $u?->nombres
                ? trim($u->nombres.' '.($u->apellidos ?? ''))
                : ($u?->name ?? 'Usuario desconocido');

              $cliente = $c?->nombres
                ? trim($c->nombres.' '.($c->apellidos ?? ''))
                : ($c?->name ?? 'Cliente desconocido');
            @endphp

            <tr>
              <td>{{ $visitas->firstItem() + $i }}</td>
              <td>{{ $usuario }}</td>
              <td>
                @if($c)
                  <a href="{{ url("/users/{$c->id}/edit") }}">{{ $cliente }}</a>
                @else
                  {{ $cliente }}
                @endif
              </td>
              <td>{{ optional($v->fecha_visita)->format('Y-m-d H:i:s') ?? $v->fecha_visita }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>

      {{ $visitas->links('pagination::bootstrap-4') }}
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
        label: 'Visitas por usuario',
        data: values
      }]
    },
    options: {
      responsive: true,
      plugins: { legend: { display: false } },
      scales: { y: { beginAtZero: true } }
    }
  });
</script>
@endpush

@section('css')
  <link rel="stylesheet" href="{{ asset('css/sefar.css') }}">
@endsection
