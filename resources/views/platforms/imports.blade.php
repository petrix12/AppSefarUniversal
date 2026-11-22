@extends('adminlte::page')
@section('title', 'Copias de plataformas')
@section('content_header')<h1>Copias de plataformas</h1>@stop
@section('css')<link rel="stylesheet" href="{{ asset('css/platform-management.css') }}?v={{ filemtime(public_path('css/platform-management.css')) }}">@stop
@section('content')
<div class="platform-admin">
    @include('kalendia.partials.messages')
    <div class="card"><div class="card-body">
        <div class="platform-toolbar justify-content-between">
            <div><h2 class="h5 mb-1">Tus datos disponibles en la App</h2><p class="text-muted mb-0">Stripe y Kalendia guardan una copia local por recurso. PayPal histórico se consulta directamente por API.</p></div>
            <a class="btn btn-outline-primary" href="{{ route('admin.platforms.imports.index') }}">Actualizar avance</a>
            <form method="POST" action="{{ route('admin.platforms.imports.store') }}">@csrf<button class="btn btn-primary" type="submit" @disabled(!$ready)><i class="fas fa-sync-alt mr-1" aria-hidden="true"></i>Importar conexiones configuradas</button></form>
        </div>
        <p class="mt-3">La importación avanza en segundo plano y conserva lo ya descargado al reintentar. Las cuentas de España mantienen sus permisos de solo consulta.</p>
        <p class="text-muted small">Última ejecución del importador: <span @if($lastRun) data-kalendia-instant="{{ $lastRun }}" @endif>{{ $lastRun ?: 'Pendiente de la primera ejecución del scheduler' }}</span></p>
        @unless($ready)<div class="alert alert-info">Falta aplicar la migración de administración de plataformas antes de importar.</div>@endunless
        @include('kalendia.partials.timezone',['timezoneContextHelp'=>'La fecha de la consulta determina el horario de verano.'])
    </div></div>
    <div class="platform-grid">
    @foreach($connections as $connection)
        @php($provider=$connection['provider'])
        @php($account=$connection['account'])
        @php($identity=$provider.'|'.$account.'|'.$connection['mode'].'|')
        @php($connectionCounts=collect($connection['resources'])->sum(fn($resource)=>(int)($counts->get($identity.$resource)?->total??0)))
        <div class="card"><div class="card-body">
            <h2 class="h5">{{ ['stripe'=>'Stripe','kalendia'=>'Kalendia','paypal'=>'PayPal'][$provider] }} · {{ $account==='main' ? 'Principal' : 'España · Histórico' }}</h2>
            <p class="text-muted">{{ $connection['configured'] ? 'Credencial configurada' : 'Pendiente de configurar credencial' }}
                @if($provider==='stripe' && $connection['configured']) · {{ ['live'=>'Producción','test'=>'Pruebas','unknown'=>'Modo sin confirmar'][$connection['mode']] }} @endif
                @if($connection['read_only']) · Solo consulta @endif</p>
            @if($connection['strategy']==='api')
                <p>Transacciones bajo demanda, con los límites de fechas de PayPal.</p>
                <a class="btn btn-outline-primary" href="{{ route('admin.paypal.history') }}">Consultar por API</a>
            @else
                <p><strong>{{ number_format($connectionCounts,0,',','.') }}</strong> registros guardados · {{ count($connection['resources']) }} recursos</p>
                <div class="platform-toolbar mb-3">
                    <a class="btn btn-outline-primary" href="{{ route('admin.platforms.index',[$provider,$account]) }}">Abrir registros</a>
                    <form method="POST" action="{{ route('admin.platforms.imports.store') }}">@csrf<input type="hidden" name="provider" value="{{ $provider }}"><input type="hidden" name="account" value="{{ $account }}"><button class="btn btn-primary" @disabled(!$ready || !$connection['configured'])>Importar recursos</button></form>
                </div>
                <details><summary>Avance por recurso</summary>
                    <div class="table-responsive mt-3" tabindex="0" aria-label="Avance de {{ $provider }} {{ $account }}"><table class="table table-sm">
                        <thead><tr><th>Recurso</th><th>Guardados</th><th>Estado</th><th>Última página recibida</th></tr></thead><tbody>
                        @foreach($connection['resources'] as $resource)
                            @php($state=$states->get($identity.$resource))
                            <tr><td>{{ $catalog->definition($provider,$resource)[0] }}</td><td>{{ number_format((int)($counts->get($identity.$resource)?->total??0),0,',','.') }}</td>
                                <td>@if($state?->error_code)<span class="text-danger">Requiere revisión</span><small class="d-block">{{ $state->error_code }}</small>
                                    @elseif($state?->requested){{ $state->checked_at ? 'En curso' : 'Pendiente' }}
                                    @elseif($state?->checked_at)Completada @else Sin iniciar @endif</td>
                                <td><span @if($state?->checked_at) data-kalendia-instant="{{ \Carbon\Carbon::parse($state->checked_at)->toIso8601String() }}" @endif>{{ $state?->checked_at ?: '—' }}</span></td></tr>
                        @endforeach
                        </tbody></table></div>
                </details>
            @endif
        </div></div>
    @endforeach
    </div>
</div>
@stop
