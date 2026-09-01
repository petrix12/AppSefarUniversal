@extends('adminlte::page')

@section('title', 'Mapa de unificación')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="mb-0"><i class="fas fa-project-diagram mr-2 text-primary"></i>Mapa de unificación</h1>
            <small class="text-muted">Inventario y decisiones de diseño: App, HubSpot, Teamleader y Monday.</small>
        </div>
        <div class="btn-group">
            <a href="{{ route('unification-map.diagram') }}" class="btn btn-outline-primary"><i class="fas fa-project-diagram mr-1"></i>Diagrama ER</a>
            <button type="button" class="btn btn-outline-secondary refresh-unification-catalogues"><i class="fas fa-cloud-download-alt mr-1"></i>Actualizar catálogos</button>
            @if($summary['relation_storage_ready'])
                <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#relationSetupModal">
                    <i class="fas fa-plus mr-1"></i>Relacionar dos plataformas
                </button>
            @else
                <button type="button" class="btn btn-outline-secondary" disabled title="La tabla de auditoría aún no está desplegada">
                    <i class="fas fa-lock mr-1"></i>Modo lectura
                </button>
            @endif
        </div>
    </div>
@stop

@section('content')
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif
    @if(isset($errors) && $errors->any())
        <div class="alert alert-danger mb-3">{{ $errors->first() }}</div>
    @endif

    <div class="alert alert-warning border-warning audit-notice">
        <i class="fas fa-shield-alt mr-1"></i>
        <strong>Auditoría primero.</strong> Este mapa lee catálogos locales y, cuando pulsas <em>Actualizar catálogos</em>, solo metadatos remotos de campo (nombre, clave/ID, tipo y módulo).
        No lee valores de contactos, negocios, proyectos o items. No crea campos en <code>users</code>, no crea <code>integration_field_mappings</code>, no activa automatizaciones y no mueve clientes entre tableros.
        <br><strong>Entidades separadas:</strong> contacto/cliente no equivale a negocio. El mapa distingue <code>users ↔ HubSpot Contacts ↔ Teamleader Contacts</code> de <code>negocios ↔ HubSpot Deals ↔ Teamleader Projects</code> y no propone cruces entre ambos.
    </div>

    @if(! $summary['relation_storage_ready'])
        <div class="alert alert-info">
            El registro para guardar relaciones está preparado en código, pero su migración no se ha ejecutado. Por ahora este mapa es estrictamente de lectura.
        </div>
    @endif

    <div class="row">
        <div class="col-lg-3 col-sm-6">
            <div class="small-box bg-info"><div class="inner"><h3>{{ $summary['hubspot_fields'] }}</h3><p>{{ $summary['hubspot_contact_fields'] }} Contacts · {{ $summary['hubspot_deal_fields'] }} Deals</p></div><div class="icon"><i class="fab fa-hubspot"></i></div></div>
        </div>
        <div class="col-lg-3 col-sm-6">
            <div class="small-box bg-success"><div class="inner"><h3>{{ $summary['teamleader_fields'] }}</h3><p>{{ $summary['teamleader_contact_fields'] }} contactos · {{ $summary['teamleader_deal_fields'] }} deals · {{ $summary['teamleader_project_fields'] }} proyectos</p></div><div class="icon"><i class="fas fa-people-arrows"></i></div></div>
        </div>
        <div class="col-lg-3 col-sm-6">
            <div class="small-box bg-primary"><div class="inner"><h3>{{ $summary['app_fields'] }}</h3><p>{{ $summary['client_app_fields'] }} clientes · {{ $summary['business_app_fields'] }} negocios</p></div><div class="icon"><i class="fas fa-database"></i></div></div>
        </div>
        <div class="col-lg-3 col-sm-6">
            <div class="small-box bg-warning"><div class="inner"><h3>{{ $summary['monday_fields'] }}</h3><p>Columnas Monday detectadas</p></div><div class="icon"><i class="fab fa-trello"></i></div></div>
        </div>
    </div>

    <div class="card card-outline card-primary">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-sitemap mr-1"></i>Diagrama de la relación seleccionada</h3>
            <div class="card-tools">
                <span id="selected-map-status" class="badge badge-secondary">Selecciona una fila</span>
            </div>
        </div>
        <div class="card-body">
            <p id="selected-map-hint" class="text-muted mb-3">Elige un campo abajo para ver sus conexiones propuestas. Las líneas punteadas son coincidencias sugeridas, no integraciones activas.</p>
            <div class="relationship-canvas" aria-live="polite">
                <svg class="relationship-lines" viewBox="0 0 1000 270" preserveAspectRatio="none" aria-hidden="true">
                    <line id="line-hubspot-app" x1="230" y1="82" x2="410" y2="135"></line>
                    <line id="line-teamleader-app" x1="770" y1="82" x2="590" y2="135"></line>
                    <line id="line-monday-app" x1="500" y1="230" x2="500" y2="165"></line>
                </svg>
                <div class="entity-card entity-hubspot"><div class="entity-title"><i class="fab fa-hubspot"></i> HubSpot</div><div id="node-hubspot" class="entity-content">—</div></div>
                <div class="entity-card entity-teamleader"><div class="entity-title"><i class="fas fa-people-arrows"></i> Teamleader</div><div id="node-teamleader" class="entity-content">—</div></div>
                <div class="entity-card entity-app"><div class="entity-title"><i class="fas fa-database"></i> App (canónica)</div><div id="node-app" class="entity-content">—</div></div>
                <div class="entity-card entity-monday"><div class="entity-title"><i class="fab fa-trello"></i> Monday</div><div id="node-monday" class="entity-content">—</div></div>
            </div>
            <div class="mt-3 small text-muted"><i class="fas fa-circle text-success"></i> Catálogo legado o decisión aprobada &nbsp; <i class="fas fa-circle text-warning"></i> Coincidencia por revisar &nbsp; <i class="fas fa-circle text-secondary"></i> Sin relación conocida</div>
        </div>
    </div>

    <div class="card card-outline card-secondary">
        <div class="card-header">
            <h3 class="card-title">Inventario de relaciones</h3>
            <form method="GET" action="{{ route('unification-map.index') }}" class="card-tools d-flex align-items-center">
                <input name="q" type="search" class="form-control form-control-sm mr-1" value="{{ request('q') }}" placeholder="Buscar campo, etiqueta o tablero">
                <select name="per_page" class="form-control form-control-sm mr-1" aria-label="Filas por página">
                    @foreach([25, 50, 100] as $pageSize)<option value="{{ $pageSize }}" @selected((int) request('per_page', 25) === $pageSize)>{{ $pageSize }}</option>@endforeach
                </select>
                <button class="btn btn-sm btn-outline-primary" title="Buscar"><i class="fas fa-search"></i></button>
            </form>
        </div>
        <div class="card-body p-0 table-responsive">
            <table class="table table-hover table-sm mb-0" id="map-table">
                <thead><tr><th>Campo App</th><th>Entidad</th><th>HubSpot</th><th>Teamleader</th><th>Monday</th><th>Base de coincidencia</th><th>Auditoría</th><th></th></tr></thead>
                <tbody>
                @forelse($map_rows as $index => $row)
                    @php
                        $app = $row['app'];
                        $hubspot = $row['hubspot'];
                        $teamleader = $row['teamleader'];
                        $monday = $row['monday_matches'];
                        $audit = $row['audit_links'];
                    @endphp
                    <tr data-map-row="{{ $index }}">
                        <td>
                            @if($app)
                                <strong>{{ $app['label'] }}</strong><br><code>{{ $app['key'] }}</code><br><small class="text-muted">{{ $app['source'] }}</small>
                            @else
                                <span class="text-muted">Sin campo propuesto</span>
                            @endif
                        </td>
                        <td>
                            @php
                                $entityType = $row['entity_type'] ?? ($app['entity_type'] ?? 'item');
                            @endphp
                            @if(in_array($entityType, ['client', 'contact']))<span class="badge badge-primary">Contacto / cliente</span>
                            @elseif(in_array($entityType, ['business', 'deal', 'project']))<span class="badge badge-success">Negocio / proyecto</span>
                            @elseif($entityType === 'item')<span class="badge badge-warning">Item por clasificar</span>
                            @else<span class="badge badge-secondary">{{ $entityType }}</span>@endif
                        </td>
                        <td>
                            @forelse($hubspot as $field)<div><code>{{ $field['key'] }}</code></div>@empty<span class="text-muted">—</span>@endforelse
                        </td>
                        <td>
                            @forelse($teamleader as $field)<div title="{{ $field['key'] }}"><strong>{{ $field['label'] }}</strong><br><small class="text-muted">{{ $field['context'] }} · {{ $field['type'] ?: 'tipo sin catalogar' }}</small></div>@empty<span class="text-muted">—</span>@endforelse
                        </td>
                        <td>
                            @forelse($monday as $field)<div><strong>{{ $field['label'] }}</strong><br><small class="text-muted">{{ $field['scope_label'] ?? 'Tablero '.$field['scope_key'] }} · {{ $field['key'] }} · {{ isset($field['confidence']) && $field['confidence'] !== null ? $field['confidence'].'%' : 'pendiente de asociar' }}</small></div>@empty<span class="text-muted">Sin coincidencia automática</span>@endforelse
                        </td>
                        <td>
                            @if($row['match_method'] === 'legacy_catalog')<span class="badge badge-success">Catálogo legado</span>
                            @elseif($row['match_method'] === 'app_only')<span class="badge badge-secondary">Solo App</span>
                            @elseif($row['match_method'] === 'commercial_catalogue')<span class="badge badge-info">Catálogo comercial local</span>
                            @elseif($row['match_method'] === 'unmatched')<span class="badge badge-warning">Pendiente de asociar</span>
                            @else<span class="badge badge-secondary">{{ $row['match_method'] }}</span>@endif
                        </td>
                        <td>
                            @forelse($audit as $decision)<span class="badge badge-{{ $decision['status'] === 'approved' ? 'success' : ($decision['status'] === 'rejected' ? 'danger' : ($decision['status'] === 'needs_information' ? 'warning' : 'secondary')) }} d-block mb-1">{{ $decision['status'] }}</span>@empty<span class="text-muted">No revisado</span>@endforelse
                        </td>
                        <td class="text-right"><button type="button" class="btn btn-sm btn-outline-primary select-map" data-index="{{ $index }}">Ver</button></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted py-4">No se encontraron catálogos locales para auditar todavía.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">
            <div class="text-muted small mb-2">
                Mostrando {{ $map_rows_pagination->firstItem() ?? 0 }}-{{ $map_rows_pagination->lastItem() ?? 0 }} de {{ $map_rows_pagination->total() }} relaciones.
                {{ $summary['legacy_associations'] }} asociaciones históricas; {{ $summary['app_legacy_columns'] }} tienen columna física detectada en <code>users</code>.
                @if($summary['active_mappings'])
                    Hay {{ $summary['active_mappings'] }} mapeos operativos ya existentes: se muestran como contexto, pero este módulo no los modifica.
                @else
                    No se detectaron mapeos operativos activos en la nueva capa.
                @endif
            </div>
            {{ $map_rows_pagination->onEachSide(1)->links('pagination::bootstrap-4') }}
        </div>
    </div>

    <div class="card card-outline card-warning">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-link mr-1"></i>Relaciones directas y derivadas</h3>
            <div class="card-tools"><span class="badge badge-info mr-1">{{ $summary['automatic_relations'] }} automáticas</span><span class="badge badge-warning">{{ $summary['derived_relations'] }} derivadas</span></div>
        </div>
        <div class="card-body">
            <p class="mb-2">Puedes relacionar cualquier par de plataformas. Cuando haya relaciones directas aprobadas o históricas, el mapa propone cadenas del tipo <code>A ↔ B ↔ C</code>; la relación <code>A ↔ C</code> siempre queda como sugerencia y requiere revisión independiente.</p>
            <p class="small text-muted mb-0">Para retirar una propuesta de este mapa usa <em>Desasociar</em>: queda rechazada con su historial de auditoría. Las asociaciones heredadas siguen siendo solo de lectura y no se eliminan desde aquí.</p>
            @if($summary['direct_audit_relations'])
                <p class="small text-muted mb-0">{{ $summary['direct_audit_relations'] }} relación(es) directa(s) guardada(s) en auditoría.</p>
            @endif
        </div>
        <div class="card-body border-top p-0 table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead><tr><th>Origen</th><th>Puente</th><th>Destino sugerido</th><th>Base</th><th></th></tr></thead>
                <tbody>
                @forelse($derived_relations as $index => $relation)
                    <tr>
                        <td><strong>{{ ucfirst($relation['left']['provider']) }}</strong><br><small>{{ $relation['left']['label'] }} · <code>{{ $relation['left']['key'] }}</code></small></td>
                        <td><strong>{{ ucfirst($relation['through']['provider']) }}</strong><br><small>{{ $relation['through']['label'] }} · <code>{{ $relation['through']['key'] }}</code></small></td>
                        <td><strong>{{ ucfirst($relation['right']['provider']) }}</strong><br><small>{{ $relation['right']['label'] }} · <code>{{ $relation['right']['key'] }}</code></small></td>
                        <td><small>{{ implode(' + ', $relation['basis']) }}</small></td>
                        <td class="text-right">
                            @if($summary['relation_storage_ready'])
                                <button type="button" class="btn btn-xs btn-outline-primary use-derived-relation" data-index="{{ $index }}">Convertir en propuesta</button>
                            @else
                                <span class="text-muted small">Solo lectura</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-3">Aún no hay cadenas entre plataformas para proponer.</td></tr>
                @endforelse
                </tbody>
            </table>
            <div class="px-3 py-2">{{ $derived_relations_pagination->onEachSide(1)->links('pagination::bootstrap-4') }}</div>
        </div>
        <div class="card-body border-top p-0 table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead>
                    <tr><th colspan="5" class="bg-light">
                        <div class="d-flex flex-wrap align-items-center justify-content-between">
                            <span>Coincidencias automáticas entre catálogos locales</span>
                            <div class="d-flex align-items-center mt-1 mt-sm-0">
                                <select id="automatic-left-provider" class="form-control form-control-sm mr-1" aria-label="Primera plataforma"><option value="">Todas</option><option value="app">App</option><option value="hubspot">HubSpot</option><option value="teamleader">Teamleader</option></select>
                                <select id="automatic-right-provider" class="form-control form-control-sm mr-1" aria-label="Segunda plataforma"><option value="">Todas</option><option value="app">App</option><option value="hubspot">HubSpot</option><option value="teamleader">Teamleader</option></select>
                                <button type="button" id="load-automatic-relations" class="btn btn-sm btn-outline-secondary"><i class="fas fa-sync-alt mr-1"></i>Cargar</button>
                            </div>
                        </div>
                    </th></tr>
                    <tr><th>Primera plataforma</th><th>Segunda plataforma</th><th>Confianza</th><th>Motivo</th><th></th></tr>
                </thead>
                <tbody id="automatic-relations-body">
                    <tr><td colspan="5" class="text-center text-muted py-3">Carga las coincidencias cuando las necesites.</td></tr>
                </tbody>
            </table>
            <div class="px-3 py-2 d-flex justify-content-between align-items-center">
                <small id="automatic-relations-meta" class="text-muted"></small>
                <div class="btn-group">
                    <button type="button" id="automatic-relations-prev" class="btn btn-xs btn-outline-secondary" disabled><i class="fas fa-chevron-left"></i></button>
                    <button type="button" id="automatic-relations-next" class="btn btn-xs btn-outline-secondary" disabled><i class="fas fa-chevron-right"></i></button>
                </div>
            </div>
        </div>
        @if($summary['relation_storage_ready'])
            <div class="card-body border-top p-0 table-responsive">
                <table class="table table-sm mb-0"><thead><tr><th>Relación directa registrada</th><th>Estado</th><th>Notas</th><th class="text-right">Decidir</th></tr></thead><tbody>
                @forelse($audited_relations as $relation)
                    <tr>
                        <td><strong>{{ ucfirst($relation['left']['provider']) }}</strong> · {{ $relation['left']['label'] }} <code>{{ $relation['left']['key'] }}</code><br><i class="fas fa-arrows-alt-h text-muted mx-1"></i><strong>{{ ucfirst($relation['right']['provider']) }}</strong> · {{ $relation['right']['label'] }} <code>{{ $relation['right']['key'] }}</code></td>
                        <td><span class="badge badge-{{ $relation['status'] === 'approved' ? 'success' : ($relation['status'] === 'rejected' ? 'danger' : ($relation['status'] === 'needs_information' ? 'warning' : 'secondary')) }}">{{ $relation['status'] }}</span></td>
                        <td><small>{{ $relation['notes'] ?: '—' }}</small></td>
                        <td class="text-right">
                            <form action="{{ route('unification-map.relations.review', $relation['id']) }}" method="POST" class="d-inline">@csrf @method('PATCH')<input type="hidden" name="status" value="approved"><input type="hidden" name="notes" value="{{ $relation['notes'] }}"><button class="btn btn-xs btn-outline-success">Aprobar diseño</button></form>
                            <form action="{{ route('unification-map.relations.review', $relation['id']) }}" method="POST" class="d-inline">@csrf @method('PATCH')<input type="hidden" name="status" value="needs_information"><input type="hidden" name="notes" value="{{ $relation['notes'] }}"><button class="btn btn-xs btn-outline-warning">Pedir info</button></form>
                            <form action="{{ route('unification-map.relations.review', $relation['id']) }}" method="POST" class="d-inline">@csrf @method('PATCH')<input type="hidden" name="status" value="rejected"><input type="hidden" name="notes" value="{{ $relation['notes'] }}"><button class="btn btn-xs btn-outline-danger" title="No borra el historial de auditoría">Desasociar</button></form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-muted py-3">Aún no has registrado conexiones manuales entre plataformas.</td></tr>
                @endforelse
                </tbody></table>
                <div class="px-3 py-2">{{ $audited_relations_pagination->onEachSide(1)->links('pagination::bootstrap-4') }}</div>
            </div>
        @endif
    </div>

    @if($summary['audit_storage_ready'])
        <div class="card card-outline card-info">
            <div class="card-header"><h3 class="card-title">Decisiones pendientes de revisión</h3></div>
            <div class="card-body p-0 table-responsive">
                <table class="table table-sm mb-0"><thead><tr><th>Campo App propuesto</th><th>Plataforma / campo</th><th>Estado</th><th>Notas</th><th class="text-right">Decidir</th></tr></thead><tbody>
                @php
                    $decisions = collect($audited_links)->unique('id')->values();
                @endphp
                @forelse($decisions as $decision)
                    <tr>
                        <td><strong>{{ $decision['app_field_label'] }}</strong><br><code>{{ $decision['app_field_key'] }}</code></td>
                        <td>{{ ucfirst($decision['provider']) }}<br><code>{{ $decision['scope_key'] }} · {{ $decision['external_field_key'] }}</code></td>
                        <td><span class="badge badge-secondary">{{ $decision['status'] }}</span></td>
                        <td><small>{{ $decision['notes'] ?: '—' }}</small></td>
                        <td class="text-right">
                            <form action="{{ route('unification-map.review', $decision['id']) }}" method="POST" class="d-inline">@csrf @method('PATCH')<input type="hidden" name="status" value="approved"><input type="hidden" name="notes" value="{{ $decision['notes'] }}"><button class="btn btn-xs btn-outline-success">Aprobar diseño</button></form>
                            <form action="{{ route('unification-map.review', $decision['id']) }}" method="POST" class="d-inline">@csrf @method('PATCH')<input type="hidden" name="status" value="needs_information"><input type="hidden" name="notes" value="{{ $decision['notes'] }}"><button class="btn btn-xs btn-outline-warning">Pedir info</button></form>
                            <form action="{{ route('unification-map.review', $decision['id']) }}" method="POST" class="d-inline">@csrf @method('PATCH')<input type="hidden" name="status" value="rejected"><input type="hidden" name="notes" value="{{ $decision['notes'] }}"><button class="btn btn-xs btn-outline-danger" title="No borra el historial de auditoría">Desasociar</button></form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-3">Aún no hay propuestas manuales.</td></tr>
                @endforelse
                </tbody></table>
                <div class="px-3 py-2">{{ $audited_links_pagination->onEachSide(1)->links('pagination::bootstrap-4') }}</div>
            </div>
        </div>
    @endif

    @if($summary['audit_storage_ready'])
        <div class="modal fade" id="newAuditLinkModal" tabindex="-1" role="dialog" aria-labelledby="newAuditLinkTitle" aria-hidden="true">
            <div class="modal-dialog modal-lg" role="document"><form method="POST" action="{{ route('unification-map.store') }}" class="modal-content">@csrf
                <div class="modal-header"><h5 class="modal-title" id="newAuditLinkTitle">Proponer una relación para auditoría</h5><button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button></div>
                <div class="modal-body">
                    <div class="alert alert-info small mb-3">Esta propuesta es un apunte de arquitectura. No crea el campo, no copia valores y no sincroniza ninguna plataforma.</div>
                    <div class="form-row">
                        <div class="form-group col-md-6"><label>Identificador del campo App</label><input required name="app_field_key" class="form-control" placeholder="estado_documental"></div>
                        <div class="form-group col-md-6"><label>Etiqueta para el mapa</label><input required name="app_field_label" class="form-control" placeholder="Estado documental"></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-4"><label>Plataforma</label><select name="provider" id="audit-provider" class="form-control"><option value="hubspot">HubSpot</option><option value="teamleader">Teamleader</option><option value="monday">Monday</option></select></div>
                        <div class="form-group col-md-4"><label>Entidad</label><input required name="external_entity_type" class="form-control" value="contact"></div>
                        <div class="form-group col-md-4"><label>Ámbito / tablero</label><input name="scope_key" class="form-control" value="*" placeholder="* o ID de tablero"></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6"><label>Clave del campo externo</label><input required name="external_field_key" class="form-control" placeholder="property o column_id"></div>
                        <div class="form-group col-md-6"><label>Etiqueta externa</label><input name="external_field_label" class="form-control" placeholder="Nombre visible en la plataforma"></div>
                    </div>
                    <div class="form-group mb-0"><label>Notas de auditoría</label><textarea name="notes" rows="3" class="form-control" placeholder="Origen, responsable, dudas, regla de conflicto…"></textarea></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button><button class="btn btn-primary">Guardar propuesta</button></div>
            </form></div>
        </div>
    @endif

    @if($summary['relation_storage_ready'])
        <div class="modal fade" id="relationSetupModal" tabindex="-1" role="dialog" aria-labelledby="relationSetupTitle" aria-hidden="true">
            <div class="modal-dialog modal-lg" role="document"><div class="modal-content">
                <div class="modal-header"><h5 class="modal-title" id="relationSetupTitle">Paso 1 de 2 · Elegir plataformas y módulos</h5><button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button></div>
                <div class="modal-body">
                    <div class="alert alert-info small">Elige los dos sistemas y el módulo que quieres comparar. En Monday, el módulo es el tablero. Después verás solo los campos de esos módulos y podrás pedir la revisión IA.</div>
                    <div class="d-flex flex-wrap align-items-center mb-3">
                        <button type="button" class="btn btn-sm btn-outline-secondary refresh-unification-catalogues"><i class="fas fa-cloud-download-alt mr-1"></i>Traer todos los catálogos remotos</button>
                        <small class="text-muted ml-2">Lee nombres, claves/IDs, tipos y módulos; no trae valores de clientes.</small>
                    </div>
                    <div id="catalogue-refresh-status" class="small text-muted mb-3">
                        HubSpot: {{ $catalog_status['hubspot']['field_count'] }} campos{{ $catalog_status['hubspot']['fetched_at'] ? ' · última actualización '.$catalog_status['hubspot']['fetched_at'] : ' · pendiente de actualizar' }}. Teamleader: {{ $catalog_status['teamleader']['field_count'] }} campos{{ $catalog_status['teamleader']['fetched_at'] ? ' · última actualización '.$catalog_status['teamleader']['fetched_at'] : ' · pendiente de actualizar' }}. Monday: {{ $catalog_status['monday']['field_count'] }} columnas{{ $catalog_status['monday']['fetched_at'] ? ' · última actualización '.$catalog_status['monday']['fetched_at'] : ' · pendiente de actualizar' }}.
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label>Primera plataforma</label>
                            <select id="setup-left-provider" class="form-control setup-provider"><option value="app">App</option><option value="hubspot">HubSpot</option><option value="teamleader">Teamleader</option><option value="monday">Monday</option></select>
                            <label class="mt-2">Módulo</label>
                            <select id="setup-left-module" class="form-control setup-module"></select>
                            <div id="setup-left-monday-board-wrap" class="d-none mt-2"><label>Tablero Monday</label><select id="setup-left-monday-board" class="form-control setup-monday-board"><option value="">Selecciona un tablero</option></select></div>
                        </div>
                        <div class="form-group col-md-6">
                            <label>Segunda plataforma</label>
                            <select id="setup-right-provider" class="form-control setup-provider"><option value="hubspot">HubSpot</option><option value="app">App</option><option value="teamleader">Teamleader</option><option value="monday">Monday</option></select>
                            <label class="mt-2">Módulo</label>
                            <select id="setup-right-module" class="form-control setup-module"></select>
                            <div id="setup-right-monday-board-wrap" class="d-none mt-2"><label>Tablero Monday</label><select id="setup-right-monday-board" class="form-control setup-monday-board"><option value="">Selecciona un tablero</option></select></div>
                        </div>
                    </div>
                    <div id="relation-setup-message" class="small text-muted">Selecciona dos plataformas y módulos comparables.</div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button><button type="button" class="btn btn-primary" id="continue-relation-setup" disabled>Ver campos asociables <i class="fas fa-arrow-right ml-1"></i></button></div>
            </div></div>
        </div>

        <div class="modal fade" id="newAuditRelationModal" tabindex="-1" role="dialog" aria-labelledby="newAuditRelationTitle" aria-hidden="true">
            <div class="modal-dialog modal-lg" role="document"><form method="POST" action="{{ route('unification-map.relations.store') }}" class="modal-content" id="audit-relation-form">@csrf
                <div class="modal-header"><h5 class="modal-title" id="newAuditRelationTitle">Paso 2 de 2 · Campos asociables</h5><button type="button" class="btn btn-sm btn-outline-secondary mr-3" id="change-relation-setup"><i class="fas fa-sliders-h mr-1"></i>Cambiar plataformas/módulos</button><button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button></div>
                <div class="modal-body">
                    <div class="alert alert-info small">Selecciona dos extremos de la misma entidad: Contacto/cliente ↔ Contacto, o Negocio ↔ Deal/Proyecto. Para Monday, escoge un tablero por cada extremo; puedes asociar columnas de tableros distintos. Esta conexión es de diseño: no copia datos, no crea campos y no activa ninguna sincronización.</div>
                    <div id="relation-configuration-summary" class="alert alert-light border small py-2">Primero configura las plataformas y módulos.</div>
                    <div class="mb-3">
                        <button type="button" id="ai-suggest-platform-pair" class="btn btn-sm btn-outline-info" @if(! $summary['ai_suggestions_available']) disabled title="Configura OPENROUTER_API_KEY para habilitarlo" @endif>
                            <i class="fas fa-magic mr-1"></i>IA: revisar este par
                        </button>
                        <small class="d-block text-muted mt-1">Con ambos campos seleccionados, analiza solo esa pareja; si no hay selección, revisa como máximo 40 coincidencias del catálogo disponible. No envía datos de clientes ni guarda una relación.</small>
                        <div id="ai-platform-suggestions" class="alert alert-info small mt-2 mb-0 d-none" role="status"></div>
                        <div class="border rounded bg-light p-2 mt-2" id="ai-batch-builder">
                            <div class="d-flex flex-wrap align-items-center">
                                <strong class="mr-2">Lote de asociaciones</strong>
                                <span class="badge badge-secondary mr-2" id="ai-batch-count">0 parejas</span>
                                <button type="button" class="btn btn-xs btn-outline-secondary mr-1" id="add-ai-batch-pair">Añadir esta pareja</button>
                                <button type="button" class="btn btn-xs btn-outline-info mr-1" id="ai-suggest-batch" disabled>IA: revisar lote</button>
                                <button type="button" class="btn btn-xs btn-outline-danger" id="clear-ai-batch" disabled>Vaciar lote</button>
                            </div>
                            <small class="d-block text-muted mt-1">Añade las parejas que quieras revisar. El lote admite hasta {{ $summary['ai_batch_candidate_limit'] }} parejas y la IA las procesa internamente por grupos de 40; no se limita la revisión a la primera llamada.</small>
                            <div id="ai-batch-pairs" class="small mt-2 text-muted">Aún no hay parejas añadidas.</div>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label>Primera plataforma</label>
                            <select id="relation-left-provider" name="left_provider" class="form-control relation-provider"><option value="app">App</option><option value="hubspot">HubSpot</option><option value="teamleader">Teamleader</option><option value="monday">Monday</option></select>
                            <div id="relation-left-monday-board-wrap" class="d-none mt-2"><label>Tablero Monday</label><select id="relation-left-monday-board" class="form-control relation-monday-board"><option value="">Selecciona un tablero</option></select></div>
                            <label class="mt-2">Campo</label>
                            <input type="search" id="relation-left-field-search" class="form-control form-control-sm mb-1 relation-field-search" data-side="left" placeholder="Filtrar por nombre o clave">
                            <select id="relation-left-field-picker" class="form-control relation-field-picker" data-side="left"></select>
                            <button type="button" id="relation-left-field-more" class="btn btn-xs btn-outline-secondary mt-1 d-none">Mostrar más</button>
                            <input type="hidden" name="left_entity_type" id="relation-left-entity-type">
                            <input type="hidden" name="left_scope_key" id="relation-left-scope-key">
                            <input type="hidden" name="left_field_key" id="relation-left-field-key">
                            <input type="hidden" name="left_field_label" id="relation-left-field-label">
                        </div>
                        <div class="form-group col-md-6">
                            <label>Segunda plataforma</label>
                            <select id="relation-right-provider" name="right_provider" class="form-control relation-provider"><option value="hubspot">HubSpot</option><option value="app">App</option><option value="teamleader">Teamleader</option><option value="monday">Monday</option></select>
                            <div id="relation-right-monday-board-wrap" class="d-none mt-2"><label>Tablero Monday</label><select id="relation-right-monday-board" class="form-control relation-monday-board"><option value="">Selecciona un tablero</option></select></div>
                            <label class="mt-2">Campo</label>
                            <input type="search" id="relation-right-field-search" class="form-control form-control-sm mb-1 relation-field-search" data-side="right" placeholder="Filtrar por nombre o clave">
                            <select id="relation-right-field-picker" class="form-control relation-field-picker" data-side="right"></select>
                            <button type="button" id="relation-right-field-more" class="btn btn-xs btn-outline-secondary mt-1 d-none">Mostrar más</button>
                            <input type="hidden" name="right_entity_type" id="relation-right-entity-type">
                            <input type="hidden" name="right_scope_key" id="relation-right-scope-key">
                            <input type="hidden" name="right_field_key" id="relation-right-field-key">
                            <input type="hidden" name="right_field_label" id="relation-right-field-label">
                        </div>
                    </div>
                    <div class="form-group mb-0"><label>Notas de auditoría</label><textarea name="notes" rows="3" class="form-control" placeholder="Por qué se relacionan, dudas, responsable y reglas de conflicto…"></textarea></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button><button class="btn btn-primary">Guardar relación para auditoría</button></div>
            </form></div>
        </div>
    @endif
