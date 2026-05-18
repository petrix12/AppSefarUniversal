@extends('adminlte::page')

@section('title', 'Jobs Teamleader')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center flex-wrap" style="gap:.75rem">
        <h1 class="mb-0">
            <i class="fas fa-tasks mr-2"></i>Jobs Teamleader
        </h1>

        <div class="d-flex flex-wrap" style="gap:.4rem">
            <form method="POST" action="{{ route('teamleader.jobs.work') }}" class="d-inline">
                @csrf
                <input type="hidden" name="jobs" value="20">
                <button type="submit" class="btn btn-sm btn-primary">
                    <i class="fas fa-play mr-1"></i> Procesar cola
                </button>
            </form>

            <form method="POST" action="{{ route('teamleader.jobs.failed.retry') }}" class="d-inline"
                  onsubmit="return confirm('¿Reintentar todos los jobs fallidos de Teamleader? Laravel los retirara de errores y los pondra en cola.');">
                @csrf
                <button type="submit" class="btn btn-sm btn-warning" {{ $totals['failed'] ? '' : 'disabled' }}>
                    <i class="fas fa-redo mr-1"></i> Reintentar fallidos
                </button>
            </form>

            <form method="POST" action="{{ route('teamleader.jobs.failed.clear') }}" class="d-inline"
                  onsubmit="return confirm('¿Limpiar todos los errores fallidos de Teamleader?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-sm btn-outline-danger" {{ $totals['failed'] ? '' : 'disabled' }}>
                    <i class="fas fa-broom mr-1"></i> Limpiar errores
                </button>
            </form>

            <a href="{{ route('teamleader.jobs.index') }}" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-sync-alt mr-1"></i> Actualizar
            </a>
        </div>
    </div>
@endsection

