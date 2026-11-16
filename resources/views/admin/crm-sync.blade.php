@extends('adminlte::page')
@section('title', 'Sincronización CRM')
@section('content_header')<h1>Sincronización CRM</h1>@stop
@section('content')
    @if(session('crm_success'))<div class="alert alert-success" role="status">{{ session('crm_success') }}</div>@endif
    @if(session('crm_error'))<div class="alert alert-danger" role="alert">{{ session('crm_error') }}</div>@endif
    <div class="card"><div class="card-body">
        <h2 class="h5">Clientes y leads</h2>
        <p>Se importa el perfil del contacto cuando tiene al menos un negocio en el proveedor correspondiente, incluidos negocios cerrados o perdidos. Un proyecto o una factura por sí solos no lo convierten en cliente. No se crean cuentas de acceso automáticamente.</p>
        <p>Los contactos sin negocios quedan fuera de la actualización de perfiles. Se conserva su historial anterior.</p>
        <p>Tablas: <strong>{{ $installed ? 'Instaladas' : 'Pendiente de migración' }}</strong> · COS desde datos locales: <strong>{{ \App\Services\CrmContactMirrorService::localReadsEnabled() ? 'Activado' : 'Desactivado' }}</strong></p>
        <p>Procesamiento de eventos: <strong>{{ config('crm_sync.processing_enabled') ? 'Activado' : 'Pausado' }}</strong>. Con el procesamiento pausado se pueden verificar entregas y conservar los eventos sin actualizar perfiles de clientes.</p>
        <p class="text-muted">Activar la recepción no confirma que el proveedor esté enviando eventos. Comprueba una entrega real y la carga inicial antes de cambiar el modo de lectura del COS. Los proyectos clásicos de Teamleader se actualizan por eventos; las facturas y la carga inicial conservan su importador independiente.</p>
        <p><strong>Alcance: campos que alimentan el COS.</strong> La recepción actual actualiza los espejos locales. La escritura automática App ↔ HubSpot ↔ Teamleader y la resolución de conflictos por campo siguen pendientes de implementación y validación.</p>
        <details class="mb-3">
            <summary>Ver campos incluidos en la sincronización del COS</summary>
            <dl class="row mt-3">
                <dt class="col-12 col-md-3">Contacto HubSpot</dt>
                <dd class="col-12 col-md-9 text-break">{{ implode(', ', \App\Services\CosCrmSyncFields::HUBSPOT_CONTACT) }}</dd>
                <dt class="col-12 col-md-3">Negocio HubSpot</dt>
                <dd class="col-12 col-md-9 text-break">{{ implode(', ', \App\Services\CosCrmSyncFields::HUBSPOT_DEAL) }}</dd>
                <dt class="col-12 col-md-3">Proyecto Teamleader</dt>
                <dd class="col-12 col-md-9">Servicio, código de proceso, certificado e importes preestablecidos/pagados de fases 1, 2, 3, Carta de Naturaleza y CIL / FCJE. Se utiliza el mismo catálogo de campos del cálculo financiero.</dd>
                <dt class="col-12 col-md-3">Datos locales</dt>
                <dd class="col-12 col-md-9">Solicitudes de documentos, recibos, contrato, permisos y resultados calculados conservan su origen y controles en la App. No se convierten en propiedades arbitrarias del CRM.</dd>
            </dl>
        </details>
        @foreach(['hubspot' => 'HubSpot', 'teamleader' => 'Teamleader'] as $provider => $label)
            <div class="border rounded p-3 mb-3">
                <h3 class="h6">{{ $label }} · Recepción {{ config("crm_sync.$provider.enabled") ? 'habilitada' : 'deshabilitada' }}</h3>
                <code class="text-break">{{ route('api.crm.webhook', ['provider' => $provider]) }}</code>
                @if($provider === 'teamleader')<p class="small text-muted mb-0">El registro desde el servidor añade el token secreto al destino. No compartas esa URL completa.</p>@endif
                @if($provider === 'hubspot')
                    <p class="small mb-0 mt-2">Firma configurada: {{ config('crm_sync.hubspot.signature_version') }}.</p>
                    @foreach($hubspotReadiness as $name => $ready)<p class="small mb-0">{{ $name }}: {{ $ready ? 'Configurado' : 'Pendiente' }}.</p>@endforeach
                @else
                    @if($teamleaderMissing !== [])<p class="small mt-2">Pendiente: {{ implode(', ', $teamleaderMissing) }}.</p>@endif
                    <form method="POST" action="{{ route('admin.integrations.crm-sync.teamleader.register') }}" class="mt-3">
                        @csrf
                        <button class="btn sefar-button cfrSefar" @disabled(! $installed || $teamleaderMissing !== [])>Registrar webhooks de Teamleader</button>
                    </form>
                @endif
            </div>
        @endforeach
        <div class="table-responsive"><table class="table table-sm"><thead><tr><th>Proveedor</th><th>Clasificación</th><th>Contactos</th></tr></thead><tbody>
            @forelse($counts as $count)<tr><td>{{ $count->provider }}</td><td>{{ ['client' => 'Con negocios', 'lead' => 'Sin negocios', 'deleted' => 'Eliminado en origen'][$count->classification] ?? $count->classification }}</td><td>{{ $count->total }}</td></tr>
            @empty<tr><td colspan="3" class="text-muted">Todavía no hay contactos evaluados.</td></tr>@endforelse
        </tbody></table></div>
    </div></div>
    <div class="card"><div class="card-header"><h2 class="card-title">Últimos eventos recibidos</h2></div><div class="card-body table-responsive">
        <table class="table table-hover"><thead><tr><th>Referencia</th><th>Proveedor</th><th>Estado</th><th>Intentos</th><th>Recibido</th><th>Procesado</th><th>Error</th></tr></thead><tbody>
            @forelse($events as $event)<tr><td>{{ $event->id }}</td><td>{{ $event->provider }}</td><td>{{ $event->status }}</td><td>{{ $event->attempts }}</td><td>{{ $event->created_at }}</td><td>{{ $event->processed_at ?? '—' }}</td><td>{{ $event->error_class ?? '—' }}</td></tr>
            @empty<tr><td colspan="7" class="text-muted">Todavía no se han recibido eventos.</td></tr>@endforelse
        </tbody></table>
    </div></div>
@stop
