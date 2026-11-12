@extends('adminlte::page')
@section('title', 'Bloqueos de clientes')
@section('content_header')
    <h1>Bloqueos de clientes</h1>
@stop
@section('content')
    @if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
    <div class="card"><div class="card-body">
        <h2 class="h5">Expedientes y entregas suspendidos</h2>
        <p>El cliente solo puede consultar el motivo del bloqueo y los datos de contacto de la empresa. Solo administradores y personal con permiso de revisión pueden consultar su expediente.</p>
        <p class="text-muted mb-0">Una marca sincronizada en HubSpot, Monday o Teamleader requiere que los permisos y flujos de esa plataforma impidan las entregas. Este panel no presenta una marca como garantía de restricción nativa.</p>
    </div></div>
    @if(auth()->user()->hasRole('Administrador'))
        <div class="card"><div class="card-header"><h2 class="card-title">Detectar registros de prueba</h2></div><div class="card-body">
            <p>Revisa nombres, apellidos, correo, pasaporte y otros campos del cliente, incluidos campos adicionales y datos de Teamleader ya importados y vinculados. Las cuentas del personal quedan excluidas. No modifica registros durante la detección.</p>
            <form method="POST" action="{{ route('admin.client-restrictions.scan') }}">@csrf
                <div class="custom-control custom-checkbox mb-3"><input class="custom-control-input" type="checkbox" name="partial" value="1" id="partial-matches"><label class="custom-control-label" for="partial-matches">Incluir coincidencias parciales de «prueba» o «test» (puede incluir nombres o palabras reales)</label></div>
                <button class="btn sefar-button cfrSefar" type="submit">Revisar coincidencias</button>
            </form>
        </div></div>
    @endif
    @if($scan)
        <div class="card"><div class="card-header"><h2 class="card-title">Vista previa: {{ count($scan->candidates) }} coincidencias</h2></div><div class="card-body">
            <p>Selecciona los registros que corresponden a pruebas. La vista previa vence a los 30 minutos; los datos se comprueban otra vez antes de bloquear.</p>
            @if(!$scan->applied_at && $scan->expires_at->isFuture())
                <form method="POST" action="{{ route('admin.client-restrictions.apply', $scan) }}">@csrf
                    <div class="table-responsive"><table class="table table-hover"><thead><tr><th>Bloquear</th><th>Cliente</th><th>Coincidencias</th></tr></thead><tbody>
                        @forelse($scan->candidates as $candidate)
                            <tr><td><input type="checkbox" name="ids[]" value="{{ $candidate['id'] }}" aria-label="Seleccionar {{ $candidate['name'] }}"></td><td>{{ $candidate['name'] }}<br>{{ $candidate['email'] }}</td><td>@foreach($candidate['matches'] as $field => $value)<div><strong>{{ $field }}:</strong> {{ $value }}</div>@endforeach</td></tr>
                        @empty<tr><td colspan="3">No se encontraron registros de prueba.</td></tr>@endforelse
                    </tbody></table></div>
                    @if(count($scan->candidates))
                        <div class="custom-control custom-checkbox mb-3"><input class="custom-control-input" type="checkbox" name="confirm" value="1" required id="confirm-test-block"><label class="custom-control-label" for="confirm-test-block">Revisé la selección y confirmo la suspensión de acceso y entregas de estos clientes.</label></div>
                        <button class="btn sefar-button cfrSefar" type="submit">Bloquear seleccionados</button>
                        <button class="btn sefar-button cfrSefar mt-2 mt-sm-0" type="submit" name="apply_all" value="1">Bloquear todos los resultados revisados</button>
                    @endif
                </form>
            @else<p>Esta vista previa ya fue aplicada o venció. Genera una nueva.</p>@endif
        </div></div>
    @endif
    <div class="card"><div class="card-body">
        <form method="GET" class="form-inline mb-3"><label for="blocked-search" class="sr-only">Buscar cliente bloqueado</label><input id="blocked-search" class="form-control mr-2 mb-2" name="q" value="{{ request('q') }}" placeholder="Nombre o correo"><button class="btn sefar-button cfrSefar mb-2">Buscar</button></form>
        <div class="table-responsive"><table class="table table-hover"><thead><tr><th>Cliente</th><th>Motivo visible al cliente</th><th>Revisión interna</th><th>Plataformas</th><th>Resolver</th></tr></thead><tbody>
            @forelse($clients as $client)
                <tr><td>{{ $client->name }}<br>{{ $client->email }}</td><td>{{ $client->clientBlockReasonDescription() }}</td><td>{{ $client->accessBlock->internal_note }}<br><small>Origen: {{ $client->accessBlock->source ?? 'manual' }}</small></td><td>
                    <strong>App: bloqueado</strong>
                    @foreach($syncs->get($client->id, collect()) as $sync)
                        <div>{{ ucfirst($sync->platform) }}: {{ ['pending' => 'Pendiente', 'configuration_required' => 'Requiere configuración', 'failed' => 'Error; se reintentará', 'marker_synced' => 'Marca sincronizada'][$sync->status] ?? $sync->status }}</div>
                    @endforeach
                </td><td>
                    @if(auth()->user()->hasRole('Administrador'))
                        <form method="POST" action="{{ route('admin.client-restrictions.unblock', $client) }}">@csrf
                            <label class="sr-only" for="resolution-{{ $client->id }}">Resolución del bloqueo</label><textarea class="form-control mb-2" id="resolution-{{ $client->id }}" name="explanation" required minlength="10" maxlength="2000" placeholder="Cómo se resolvió la situación"></textarea>
                            <button class="btn sefar-button cfrSefar" type="submit">Registrar resolución y desbloquear</button>
                        </form>
                    @else<span>Requiere un administrador</span>@endif
                </td></tr>
            @empty<tr><td colspan="5">No hay expedientes bloqueados con este filtro.</td></tr>@endforelse
        </tbody></table></div>
        {{ $clients->links() }}
    </div></div>
    <div class="card"><div class="card-header"><h2 class="card-title">Últimas acciones</h2></div><div class="card-body"><ul class="mb-0">@foreach($events as $event)<li>{{ $event->created_at }} · Cliente #{{ $event->user_id }} · {{ $event->action === 'blocked' ? 'Bloqueado' : 'Desbloqueado' }} · {{ $event->source }} · Responsable #{{ $event->actor_id ?? 'automático' }}</li>@endforeach</ul></div></div>
@stop