@section('content')
    @foreach(['status' => 'success', 'error' => 'danger'] as $key => $class)
        @if(session($key))
            <div class="alert alert-{{ $class }} alert-dismissible fade show">
                {!! session($key) !!}
                <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
            </div>
        @endif
    @endforeach

    @unless($jobsTableExists)
        <div class="alert alert-warning">
            No existe la tabla <code>jobs</code>. Esta vista necesita la cola database de Laravel para listar jobs pendientes.
        </div>
    @endunless

    <div class="row">
        <div class="col-md-2 col-sm-6">
            <div class="small-box bg-info">
                <div class="inner">
                    <h3>{{ number_format($totals['total']) }}</h3>
                    <p>Total en cola</p>
                </div>
                <div class="icon"><i class="fas fa-layer-group"></i></div>
            </div>
        </div>
        <div class="col-md-2 col-sm-6">
            <div class="small-box bg-success">
                <div class="inner">
                    <h3>{{ number_format($totals['ready']) }}</h3>
                    <p>Listos</p>
                </div>
                <div class="icon"><i class="fas fa-play"></i></div>
            </div>
        </div>
        <div class="col-md-2 col-sm-6">
            <div class="small-box bg-warning">
                <div class="inner">
                    <h3>{{ number_format($totals['delayed']) }}</h3>
                    <p>Diferidos</p>
                </div>
                <div class="icon"><i class="fas fa-clock"></i></div>
            </div>
        </div>
        <div class="col-md-2 col-sm-6">
            <div class="small-box bg-primary">
                <div class="inner">
                    <h3>{{ number_format($totals['reserved']) }}</h3>
                    <p>Reservados</p>
                </div>
                <div class="icon"><i class="fas fa-spinner"></i></div>
            </div>
        </div>
        <div class="col-md-2 col-sm-6">
            <div class="small-box bg-danger">
                <div class="inner">
                    <h3>{{ number_format($totals['failed']) }}</h3>
                    <p>Fallidos</p>
                </div>
                <div class="icon"><i class="fas fa-exclamation-triangle"></i></div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-5">
            <div class="card card-outline card-primary">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-stream mr-1"></i>Resumen por cola
                    </h3>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th>Cola</th>
                                    <th class="text-right">Total</th>
                                    <th class="text-right">Listos</th>
                                    <th class="text-right">Diferidos</th>
                                    <th class="text-right">Reservados</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($queueStats as $stat)
                                    <tr>
                                        <td><code>{{ $stat['queue'] }}</code></td>
                                        <td class="text-right">{{ number_format($stat['total']) }}</td>
                                        <td class="text-right text-success">{{ number_format($stat['ready']) }}</td>
                                        <td class="text-right text-warning">{{ number_format($stat['delayed']) }}</td>
                                        <td class="text-right text-primary">{{ number_format($stat['reserved']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card card-outline card-secondary">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-database mr-1"></i>Datos Teamleader sincronizados
                    </h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        @foreach($dataCounts as $label => $count)
                            <div class="col-md-3 col-sm-6 mb-3">
                                <div class="border rounded p-2 h-100">
                                    <div class="text-muted small">{{ $label }}</div>
                                    <div class="h5 mb-0">{{ number_format($count) }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-outline card-primary">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-list mr-1"></i>Proximos jobs pendientes
            </h3>
            <div class="card-tools">
                <span class="badge badge-light border">Max. 50</span>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead class="thead-light">
                        <tr>
                            <th>ID</th>
                            <th>Cola</th>
                            <th>Job</th>
                            <th>Parametros</th>
                            <th>Intentos</th>
                            <th>Estado</th>
                            <th>Disponible</th>
                            <th>Reservado</th>
                            <th>Creado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($nextJobs as $job)
                            @php
                                $statusClass = [
                                    'Listo' => 'success',
                                    'Diferido' => 'warning',
                                    'Reservado' => 'primary',
                                ][$job->status] ?? 'secondary';
                            @endphp
                            <tr>
                                <td><code>{{ $job->id }}</code></td>
                                <td><code>{{ $job->queue }}</code></td>
                                <td>{{ $job->job_name }}</td>
                                <td><small class="text-muted">{{ $job->summary }}</small></td>
                                <td>{{ $job->attempts }}</td>
                                <td>
                                    <span class="badge badge-{{ $statusClass }}">{{ $job->status }}</span>
                                </td>
                                <td>{{ $job->available_at }}</td>
                                <td>{{ $job->reserved_at }}</td>
                                <td>{{ $job->created_at }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center text-muted py-4">
                                    <i class="fas fa-check-circle fa-2x d-block mb-2"></i>
                                    No hay jobs pendientes de Teamleader en este momento.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-7">
            <div class="card card-outline card-danger">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-exclamation-circle mr-1"></i>Jobs fallidos recientes
                    </h3>
                </div>
                <div class="card-body p-0">
                    @unless($failedJobsTableExists)
                        <div class="alert alert-warning m-3">
                            No existe la tabla <code>failed_jobs</code>.
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0">
                                <thead class="thead-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Cola</th>
                                        <th>Job</th>
                                        <th>Error</th>
                                        <th>Fecha</th>
                                        <th class="text-right">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($failedJobs as $job)
                                        <tr>
                                            <td><code>{{ $job->uuid ?: $job->id }}</code></td>
                                            <td><code>{{ $job->queue }}</code></td>
                                            <td>
                                                {{ $job->job_name }}
                                                <div><small class="text-muted">{{ $job->summary }}</small></div>
                                            </td>
                                            <td><small>{{ $job->error }}</small></td>
                                            <td>{{ $job->failed_at }}</td>
                                            <td class="text-right text-nowrap">
                                                <form method="POST" action="{{ route('teamleader.jobs.failed.retry-one', $job->id) }}" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="btn btn-xs btn-warning" title="Reintentar job">
                                                        <i class="fas fa-redo"></i>
                                                    </button>
                                                </form>
                                                <form method="POST" action="{{ route('teamleader.jobs.failed.clear-one', $job->id) }}" class="d-inline"
                                                      onsubmit="return confirm('¿Limpiar este error?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-xs btn-outline-danger" title="Limpiar error">
                                                        <i class="fas fa-broom"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="text-center text-muted py-4">
                                                No hay jobs fallidos de Teamleader.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    @endunless
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card card-outline card-info">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-history mr-1"></i>Ultimos logs de sync
                    </h3>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th>Entidad</th>
                                    <th>Estado</th>
                                    <th class="text-right">Procesados</th>
                                    <th>Inicio</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($syncLogs as $log)
                                    @php
                                        $logClass = [
                                            'running' => 'primary',
                                            'completed' => 'success',
                                            'failed' => 'danger',
                                        ][$log->status] ?? 'secondary';
                                    @endphp
                                    <tr>
                                        <td>{{ $log->entity }}</td>
                                        <td><span class="badge badge-{{ $logClass }}">{{ $log->status }}</span></td>
                                        <td class="text-right">
                                            {{ number_format($log->processed) }}/{{ number_format($log->total) }}
                                            @if($log->failed)
                                                <span class="text-danger">({{ number_format($log->failed) }} err.)</span>
                                            @endif
                                        </td>
                                        <td>
                                            <small>{{ $log->started_at?->format('d/m/Y H:i') ?? '-' }}</small>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-4">
                                            Aun no hay logs de sincronizacion.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('css')
    <link rel="stylesheet" href="{{ asset('css/sefar.css') }}">
@stop
