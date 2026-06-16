@extends('adminlte::page')

@section('title', 'Codigos de Referidos')

@section('content_header')
    <h1>Codigos de Referidos</h1>
@stop

@section('content')
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if(session('warning'))
        <div class="alert alert-warning">{{ session('warning') }}</div>
    @endif

    @if(! $tablesReady)
        <div class="alert alert-warning">
            Las tablas de referidos aun no estan migradas. Cuando tengas respaldo, ejecuta las migraciones antes de generar codigos.
        </div>
    @endif

    @if($tablesReady && $coordinatorsWithoutCode > 0)
        <div class="alert alert-info">
            Hay {{ $coordinatorsWithoutCode }} coordinador(es) sin codigo. Usa "Generar faltantes" para asignarlos.
        </div>
    @endif

    @if($coordinatorsWithoutEmail > 0)
        <div class="alert alert-warning">
            Hay {{ $coordinatorsWithoutEmail }} coordinador(es) sin correo registrado. No recibiran el envio masivo.
        </div>
    @endif

    <div class="mb-3 d-flex justify-content-end">
        <form action="{{ route('admin.referral-codes.sync') }}" method="POST" class="mr-2">
            @csrf
            <button type="submit" class="btn btn-outline-primary" @if(! $tablesReady) disabled @endif>
                <i class="fas fa-sync-alt"></i> Generar faltantes
            </button>
        </form>

        <form action="{{ route('admin.referral-codes.send-all') }}" method="POST">
            @csrf
            <button type="submit" class="btn btn-primary" onclick="return confirm('Enviar codigos a todos los coordinadores?')" @if(! $tablesReady) disabled @endif>
                <i class="fas fa-paper-plane"></i> Enviar a coordinadores
            </button>
        </form>
    </div>

    <div class="card">
        <div class="card-body table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Coordinador</th>
                        <th>Email</th>
                        <th>Codigo</th>
                        <th>Estado</th>
                        <th>Ventas</th>
                        <th>Monto referido</th>
                        <th>Ultimo envio</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($codes as $code)
                        <tr>
                            <td>{{ optional($code->coordinator)->name ?? 'Sin usuario' }}</td>
                            <td>{{ optional($code->coordinator)->email ?? 'Sin correo' }}</td>
                            <td>
                                <code class="h6 mb-0">{{ $code->code }}</code>
                            </td>
                            <td>
                                @if($code->active)
                                    <span class="badge badge-success">Activo</span>
                                @else
                                    <span class="badge badge-secondary">Inactivo</span>
                                @endif
                            </td>
                            <td>{{ $code->sales_count }}</td>
                            <td>{{ number_format((float) ($code->sales_amount ?? 0), 2) }} EUR</td>
                            <td>{{ optional($code->last_sent_at)->format('d/m/Y H:i') ?? 'No enviado' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted">No hay coordinadores con codigo.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@stop

@section('css')
    <link rel="stylesheet" href="{{ asset('css/sefar.css') }}">
@stop
