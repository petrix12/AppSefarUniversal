@extends('adminlte::page')

@section('title', 'Admin — Tareas')

@section('content_header')
    <h1>
        <i class="fas fa-tasks mr-2 text-primary"></i>Panel de Tareas
        <small class="text-muted">{{ $date->format('d/m/Y') }}</small>
    </h1>
@stop

@section('content')

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {!! session('success') !!}
            <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
        </div>
    @endif

    {{-- Stats --}}
    <div class="row">
        @php
            $statusConfig = [
                'pending'     => ['label'=>'Pendientes',  'color'=>'warning', 'icon'=>'clock'],
                'in_progress' => ['label'=>'En curso',    'color'=>'primary', 'icon'=>'spinner'],
                'completed'   => ['label'=>'Completadas', 'color'=>'success', 'icon'=>'check'],
                'canceled'    => ['label'=>'Canceladas',  'color'=>'danger',  'icon'=>'times'],
            ];
        @endphp
        @foreach($statusConfig as $key => $cfg)
            <div class="col-sm-3">
                <div class="small-box bg-{{ $cfg['color'] }}">
                    <div class="inner">
                        <h3>{{ $stats[$key] ?? 0 }}</h3>
                        <p>{{ $cfg['label'] }}</p>
                    </div>
                    <div class="icon"><i class="fas fa-{{ $cfg['icon'] }}"></i></div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Gráfico (Chart.js inline, sin instalar nada extra) --}}
    <div class="card card-outline card-dark mb-3">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-chart-bar mr-1"></i>Tareas por asesor</h3>
        </div>
        <div class="card-body">
            <canvas id="taskChart" height="100"></canvas>
        </div>
    </div>

    {{-- Filtros + Acciones --}}
    <div class="card card-outline card-secondary mb-3">
        <div class="card-body py-2">
            <form method="GET" class="form-inline flex-wrap gap-2">
                <input type="date" name="date" class="form-control form-control-sm mr-2"
                       value="{{ $date->toDateString() }}">

                <select name="user_id" class="form-control form-control-sm mr-2">
                    <option value="">— Todos los asesores —</option>
                    @foreach($advisors as $id => $name)
                        <option value="{{ $id }}" {{ request('user_id') == $id ? 'selected' : '' }}>
                            {{ $name }}
                        </option>
                    @endforeach
                </select>

                <select name="status" class="form-control form-control-sm mr-2">
                    <option value="">— Todos los estados —</option>
                    <option value="pending"     {{ request('status') === 'pending'     ? 'selected' : '' }}>Pendiente</option>
                    <option value="in_progress" {{ request('status') === 'in_progress' ? 'selected' : '' }}>En curso</option>
                    <option value="completed"   {{ request('status') === 'completed'   ? 'selected' : '' }}>Completada</option>
                    <option value="canceled"    {{ request('status') === 'canceled'    ? 'selected' : '' }}>Cancelada</option>
                </select>

                <button class="btn btn-sm btn-primary mr-2">
                    <i class="fas fa-search mr-1"></i>Filtrar
                </button>

                <a href="{{ route('tasks.admin.create') }}" class="btn btn-sm btn-success mr-2">
                    <i class="fas fa-plus mr-1"></i>Nueva tarea
                </a>

                {{-- Generar tareas del día --}}
                <form method="POST" action="{{ route('tasks.admin.generate-daily') }}" class="d-inline">
                    @csrf
                    <input type="hidden" name="date" value="{{ $date->toDateString() }}">
                    <button class="btn btn-sm btn-warning"
                            onclick="return confirm('¿Generar tareas para {{ $date->toDateString() }}?')">
                        <i class="fas fa-magic mr-1"></i>Generar tareas diarias
                    </button>
                </form>
            </form>
        </div>
    </div>

    {{-- Tabla --}}
    <div class="card card-outline card-primary">
        <div class="card-body p-0">
            <table class="table table-hover table-sm mb-0">
                <thead class="thead-dark">
                    <tr>
                        <th>#</th>
                        <th>Asesor</th>
                        <th>Contacto</th>
                        <th>Título</th>
                        <th>Estado</th>
                        <th>Fecha</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($tasks as $task)
                        @php
                            $colorMap = [
                                'pending'=>'warning','in_progress'=>'primary',
                                'completed'=>'success','canceled'=>'danger'
                            ];
                            $labelMap = [
                                'pending'=>'Pendiente','in_progress'=>'En curso',
                                'completed'=>'Completada','canceled'=>'Cancelada'
                            ];
                        @endphp
                        <tr>
                            <td>{{ $task->id }}</td>
                            <td>{{ $task->assignee?->name ?? '—' }}</td>
                            <td>{{ $task->contact?->name ?? '—' }}</td>
                            <td>{{ Str::limit($task->title, 45) }}</td>
                            <td>
                                <span class="badge badge-{{ $colorMap[$task->status] ?? 'secondary' }}">
                                    {{ $labelMap[$task->status] ?? $task->status }}
                                </span>
                            </td>
                            <td>{{ $task->due_date->format('d/m/Y') }}</td>
                            <td>
                                <a href="{{ route('tasks.admin.edit', $task) }}"
                                   class="btn btn-xs btn-info">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <form action="{{ route('tasks.admin.destroy', $task) }}"
                                      method="POST" class="d-inline"
                                      onsubmit="return confirm('¿Eliminar tarea #{{ $task->id }}?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-xs btn-danger">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">
                                Sin tareas para los filtros aplicados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">
            {{ $tasks->links() }}
        </div>
    </div>

@stop

@section('css')
    <link rel="stylesheet" href="{{ asset('css/sefar.css') }}">
@stop

@section('js')
{{-- Chart.js desde CDN (sin instalación) --}}
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
    const ctx = document.getElementById('taskChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: @json($chartData['labels']),
            datasets: [
                {
                    label: 'Pendientes',
                    data: @json($chartData['pending']),
                    backgroundColor: 'rgba(255, 193, 7, 0.8)',
                },
                {
                    label: 'En curso',
                    data: @json($chartData['progress']),
                    backgroundColor: 'rgba(0, 123, 255, 0.8)',
                },
                {
                    label: 'Completadas',
                    data: @json($chartData['done']),
                    backgroundColor: 'rgba(40, 167, 69, 0.8)',
                },
                {
                    label: 'Canceladas',
                    data: @json($chartData['canceled']),
                    backgroundColor: 'rgba(220, 53, 69, 0.8)',
                },
            ],
        },
        options: {
            responsive: true,
            plugins: { legend: { position: 'top' } },
            scales: {
                x: { stacked: true },
                y: { stacked: true, beginAtZero: true, ticks: { stepSize: 1 } },
            },
        },
    });
</script>
@stop
