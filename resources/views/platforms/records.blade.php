@extends('platforms.layout')
@section('platform_title', $definition[0])
@section('platform_content')
<div class="card"><div class="card-body">
    <div class="platform-toolbar justify-content-between mb-3"><h2 class="h5 mb-0">{{ $definition[0] }}</h2>
        @if(!$readOnly && $resource==='customers')<button class="btn btn-primary" type="button" data-platform-template="#platform-new-customer">Crear contacto</button>@endif
        <form method="POST" action="{{ route('admin.platforms.sync', [$provider,$account,$resource]) }}">@csrf<button class="btn btn-outline-primary" type="submit" @disabled($state?->requested)><i class="fas fa-sync-alt mr-1" aria-hidden="true"></i>{{ $state?->requested ? 'Importando…':'Actualizar desde '.$platformName }}</button></form>
    </div>
    @if($state?->error_code)<div class="alert alert-warning">La última importación requiere revisión: {{ $state->error_code }}. Los registros anteriores se conservan.</div>@endif
    <form method="GET" class="platform-toolbar align-items-end mb-3">
        <div class="platform-filter"><label for="platform-search">Nombre, correo o identificador</label><input class="form-control" id="platform-search" name="q" value="{{ request('q') }}" maxlength="190"></div>
        <div><label for="platform-status">Estado</label><input class="form-control" id="platform-status" name="status" value="{{ request('status') }}" maxlength="80"></div>
        <button class="btn btn-primary" type="submit">Buscar en la App</button>
    </form>
    <details class="mb-3"><summary>Buscar un registro directamente en {{ $platformName }}</summary>
        <form method="POST" action="{{ route('admin.platforms.lookup',[$provider,$account,$resource]) }}" data-platform-lookup class="platform-toolbar align-items-end mt-2">@csrf
            <div class="platform-filter"><label for="platform-remote">Identificador completo</label><input class="form-control" id="platform-remote" name="remote_id" required maxlength="190" placeholder="{{ $definition[1] ? $definition[1].'_…':'Identificador del registro' }}"></div><button class="btn btn-outline-primary" type="submit">Consultar plataforma</button>
        </form>
    </details>
    <div class="table-responsive" tabindex="0" aria-label="Registros de {{ $definition[0] }}"><table class="table table-striped">
        <thead><tr><th>Registro</th><th>Contacto</th><th>Estado</th><th>Importe</th><th>Última consulta</th><th>Acción</th></tr></thead>
        <tbody>@forelse($records as $record)
            @php($data=$record->data)
            <tr><td><strong>{{ $record->name ?: $record->remote_id }}</strong><small class="d-block text-muted platform-value">{{ $record->remote_id }}</small></td><td class="platform-value">{{ $record->email ?: '—' }}</td>
                <td>{{ \App\Services\PlatformPresentation::status($record->status) }}</td>
                <td class="text-nowrap">{{ \App\Services\PlatformPresentation::amount($record->provider,$data) }}</td>
                <td><span data-kalendia-instant="{{ $record->checked_at?->toIso8601String() }}">{{ $record->checked_at?->format('d/m/Y H:i') }} UTC</span></td>
                <td><button class="btn btn-outline-primary" type="button" data-platform-record="{{ route('admin.platforms.show',[$provider,$account,$record]) }}">{{ $readOnly ? 'Consultar' : 'Gestionar' }}</button></td></tr>
        @empty<tr><td colspan="6" class="text-muted">No hay registros con esos filtros. Puedes iniciar la importación o consultar un identificador directamente.</td></tr>@endforelse</tbody>
    </table></div>
    {{ $records->links() }}
</div></div>
@if(!$readOnly && $resource==='customers')
<template id="platform-new-customer">
    <h3 class="h5">Crear contacto en {{ $platformName }}</h3>
    <form method="POST" action="{{ route('admin.platforms.customers.store',[$provider,$account]) }}" data-platform-form>@csrf<input type="hidden" name="operation_id" value="{{ \Illuminate\Support\Str::uuid() }}">
        <div class="form-group"><label for="new-contact-name">Nombre completo</label><input class="form-control" id="new-contact-name" name="name" required maxlength="190"></div>
        <div class="form-group"><label for="new-contact-email">Correo</label><input class="form-control" type="email" id="new-contact-email" name="email" maxlength="190"></div>
        <div class="form-group"><label for="new-contact-phone">Teléfono</label><input class="form-control" id="new-contact-phone" name="phone" maxlength="80"></div>
        @if($provider==='kalendia')<div class="form-group"><label for="new-contact-timezone">Zona horaria</label><select class="form-control" id="new-contact-timezone" name="timezone">@foreach(['America/Bogota','America/Caracas','America/Santiago','Europe/Madrid'] as $zone)<option>{{ $zone }}</option>@endforeach</select></div>@endif
        <p class="text-muted">Introduce al menos un correo o un teléfono. Este registro no crea una cuenta de acceso en la App.</p>
        <label class="d-block"><input type="checkbox" name="confirmed" value="1" required> Confirmo el registro de estos datos en {{ $platformName }}.</label><button class="btn btn-primary" type="submit">Crear contacto</button>
    </form>
</template>
@endif
@endsection