@stop

@section('css')
<style>
    .audit-notice { line-height: 1.55; }
    .relationship-canvas { min-height: 270px; position: relative; background: #f8fafc; border: 1px solid #e5e7eb; border-radius: .4rem; overflow: hidden; }
    .relationship-lines { position: absolute; inset: 0; width: 100%; height: 100%; pointer-events: none; }
    .relationship-lines line { stroke: #9ca3af; stroke-width: 3; stroke-dasharray: 7 5; opacity: .4; }
    .relationship-lines line.connected { stroke: #2563eb; opacity: .95; stroke-dasharray: none; }
    .relationship-lines line.suggested { stroke: #d97706; opacity: .9; }
    .entity-card { position: absolute; width: 25%; max-width: 250px; min-height: 94px; padding: .65rem .8rem; background: #fff; border: 1px solid #d1d5db; border-radius: .4rem; box-shadow: 0 2px 6px rgba(0,0,0,.06); z-index: 1; }
    .entity-title { font-size: .8rem; font-weight: 700; text-transform: uppercase; color: #4b5563; margin-bottom: .35rem; }
    .entity-content { font-size: .82rem; overflow-wrap: anywhere; }
    .entity-content code { font-size: .76rem; white-space: normal; }
    .entity-hubspot { top: 18px; left: 4%; border-top: 3px solid #ff7a59; }
    .entity-teamleader { top: 18px; right: 4%; border-top: 3px solid #6c63ff; }
    .entity-app { top: 90px; left: 37.5%; border-top: 3px solid #2563eb; }
    .entity-monday { bottom: 12px; left: 37.5%; border-top: 3px solid #f4b400; }
    #map-table td { vertical-align: middle; max-width: 270px; }
    #map-table code { font-size: .78rem; white-space: normal; overflow-wrap: anywhere; }
    @media (max-width: 767px) { .relationship-canvas { min-height: 460px; } .entity-card { width: 43%; } .entity-hubspot { left: 4%; } .entity-teamleader { right: 4%; } .entity-app { top: 168px; left: 28.5%; } .entity-monday { bottom: 14px; left: 28.5%; } }
</style>
@stop

@section('js')
<script>
    (function () {
        const rows = {{ \Illuminate\Support\Js::from($map_rows) }};
        const aiAvailable = {{ $summary['ai_suggestions_available'] ? 'true' : 'false' }};
        const suggestUrl = @json(route('unification-map.suggest'));
        const bulkStoreUrl = @json(route('unification-map.relations.bulk'));
        const fieldsUrl = @json(route('unification-map.fields'));
        const catalogueRefreshUrl = @json(route('unification-map.catalogues.refresh'));
        const catalogueStatusUrl = @json(route('unification-map.catalogues.status'));
        const mondayBoardsUrl = @json(route('unification-map.monday.boards'));
        const mondayFieldsUrl = @json(route('unification-map.monday.fields'));
        const automaticRelationsUrl = @json(route('unification-map.relations.automatic'));
        const aiBatchCandidateLimit = Number(@json($summary['ai_batch_candidate_limit']));
        const csrfToken = @json(csrf_token());
        const relationStorageReady = {{ $summary['relation_storage_ready'] ? 'true' : 'false' }};
        const derivedRelations = {{ \Illuminate\Support\Js::from($derived_relations) }};
        const entityLabel = (entityType) => ({
            client: 'Contacto / cliente', contact: 'Contacto',
            business: 'Negocio', deal: 'Deal', project: 'Proyecto',
            item: 'Item por clasificar',
        }[entityType] || entityType || 'Sin clasificar');
        const text = (field) => field ? `<strong>${escapeHtml(field.label || field.key || '—')}</strong><br><code>${escapeHtml(field.key || '')}</code><br><small class="text-muted">${escapeHtml(entityLabel(field.entity_type))}</small>${field.scope_key ? `<br><small>Ámbito: ${escapeHtml(field.scope_key)}</small>` : ''}` : '—';
        const many = (fields, empty) => fields && fields.length ? fields.map(text).join('<hr class="my-1">') : `<span class="text-muted">${empty}</span>`;
        const escapeHtml = (value) => String(value ?? '').replace(/[&<>'"]/g, (character) => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;'}[character]));
        let aiBatchPairs = [];
        let mondayBoards = null;
        let mondayBoardsRequest = null;
        let catalogueStatusTimer = null;
        let automaticRelations = [];
        const automaticState = {page: 1, hasMore: false, total: 0};
        const relationFieldState = {
            left: {fields: [], page: 0, hasMore: false, selectedIdentity: null, requestId: 0, module: null},
            right: {fields: [], page: 0, hasMore: false, selectedIdentity: null, requestId: 0, module: null},
        };

        window.selectMap = function (index) {
            const row = rows[index];
            if (!row) return;
            document.getElementById('node-app').innerHTML = text(row.app);
            document.getElementById('node-hubspot').innerHTML = many(row.hubspot, 'No asociado');
            document.getElementById('node-teamleader').innerHTML = many(row.teamleader, 'No asociado');
            document.getElementById('node-monday').innerHTML = many(row.monday_matches, 'Sin coincidencia automática');
            const hasHubspot = row.hubspot && row.hubspot.length;
            const hasTeamleader = row.teamleader && row.teamleader.length;
            const hasMonday = row.monday_matches && row.monday_matches.length;
            const status = row.match_method === 'legacy_catalog'
                ? 'Catálogo legado: revisar y decidir'
                : (row.match_method === 'commercial_catalogue'
                    ? 'Catálogo local de negocios/proyectos: revisar'
                    : (row.match_method === 'unmatched' ? 'Campo pendiente de asociar' : 'Diseño por revisar'));
            document.getElementById('selected-map-status').textContent = status;
            document.getElementById('selected-map-hint').textContent = 'La visualización muestra relaciones conocidas o sugeridas. Ninguna línea representa una sincronización activa.';
            setLine('line-hubspot-app', hasHubspot, row.match_method === 'legacy_catalog' ? 'connected' : 'suggested');
            setLine('line-teamleader-app', hasTeamleader, row.match_method === 'legacy_catalog' ? 'connected' : 'suggested');
            setLine('line-monday-app', hasMonday, 'suggested');
            document.querySelectorAll('#map-table tbody tr').forEach((element) => element.classList.toggle('table-primary', Number(element.dataset.mapRow) === index));
        };

        function setLine(id, present, className) {
            const line = document.getElementById(id);
            line.classList.remove('connected', 'suggested');
            if (present) line.classList.add(className);
        }

        document.querySelectorAll('.select-map').forEach((button) => button.addEventListener('click', () => window.selectMap(Number(button.dataset.index))));

        const fieldIdentity = (field) => `${field.entity_type || ''}|${field.scope_key || '*'}|${field.key || ''}`;
        const providersCanBeCompared = (left, right) => left && right && (left !== right || left === 'monday');

        async function fetchJson(url) {
            const response = await fetch(url, {headers: {'Accept': 'application/json'}});
            const payload = await response.json().catch(() => ({}));
            if (!response.ok) throw new Error(payload.message || `No se pudo cargar el catálogo (HTTP ${response.status}).`);
            return payload;
        }

        async function refreshExternalCatalogues() {
            const buttons = [...document.querySelectorAll('.refresh-unification-catalogues')];
            const status = document.getElementById('catalogue-refresh-status');
            buttons.forEach((button) => {
                button.disabled = true;
                button.dataset.idleHtml = button.innerHTML;
                button.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Actualizando…';
            });
            if (status) {
                status.className = 'alert alert-info small mb-3';
                status.textContent = 'Leyendo únicamente metadatos de HubSpot, Teamleader y todos los tableros de Monday. Puede tardar si Monday tiene muchos tableros.';
            }

            try {
                const response = await fetch(catalogueRefreshUrl, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken},
                    body: JSON.stringify({providers: ['hubspot', 'teamleader', 'monday']}),
                });
                const payload = await response.json().catch(() => ({}));
                if (!response.ok) throw new Error(payload.message || `No se pudieron actualizar los catálogos (HTTP ${response.status}).`);

                const report = Object.entries(payload.providers || {}).map(([provider, result]) => {
                    const title = provider === 'hubspot' ? 'HubSpot' : provider === 'teamleader' ? 'Teamleader' : 'Monday';
                    if (result.in_progress) {
                        const progress = result.board_count === null || result.board_count === undefined
                            ? 'preparando tableros'
                            : `${result.processed_boards || 0}/${result.board_count} tableros`;
                        return `${title}: actualización en curso (${progress})`;
                    }
                    if (result.ok) {
                        const warning = result.unavailable_boards?.length
                            ? ` · ${result.unavailable_boards.length} tablero(s) de Monday no disponible(s)`
                            : '';
                        return `${title}: ${result.field_count || 0} campo(s)${warning}`;
                    }
                    return `${title}: ERROR · ${result.message || 'sin detalle'}${result.cached ? ` (se conserva el último catálogo de ${result.field_count || 0} campos)` : ''}`;
                });
                const failed = Object.values(payload.providers || {}).some((result) => !result.ok);
                if (status) {
                    status.className = `alert alert-${failed ? 'warning' : 'success'} small mb-3`;
                    status.textContent = `${report.join(' · ')}. Los selectores ya usan el catálogo actualizado; recarga el mapa si quieres actualizar los contadores superiores.`;
                }

                if (payload.providers?.monday?.in_progress) {
                    pollMondayCatalogueStatus();
                }

                // The server cache was refreshed, so discard any browser copy
                // of boards and reload visible field lists from the new metadata.
                mondayBoards = null;
                await Promise.all(['left', 'right'].map((side) => {
                    const provider = document.getElementById(`relation-${side}-provider`);
                    return provider?.value ? loadRelationFields(side) : Promise.resolve();
                }));
            } catch (error) {
                if (status) {
                    status.className = 'alert alert-danger small mb-3';
                    status.textContent = error.message || 'No se pudieron actualizar los catálogos remotos.';
                } else {
                    window.alert(error.message || 'No se pudieron actualizar los catálogos remotos.');
                }
            } finally {
                buttons.forEach((button) => {
                    button.disabled = false;
                    button.innerHTML = button.dataset.idleHtml || '<i class="fas fa-cloud-download-alt mr-1"></i>Actualizar catálogos';
                });
            }
        }

        async function pollMondayCatalogueStatus() {
            if (catalogueStatusTimer) window.clearTimeout(catalogueStatusTimer);
            const status = document.getElementById('catalogue-refresh-status');
            try {
                const payload = await fetchJson(catalogueStatusUrl);
                const monday = payload.providers?.monday || {};
                const progress = monday.refresh || {};
                const total = progress.total;
                const processed = progress.processed || 0;
                if (status && progress.status) {
                    if (progress.status === 'completed') {
                        status.className = 'alert alert-success small mb-3';
                        status.textContent = `Monday: catálogo completo actualizado (${monday.field_count || 0} columnas en ${total || 0} tableros; ${progress.unavailable_boards?.length || 0} no disponibles). Recarga el mapa para actualizar los contadores superiores.`;
                    } else if (progress.status === 'failed') {
                        status.className = 'alert alert-danger small mb-3';
                        status.textContent = `Monday: la actualización del catálogo falló. ${progress.message || 'Sin detalle.'}`;
                    } else {
                        status.className = 'alert alert-info small mb-3';
                        status.textContent = total === null || total === undefined
                            ? 'Monday: preparando la lista completa de tableros…'
                            : `Monday: ${processed}/${total} tableros procesados; ${monday.field_count || 0} columnas de metadatos disponibles hasta ahora.`;
                    }
                }
                if (['queued', 'running'].includes(progress.status)) {
                    catalogueStatusTimer = window.setTimeout(pollMondayCatalogueStatus, 4000);
                }
            } catch (error) {
                if (status) {
                    status.className = 'alert alert-warning small mb-3';
                    status.textContent = `No se pudo consultar el progreso de Monday: ${error.message || 'sin detalle.'}`;
                }
            }
        }

        async function ensureMondayBoards() {
            if (mondayBoards) return mondayBoards;
            if (!mondayBoardsRequest) {
                mondayBoardsRequest = fetchJson(mondayBoardsUrl)
                    .then((payload) => {
                        mondayBoards = payload.data || [];
                        return mondayBoards;
                    })
                    .finally(() => { mondayBoardsRequest = null; });
            }

            return mondayBoardsRequest;
        }

        function renderMondayBoardPicker(side) {
            const provider = document.getElementById(`relation-${side}-provider`);
            const wrap = document.getElementById(`relation-${side}-monday-board-wrap`);
            const picker = document.getElementById(`relation-${side}-monday-board`);
            if (!provider || !wrap || !picker) return;

            const isMonday = provider.value === 'monday';
            wrap.classList.toggle('d-none', !isMonday);
            if (!isMonday || !mondayBoards) return;

            const selected = picker.value;
            picker.innerHTML = '<option value="">Selecciona un tablero</option>';
            mondayBoards.forEach((board) => {
                const option = document.createElement('option');
                option.value = board.id;
                option.textContent = board.name;
                picker.appendChild(option);
            });
            picker.value = selected;
        }

        const providerModules = {
            app: [{value: 'client', label: 'Contactos / clientes'}, {value: 'business', label: 'Negocios'}],
            hubspot: [{value: 'contact', label: 'Contacts'}, {value: 'deal', label: 'Deals'}],
            teamleader: [{value: 'contact', label: 'Contactos / empresas'}, {value: 'deal', label: 'Deals'}, {value: 'project', label: 'Proyectos'}],
            monday: [{value: 'item', label: 'Tablero Monday'}],
        };

        const moduleFamily = (module) => ({
            client: 'contact', contact: 'contact',
            business: 'commercial', deal: 'commercial', project: 'commercial',
            item: 'workflow',
        }[module] || module || '');

        function selectedSetup(side) {
            return {
                provider: document.getElementById(`setup-${side}-provider`)?.value || '',
                module: document.getElementById(`setup-${side}-module`)?.value || '',
                boardId: document.getElementById(`setup-${side}-monday-board`)?.value || '',
            };
        }

        function moduleLabel(provider, module) {
            return (providerModules[provider] || []).find((item) => item.value === module)?.label || module || 'Sin módulo';
        }

        async function renderSetupModules(side) {
            const provider = document.getElementById(`setup-${side}-provider`);
            const module = document.getElementById(`setup-${side}-module`);
            const boardWrap = document.getElementById(`setup-${side}-monday-board-wrap`);
            const board = document.getElementById(`setup-${side}-monday-board`);
            if (!provider || !module || !boardWrap || !board) return;

            const previous = module.value;
            module.innerHTML = (providerModules[provider.value] || []).map((item) => `<option value="${escapeHtml(item.value)}">${escapeHtml(item.label)}</option>`).join('');
            module.value = [...module.options].some((item) => item.value === previous) ? previous : (module.options[0]?.value || '');
            const isMonday = provider.value === 'monday';
            boardWrap.classList.toggle('d-none', !isMonday);
            if (isMonday) {
                try {
                    await ensureMondayBoards();
                    const previousBoard = board.value;
                    board.innerHTML = '<option value="">Selecciona un tablero</option>';
                    mondayBoards.forEach((item) => {
                        const option = document.createElement('option');
                        option.value = item.id;
                        option.textContent = item.name;
                        board.appendChild(option);
                    });
                    board.value = previousBoard;
                } catch (error) {
                    board.innerHTML = '<option value="">No se pudieron cargar los tableros</option>';
                }
            }
            updateRelationSetup();
        }

        function setupCanContinue(left, right) {
            if (!providersCanBeCompared(left.provider, right.provider) || !left.module || !right.module) return false;
            const leftFamily = moduleFamily(left.module);
            const rightFamily = moduleFamily(right.module);
            if (leftFamily !== rightFamily && leftFamily !== 'workflow' && rightFamily !== 'workflow') return false;
            if ((left.provider === 'monday' && !left.boardId) || (right.provider === 'monday' && !right.boardId)) return false;
            if (left.provider === 'monday' && right.provider === 'monday' && left.boardId === right.boardId) return false;

            return true;
        }

        function updateRelationSetup() {
            const left = selectedSetup('left');
            const right = selectedSetup('right');
            const button = document.getElementById('continue-relation-setup');
            const message = document.getElementById('relation-setup-message');
            const ready = setupCanContinue(left, right);
            if (button) button.disabled = !ready;
            if (!message) return;
            if (ready) {
                message.className = 'small text-success';
                message.textContent = 'Configuración válida. Continúa para elegir los campos que la IA revisará.';
            } else if (left.provider === right.provider && left.provider !== 'monday') {
                message.className = 'small text-warning';
                message.textContent = 'Selecciona plataformas distintas. La única excepción permitida es Monday entre dos tableros distintos.';
            } else if (moduleFamily(left.module) !== moduleFamily(right.module)
                && moduleFamily(left.module) !== 'workflow' && moduleFamily(right.module) !== 'workflow') {
                message.className = 'small text-warning';
                message.textContent = 'No se pueden mezclar contactos/clientes con negocios/deals/proyectos.';
            } else if ((left.provider === 'monday' && !left.boardId) || (right.provider === 'monday' && !right.boardId)) {
                message.className = 'small text-warning';
                message.textContent = 'Selecciona el tablero de Monday para cada extremo.';
            } else {
                message.className = 'small text-muted';
                message.textContent = 'Selecciona dos plataformas y módulos comparables.';
            }
        }

        async function applyRelationSetup() {
            const left = selectedSetup('left');
            const right = selectedSetup('right');
            if (!setupCanContinue(left, right)) return;

            const setup = {left, right};
            for (const side of ['left', 'right']) {
                const endpoint = setup[side];
                document.getElementById(`relation-${side}-provider`).value = endpoint.provider;
                relationFieldState[side].module = endpoint.provider === 'monday' ? null : endpoint.module;
                if (endpoint.provider === 'monday') {
                    await ensureMondayBoards();
                    renderMondayBoardPicker(side);
                    document.getElementById(`relation-${side}-monday-board`).value = endpoint.boardId;
                }
            }
            aiBatchPairs = [];
            clearPlatformAiSuggestions();
            const summary = document.getElementById('relation-configuration-summary');
            if (summary) {
                const describe = (endpoint) => `${endpoint.provider === 'app' ? 'App' : endpoint.provider === 'hubspot' ? 'HubSpot' : endpoint.provider === 'teamleader' ? 'Teamleader' : 'Monday'} · ${endpoint.provider === 'monday' ? (mondayBoards || []).find((item) => String(item.id) === String(endpoint.boardId))?.name || `Tablero ${endpoint.boardId}` : moduleLabel(endpoint.provider, endpoint.module)}`;
                summary.innerHTML = `<strong>Configuración:</strong> ${escapeHtml(describe(left))} <i class="fas fa-arrows-alt-h mx-1 text-muted"></i> ${escapeHtml(describe(right))}. Estos son los únicos módulos cuyos campos se mostrarán.`;
            }
            if (window.jQuery) {
                window.jQuery('#relationSetupModal').modal('hide');
                window.jQuery('#newAuditRelationModal').modal('show');
            }
            await Promise.all(['left', 'right'].map((side) => loadRelationFields(side)));
            updatePairAiButton();
            renderAiBatch();
        }

        function renderRelationPicker(side, message = null) {
            const picker = document.getElementById(`relation-${side}-field-picker`);
            const more = document.getElementById(`relation-${side}-field-more`);
            const state = relationFieldState[side];
            if (!picker || !more) return;

            picker.innerHTML = '';
            if (message || !state.fields.length) {
                const option = document.createElement('option');
                option.value = '';
                option.textContent = message || 'No se encontraron campos';
                picker.appendChild(option);
                picker.disabled = true;
                more.classList.add('d-none');
                syncRelationEndpoint(side);
                return;
            }

            state.fields.forEach((field) => {
                const option = document.createElement('option');
                option.value = fieldIdentity(field);
                option.textContent = `${field.label || field.key} · ${field.key} · ${entityLabel(field.entity_type)}${field.scope_key && field.scope_key !== '*' ? ` · tablero ${field.scope_key}` : ''}`;
                picker.appendChild(option);
            });
            picker.disabled = false;
            const selected = state.fields.some((field) => fieldIdentity(field) === state.selectedIdentity)
                ? state.selectedIdentity
                : fieldIdentity(state.fields[0]);
            picker.value = selected;
            state.selectedIdentity = selected;
            more.classList.toggle('d-none', !state.hasMore);
            more.disabled = !state.hasMore;
            syncRelationEndpoint(side);
        }

        async function loadRelationFields(side, {append = false} = {}) {
            const provider = document.getElementById(`relation-${side}-provider`);
            const search = document.getElementById(`relation-${side}-field-search`);
            const mondayBoard = document.getElementById(`relation-${side}-monday-board`);
            const state = relationFieldState[side];
            if (!provider) return;

            const requestId = ++state.requestId;
            if (!append) {
                state.fields = [];
                state.page = 0;
                state.hasMore = false;
                state.selectedIdentity = null;
            }

            if (provider.value === 'monday') {
                try {
                    await ensureMondayBoards();
                    renderMondayBoardPicker(side);
                } catch (error) {
                    renderRelationPicker(side, error.message || 'No se pudo cargar los tableros de Monday');
                    return;
                }
                if (!mondayBoard?.value) {
                    renderRelationPicker(side, 'Selecciona un tablero de Monday');
                    return;
                }
            } else {
                document.getElementById(`relation-${side}-monday-board-wrap`)?.classList.add('d-none');
            }

            renderRelationPicker(side, 'Cargando campos...');
            const params = new URLSearchParams({
                search: search?.value || '',
                page: String(append ? state.page + 1 : 1),
                per_page: '50',
            });
            if (provider.value === 'monday') params.set('board_id', mondayBoard.value);
            else {
                params.set('provider', provider.value);
                if (state.module) params.set('entity_type', state.module);
            }

            try {
                const payload = await fetchJson(`${provider.value === 'monday' ? mondayFieldsUrl : fieldsUrl}?${params.toString()}`);
                if (requestId !== state.requestId) return;
                const fields = payload.data || [];
                state.fields = append
                    ? [...state.fields, ...fields.filter((field) => !state.fields.some((current) => fieldIdentity(current) === fieldIdentity(field)))]
                    : fields;
                state.page = Number(payload.meta?.page || 1);
                state.hasMore = Boolean(payload.meta?.has_more);
                renderRelationPicker(side);
            } catch (error) {
                if (requestId === state.requestId) renderRelationPicker(side, error.message || 'No se pudo cargar los campos');
            }
        }

        function syncRelationEndpoint(side) {
            const picker = document.getElementById(`relation-${side}-field-picker`);
            const state = relationFieldState[side];
            if (!picker) return;
            state.selectedIdentity = picker.value || null;
            const field = state.fields.find((item) => fieldIdentity(item) === state.selectedIdentity);
            document.getElementById(`relation-${side}-entity-type`).value = field?.entity_type || '';
            document.getElementById(`relation-${side}-scope-key`).value = field?.scope_key || '';
            document.getElementById(`relation-${side}-field-key`).value = field?.key || '';
            document.getElementById(`relation-${side}-field-label`).value = field?.label || '';
            updatePairAiButton();
        }

        function selectedPlatformPair() {
            return {
                left: document.getElementById('relation-left-provider')?.value || '',
                right: document.getElementById('relation-right-provider')?.value || '',
            };
        }

        function selectedPairPayload() {
            const {left, right} = selectedPlatformPair();

            return {
                left_provider: left,
                right_provider: right,
                left_entity_type: document.getElementById('relation-left-entity-type')?.value || '',
                left_scope_key: document.getElementById('relation-left-scope-key')?.value || '',
                left_field_key: document.getElementById('relation-left-field-key')?.value || '',
                left_field_label: document.getElementById('relation-left-field-label')?.value || '',
                right_entity_type: document.getElementById('relation-right-entity-type')?.value || '',
                right_scope_key: document.getElementById('relation-right-scope-key')?.value || '',
                right_field_key: document.getElementById('relation-right-field-key')?.value || '',
                right_field_label: document.getElementById('relation-right-field-label')?.value || '',
            };
        }

        function selectedBatchPair() {
            const selection = selectedPairPayload();
            if (!providersCanBeCompared(selection.left_provider, selection.right_provider)
                || !selection.left_field_key || !selection.right_field_key) {
                return null;
            }

            return {
                left: {
                    entity_type: selection.left_entity_type,
                    scope_key: selection.left_scope_key || '*',
                    field_key: selection.left_field_key,
                    field_label: document.getElementById('relation-left-field-label')?.value || selection.left_field_key,
                },
                right: {
                    entity_type: selection.right_entity_type,
                    scope_key: selection.right_scope_key || '*',
                    field_key: selection.right_field_key,
                    field_label: document.getElementById('relation-right-field-label')?.value || selection.right_field_key,
                },
            };
        }

        function batchPairIdentity(pair) {
            const {left, right} = selectedPlatformPair();
            return `${left}|${pair.left.entity_type}|${pair.left.scope_key}|${pair.left.field_key}↔${right}|${pair.right.entity_type}|${pair.right.scope_key}|${pair.right.field_key}`;
        }

        function batchFieldLabel(provider, endpoint) {
            return endpoint.field_label || endpoint.field_key;
        }

        function renderAiBatch() {
            const target = document.getElementById('ai-batch-pairs');
            const count = document.getElementById('ai-batch-count');
            const review = document.getElementById('ai-suggest-batch');
            const clear = document.getElementById('clear-ai-batch');
            const {left, right} = selectedPlatformPair();
            if (count) count.textContent = `${aiBatchPairs.length} pareja(s)`;
            if (review) review.disabled = !aiAvailable || !aiBatchPairs.length || !providersCanBeCompared(left, right);
            if (clear) clear.disabled = !aiBatchPairs.length;
            if (!target) return;
            if (!aiBatchPairs.length) {
                target.className = 'small mt-2 text-muted';
                target.textContent = 'Aún no hay parejas añadidas.';
                return;
            }

            target.className = 'small mt-2';
            target.innerHTML = aiBatchPairs.map((pair, index) => `<div class="d-flex align-items-start mb-1"><span class="flex-grow-1"><strong>${escapeHtml(batchFieldLabel(left, pair.left))}</strong> <i class="fas fa-arrows-alt-h text-muted mx-1"></i> <strong>${escapeHtml(batchFieldLabel(right, pair.right))}</strong></span><button type="button" class="btn btn-xs btn-outline-danger remove-ai-batch-pair" data-index="${index}" title="Quitar del lote">×</button></div>`).join('')
                + (aiBatchPairs.length >= aiBatchCandidateLimit ? `<div class="text-warning mt-1">Se alcanzó el máximo configurado de ${aiBatchCandidateLimit} parejas.</div>` : '');
            target.querySelectorAll('.remove-ai-batch-pair').forEach((button) => button.addEventListener('click', () => {
                aiBatchPairs.splice(Number(button.dataset.index), 1);
                renderAiBatch();
            }));
        }

        function addSelectedPairToAiBatch() {
            const pair = selectedBatchPair();
            if (!pair) {
                window.alert('Selecciona un campo disponible en ambas plataformas antes de añadirlo al lote.');
                return;
            }
            if (aiBatchPairs.length >= aiBatchCandidateLimit) {
                window.alert(`El lote ya alcanzó el máximo configurado de ${aiBatchCandidateLimit} parejas.`);
                return;
            }
            const identity = batchPairIdentity(pair);
            if (!aiBatchPairs.some((item) => batchPairIdentity(item) === identity)) {
                aiBatchPairs.push(pair);
            }
            clearPlatformAiSuggestions();
            renderAiBatch();
        }

        function updatePairAiButton() {
            const button = document.getElementById('ai-suggest-platform-pair');
            if (!button) return;
            button.disabled = !aiAvailable;
            button.title = aiAvailable
                ? 'Selecciona los campos y pide la revisión IA.'
                : 'Configura OPENROUTER_API_KEY para habilitarlo.';
        }

        function clearPlatformAiSuggestions() {
            const target = document.getElementById('ai-platform-suggestions');
            if (target) target.classList.add('d-none');
        }

        function serialiseAiRelation(item) {
            return {
                left_provider: item.left.provider,
                left_entity_type: item.left.entity_type,
                left_scope_key: item.left.scope_key,
                left_field_key: item.left.key,
                left_field_label: item.left.label,
                right_provider: item.right.provider,
                right_entity_type: item.right.entity_type,
                right_scope_key: item.right.scope_key,
                right_field_key: item.right.key,
                right_field_label: item.right.label,
                confidence: item.confidence,
                reason: item.reason || '',
            };
        }

        async function storeSelectedAiSuggestions(items, status = 'proposed') {
            const target = document.getElementById('ai-platform-suggestions');
            const selected = [...target.querySelectorAll('.ai-relation-selection:checked')]
                .map((checkbox) => items[Number(checkbox.dataset.aiIndex)])
                .filter(Boolean);
            if (!selected.length) {
                window.alert('Selecciona al menos una sugerencia para guardarla como propuesta de auditoría.');
                return;
            }
            const action = status === 'approved' ? 'aprobar como diseño' : 'guardar para auditoría';
            if (!window.confirm(`¿${action.charAt(0).toUpperCase() + action.slice(1)} ${selected.length} relación(es)? No se activará ningún mapeo ni sincronización.`)) return;

            const buttons = [...target.querySelectorAll('.store-ai-suggestions, .approve-ai-suggestions')];
            buttons.forEach((button) => button.disabled = true);
            try {
                const response = await fetch(bulkStoreUrl, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken},
                    body: JSON.stringify({relations: selected.map(serialiseAiRelation), status}),
                });
                const payload = await response.json().catch(() => ({}));
                if (!response.ok) throw new Error(payload.message || `No se pudieron guardar las propuestas (HTTP ${response.status}).`);
                target.className = 'alert alert-success small mt-2 mb-0';
                target.textContent = `${payload.message || 'Relaciones guardadas para auditoría.'} ${status === 'approved' ? 'Quedaron aprobadas como diseño y pueden servir de puente en el mapa.' : 'Quedaron pendientes de revisión.'} Recarga el mapa para verlas.`;
            } catch (error) {
                buttons.forEach((button) => button.disabled = false);
                target.className = 'alert alert-warning small mt-2 mb-0';
                target.textContent = error.message || 'No se pudieron guardar las propuestas de auditoría.';
            }
        }

        function renderAiSuggestions(suggestion, contextLabel) {
            const target = document.getElementById('ai-platform-suggestions');
            const items = suggestion.suggestions || [];
            if (!items.length) {
                target.className = 'alert alert-secondary small mt-2 mb-0';
                target.textContent = suggestion.used_ai
                    ? `La IA no recomendó convertir ninguna pareja de ${contextLabel} en propuesta. No se guardó ni activó nada.`
                    : `No hay parejas locales suficientes en ${contextLabel}; OpenRouter no fue consultado y no se consumieron créditos.`;
                return;
            }

            const batchInfo = suggestion.candidate_count
                ? ` · ${suggestion.candidate_count} pareja(s) evaluada(s) en ${suggestion.batch_count} llamada(s)`
                : '';
            target.className = 'alert alert-info small mt-2 mb-0';
            target.innerHTML = `<strong>IA · ${items.length} sugerencia(s) para revisar${batchInfo}</strong><ul class="mb-1 pl-3 mt-2">${items.map((item, index) => `<li class="mb-2"><label class="mb-0"><input type="checkbox" class="ai-relation-selection mr-1" data-ai-index="${index}" checked><strong>${escapeHtml(item.left.label)} ↔ ${escapeHtml(item.right.label)}</strong></label> <span class="badge badge-info">${escapeHtml(item.confidence)}%</span><br>${escapeHtml(item.reason || 'Sin explicación adicional.')}<br><button type="button" class="btn btn-xs btn-outline-primary mt-1 use-ai-relation" data-ai-index="${index}">Editar individualmente</button></li>`).join('')}</ul><button type="button" class="btn btn-sm btn-outline-secondary store-ai-suggestions">Guardar para revisión</button><button type="button" class="btn btn-sm btn-outline-success ml-1 approve-ai-suggestions">Aprobar como diseño</button><br><small>Aprobar solo registra el diseño auditado y habilita puentes en este mapa; no crea mapeos ni sincronizaciones.</small>`;
            target.querySelectorAll('.use-ai-relation').forEach((item) => item.addEventListener('click', () => {
                openSuggestedRelation(items[Number(item.dataset.aiIndex)]);
            }));
            target.querySelector('.store-ai-suggestions')?.addEventListener('click', () => storeSelectedAiSuggestions(items));
            target.querySelector('.approve-ai-suggestions')?.addEventListener('click', () => storeSelectedAiSuggestions(items, 'approved'));
        }

        async function requestAiSuggestion(payload, button, busyText, contextLabel, idleHtml) {
            const {left, right} = selectedPlatformPair();
            const target = document.getElementById('ai-platform-suggestions');
            if (!aiAvailable) {
                target.className = 'alert alert-warning small mt-2 mb-0';
                target.textContent = 'OPENROUTER_API_KEY no está disponible en esta instancia.';
                return;
            }
            if (!providersCanBeCompared(left, right)) {
                target.className = 'alert alert-warning small mt-2 mb-0';
                target.textContent = 'Selecciona dos plataformas distintas; Monday solo puede compararse entre tableros distintos.';
                return;
            }
            if (!Array.isArray(payload.batch_pairs) && (!payload.left_field_key || !payload.right_field_key)) {
                target.className = 'alert alert-warning small mt-2 mb-0';
                target.textContent = 'Primero elige un campo en cada módulo. El botón permanece disponible para que puedas ver esta indicación.';
                return;
            }

            button.disabled = true;
            button.innerHTML = `<i class="fas fa-spinner fa-spin mr-1"></i>${busyText}`;
            target.className = 'alert alert-info small mt-2 mb-0';
            target.textContent = `Analizando ${contextLabel} entre ${left} ↔ ${right}; no se envían datos de clientes.`;

            try {
                const response = await fetch(suggestUrl, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken},
                    body: JSON.stringify(payload),
                });
                const result = await response.json().catch(() => ({}));
                if (!response.ok) throw new Error(result.message || `No se pudo generar la sugerencia (HTTP ${response.status}).`);
                renderAiSuggestions(result.suggestion || {}, contextLabel);
            } catch (error) {
                target.className = 'alert alert-warning small mt-2 mb-0';
                target.textContent = error.message || 'No se pudo generar la sugerencia.';
            } finally {
                updatePairAiButton();
                renderAiBatch();
                button.innerHTML = idleHtml;
            }
        }

        document.getElementById('ai-suggest-platform-pair')?.addEventListener('click', function () {
            requestAiSuggestion(selectedPairPayload(), this, 'Analizando este par', 'el par de campos seleccionado', '<i class="fas fa-magic mr-1"></i>IA: revisar este par');
        });
        document.getElementById('add-ai-batch-pair')?.addEventListener('click', addSelectedPairToAiBatch);
        document.getElementById('clear-ai-batch')?.addEventListener('click', () => {
            aiBatchPairs = [];
            clearPlatformAiSuggestions();
            renderAiBatch();
        });
        document.getElementById('ai-suggest-batch')?.addEventListener('click', function () {
            const {left, right} = selectedPlatformPair();
            if (!aiBatchPairs.length || !providersCanBeCompared(left, right)) return;
            requestAiSuggestion({left_provider: left, right_provider: right, batch_pairs: aiBatchPairs}, this, 'Analizando lote', `el lote de ${aiBatchPairs.length} pareja(s)`, '<i class="fas fa-magic mr-1"></i>IA: revisar lote');
        });
        document.querySelectorAll('.refresh-unification-catalogues').forEach((button) => {
            button.addEventListener('click', refreshExternalCatalogues);
        });

        ['left', 'right'].forEach((side) => {
            document.getElementById(`setup-${side}-provider`)?.addEventListener('change', () => renderSetupModules(side));
            document.getElementById(`setup-${side}-module`)?.addEventListener('change', updateRelationSetup);
            document.getElementById(`setup-${side}-monday-board`)?.addEventListener('change', updateRelationSetup);
            renderSetupModules(side);
        });
        document.getElementById('continue-relation-setup')?.addEventListener('click', applyRelationSetup);
        document.getElementById('change-relation-setup')?.addEventListener('click', () => {
            if (window.jQuery) {
                window.jQuery('#newAuditRelationModal').modal('hide');
                window.jQuery('#relationSetupModal').modal('show');
            }
        });

        const fieldSearchTimers = {};
        ['left', 'right'].forEach((side) => {
            document.getElementById(`relation-${side}-provider`)?.addEventListener('change', () => {
                aiBatchPairs = [];
                relationFieldState[side].module = null;
                clearPlatformAiSuggestions();
                updatePairAiButton();
                loadRelationFields(side);
                renderAiBatch();
            });
            document.getElementById(`relation-${side}-monday-board`)?.addEventListener('change', () => {
                aiBatchPairs = [];
                clearPlatformAiSuggestions();
                updatePairAiButton();
                loadRelationFields(side);
                renderAiBatch();
            });
            document.getElementById(`relation-${side}-field-picker`)?.addEventListener('change', () => {
                clearPlatformAiSuggestions();
                syncRelationEndpoint(side);
            });
            document.getElementById(`relation-${side}-field-search`)?.addEventListener('input', () => {
                window.clearTimeout(fieldSearchTimers[side]);
                fieldSearchTimers[side] = window.setTimeout(() => loadRelationFields(side), 250);
            });
            document.getElementById(`relation-${side}-field-more`)?.addEventListener('click', () => loadRelationFields(side, {append: true}));
            loadRelationFields(side);
        });
        renderAiBatch();
        document.getElementById('audit-relation-form')?.addEventListener('submit', function (event) {
            const left = document.getElementById('relation-left-field-key').value;
            const right = document.getElementById('relation-right-field-key').value;
            if (!left || !right) {
                event.preventDefault();
                window.alert('Selecciona un campo disponible en ambas plataformas.');
            }
        });
        document.querySelectorAll('.use-derived-relation').forEach((button) => button.addEventListener('click', () => {
            const relation = derivedRelations[Number(button.dataset.index)];
            openSuggestedRelation(relation);
        }));

        async function openSuggestedRelation(relation) {
            if (!relation) return;
            await Promise.all([
                applyDerivedEndpoint('left', relation.left),
                applyDerivedEndpoint('right', relation.right),
            ]);
            if (window.jQuery) window.jQuery('#newAuditRelationModal').modal('show');
        }

        async function applyDerivedEndpoint(side, endpoint) {
            const provider = document.getElementById(`relation-${side}-provider`);
            const picker = document.getElementById(`relation-${side}-field-picker`);
            if (!provider || !picker) return;
            provider.value = endpoint.provider;
            const search = document.getElementById(`relation-${side}-field-search`);
            if (search) search.value = endpoint.key;
            if (endpoint.provider === 'monday') {
                try {
                    await ensureMondayBoards();
                    renderMondayBoardPicker(side);
                    document.getElementById(`relation-${side}-monday-board`).value = endpoint.scope_key;
                } catch (error) {
                    return;
                }
            }
            await loadRelationFields(side);
            const state = relationFieldState[side];
            const selected = state.fields.find((field) => field.key === endpoint.key
                && (field.scope_key || '*') === (endpoint.scope_key || '*')
                && field.entity_type === endpoint.entity_type);
            if (selected) {
                state.selectedIdentity = fieldIdentity(selected);
                renderRelationPicker(side);
            }
            syncRelationEndpoint(side);
        }

        function automaticRow(relation, index) {
            const action = relationStorageReady
                ? `<button type="button" class="btn btn-xs btn-outline-primary use-automatic-relation" data-index="${index}">Convertir en propuesta</button>`
                : '<span class="text-muted small">Solo lectura</span>';
            const leftSource = relation.left_source ? `<br><small class="text-muted">${escapeHtml(relation.left_source)}</small>` : '';
            const rightSource = relation.right_source ? `<br><small class="text-muted">${escapeHtml(relation.right_source)}</small>` : '';
            const badge = Number(relation.confidence) === 100 ? 'success' : 'warning';

            return `<tr><td><strong>${escapeHtml(relation.left.provider)}</strong><br><small>${escapeHtml(relation.left.label)} · <code>${escapeHtml(relation.left.key)}</code></small><br><small class="text-muted">${escapeHtml(relation.left.entity_type)}</small>${leftSource}</td><td><strong>${escapeHtml(relation.right.provider)}</strong><br><small>${escapeHtml(relation.right.label)} · <code>${escapeHtml(relation.right.key)}</code></small><br><small class="text-muted">${escapeHtml(relation.right.entity_type)}</small>${rightSource}</td><td><span class="badge badge-${badge}">${escapeHtml(relation.confidence)}%</span><br><small>${escapeHtml(relation.match_method)}</small></td><td><small>${escapeHtml(relation.reason)}</small></td><td class="text-right">${action}</td></tr>`;
        }

        function renderAutomaticRelations() {
            const body = document.getElementById('automatic-relations-body');
            const meta = document.getElementById('automatic-relations-meta');
            const previous = document.getElementById('automatic-relations-prev');
            const next = document.getElementById('automatic-relations-next');
            if (!body || !meta || !previous || !next) return;

            body.innerHTML = automaticRelations.length
                ? automaticRelations.map(automaticRow).join('')
                : '<tr><td colspan="5" class="text-center text-muted py-3">No hay coincidencias automáticas por encima del umbral de revisión.</td></tr>';
            meta.textContent = automaticState.total ? `Página ${automaticState.page} · ${automaticState.total} coincidencia(s)` : '';
            previous.disabled = automaticState.page <= 1;
            next.disabled = !automaticState.hasMore;
        }

        async function loadAutomaticRelations(page = 1) {
            const left = document.getElementById('automatic-left-provider')?.value || '';
            const right = document.getElementById('automatic-right-provider')?.value || '';
            const button = document.getElementById('load-automatic-relations');
            const body = document.getElementById('automatic-relations-body');
            const params = new URLSearchParams({page: String(page), per_page: '25'});
            if (left) params.set('left_provider', left);
            if (right) params.set('right_provider', right);
            if (button) button.disabled = true;
            if (body) body.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3">Calculando coincidencias...</td></tr>';

            try {
                const payload = await fetchJson(`${automaticRelationsUrl}?${params.toString()}`);
                automaticRelations = payload.data || [];
                automaticState.page = Number(payload.meta?.page || 1);
                automaticState.total = Number(payload.meta?.total || 0);
                automaticState.hasMore = Boolean(payload.meta?.has_more);
                renderAutomaticRelations();
            } catch (error) {
                if (body) body.innerHTML = `<tr><td colspan="5" class="text-center text-danger py-3">${escapeHtml(error.message || 'No se pudieron cargar las coincidencias.')}</td></tr>`;
            } finally {
                if (button) button.disabled = false;
            }
        }

        document.getElementById('load-automatic-relations')?.addEventListener('click', () => loadAutomaticRelations(1));
        document.getElementById('automatic-relations-prev')?.addEventListener('click', () => loadAutomaticRelations(Math.max(1, automaticState.page - 1)));
        document.getElementById('automatic-relations-next')?.addEventListener('click', () => loadAutomaticRelations(automaticState.page + 1));
        document.getElementById('automatic-relations-body')?.addEventListener('click', (event) => {
            const button = event.target.closest('.use-automatic-relation');
            if (button) openSuggestedRelation(automaticRelations[Number(button.dataset.index)]);
        });
        if (rows.length) window.selectMap(0);
    })();
</script>
@stop
