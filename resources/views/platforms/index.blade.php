@extends('platforms.layout')
@section('platform_title', 'Administración')
@section('platform_content')
<div class="card"><div class="card-body">
    <div class="platform-toolbar justify-content-between">
        <div><h2 class="h5 mb-1">Conexión y sincronización</h2><p class="mb-0 text-muted">{{ $configured ? 'Credencial configurada' : 'Falta configurar la credencial' }}@if($provider==='stripe') · {{ $mode===null ? 'Modo sin confirmar' : ($mode ? 'Producción' : 'Pruebas') }}@endif</p></div>
        <div class="platform-toolbar">
            @if($provider==='stripe')<a class="btn btn-outline-primary" href="{{ route('admin.platforms.audit', [$account, 'category'=>'failures']) }}">Auditoría de pagos</a>@endif
            <form method="POST" action="{{ route('admin.platforms.imports.store') }}">@csrf<input type="hidden" name="provider" value="{{ $provider }}"><input type="hidden" name="account" value="{{ $account }}"><button class="btn btn-primary" type="submit" @disabled(!$configured)>Importar todos los recursos</button></form>
            <form method="POST" action="{{ route('admin.platforms.check', [$provider,$account]) }}">@csrf<button class="btn btn-outline-primary" type="submit">Comprobar conexión</button></form>
        </div>
    </div>
    <p class="text-muted mt-3 mb-0">Los listados consultan registros guardados en la App. Puedes importar datos por páginas y actualizar una ficha cuando necesites comprobar su estado actual.</p>
    @if(session('platform_connection'))<details class="mt-3" open><summary>Resultado de la comprobación</summary><dl class="mt-2">@foreach(session('platform_connection') as $key=>$value)<dt>{{ $key }}</dt><dd class="platform-value">{{ is_array($value)?json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES):$value }}</dd>@endforeach</dl></details>@endif
</div></div>
<div class="platform-grid">
@foreach($resources as $resource=>$definition)
    <div class="card"><div class="card-body d-flex flex-column">
        <h2 class="h5">{{ $definition[0] }}</h2><p class="mb-2"><strong>{{ $counts[$resource]??0 }}</strong> registros guardados</p>
        @php($state=$states[$resource]??null)
        <p class="text-muted small">{{ $state?->requested ? 'Actualización en curso' : ($state?->checked_at ? 'Última importación: '.\Carbon\Carbon::parse($state->checked_at)->format('d/m/Y H:i').' UTC' : 'Pendiente de primera importación') }}@if($state?->error_code)<br>Requiere revisión: {{ $state->error_code }}@endif</p>
        <div class="platform-toolbar mt-auto"><a class="btn btn-primary" href="{{ route('admin.platforms.records', [$provider,$account,$resource]) }}">{{ $readOnly ? 'Consultar' : 'Gestionar' }}</a>
            <form method="POST" action="{{ route('admin.platforms.sync', [$provider,$account,$resource]) }}">@csrf<button class="btn btn-outline-secondary" type="submit" @disabled($state?->requested)>Importar</button></form></div>
    </div></div>
@endforeach
</div>
@if($provider==='kalendia')<div class="card"><div class="card-body"><h2 class="h5">Qué permite gestionar Kalendia</h2><p class="mb-0">Puedes editar contactos, registrar notas, crear citas sin un nuevo cobro, reagendar, cancelar y actualizar respuestas de formularios. Servicios, profesionales, recursos, pedidos y pagos se consultan desde aquí; su configuración y los cobros se administran según los permisos disponibles en Kalendia.</p></div></div>@endif
@endsection
