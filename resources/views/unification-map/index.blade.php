@extends('adminlte::page')

@section('title', 'Mapa de unificación')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="mb-0"><i class="fas fa-project-diagram mr-2 text-primary"></i>Mapa de unificación</h1>
            <small class="text-muted">Inventario y decisiones de diseño: App, HubSpot, Teamleader y Monday.</small>
        </div>
        @if($summary['relation_storage_ready'])
            <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#newAuditRelationModal">
                <i class="fas fa-plus mr-1"></i>Relacionar dos plataformas
            </button>
        @else
            <button type="button" class="btn btn-outline-secondary" disabled title="La tabla de auditoría aún no está desplegada">
                <i class="fas fa-lock mr-1"></i>Modo lectura
            </button>
        @endif
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
        <strong>Auditoría primero.</strong> Este mapa solo lee los catálogos locales y guarda, si se habilita, decisiones de auditoría.
        No consulta APIs externas, no crea campos en <code>users</code>, no crea <code>integration_field_mappings</code>, no activa automatizaciones y no mueve clientes entre tableros.
    </div>

    @if(! $summary['relation_storage_ready'])
        <div class="alert alert-info">
            El registro para guardar relaciones está preparado en código, pero su migración no se ha ejecutado. Por ahora este mapa es estrictamente de lectura.
        </div>
    @endif

    <div class="row">
        <div class="col-lg-3 col-sm-6">
            <div class="small-box bg-info"><div class="inner"><h3>{{ $summary['hubspot_fields'] }}</h3><p>Campos HubSpot del catálogo</p></div><div class="icon"><i class="fab fa-hubspot"></i></div></div>
        </div>
        <div class="col-lg-3 col-sm-6">
            <div class="small-box bg-success"><div class="inner"><h3>{{ $summary['teamleader_fields'] }}</h3><p>Campos Teamleader asociados</p></div><div class="icon"><i class="fas fa-people-arrows"></i></div></div>
        </div>
        <div class="col-lg-3 col-sm-6">
            <div class="small-box bg-primary"><div class="inner"><h3>{{ $summary['app_fields'] }}</h3><p>Campos de referencia en App</p></div><div class="icon"><i class="fas fa-database"></i></div></div>
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
            <div class="card-tools"><input id="map-search" type="search" class="form-control form-control-sm" placeholder="Buscar campo, etiqueta o tablero"></div>
        </div>
        <div class="card-body p-0 table-responsive">
            <table class="table table-hover table-sm mb-0" id="map-table">
                <thead><tr><th>Campo App</th><th>HubSpot</th><th>Teamleader</th><th>Monday</th><th>Base de coincidencia</th><th>Auditoría</th><th></th></tr></thead>
                <tbody>
                @forelse($map_rows as $index => $row)
                    @php
                        $app = $row['app'];
                        $hubspot = $row['hubspot'];
                        $teamleader = $row['teamleader'];
                        $monday = $row['monday_matches'];
                        $audit = $row['audit_links'];
                        $searchText = strtolower(implode(' ', array_filter([
                            $app['key'] ?? null, $app['label'] ?? null,
                            collect($hubspot)->pluck('key')->implode(' '), collect($hubspot)->pluck('label')->implode(' '),
                            collect($teamleader)->pluck('label')->implode(' '), collect($teamleader)->pluck('key')->implode(' '),
                            collect($monday)->pluck('label')->implode(' '), collect($monday)->pluck('scope_key')->implode(' '),
                        ])));
                    @endphp
                    <tr data-map-row="{{ $index }}" data-search="{{ $searchText }}">
                        <td>
                            @if($app)
                                <strong>{{ $app['label'] }}</strong><br><code>{{ $app['key'] }}</code><br><small class="text-muted">{{ $app['source'] }}</small>
                            @else
                                <span class="text-muted">Sin campo propuesto</span>
                            @endif
                        </td>
                        <td>
                            @forelse($hubspot as $field)<div><code>{{ $field['key'] }}</code></div>@empty<span class="text-muted">—</span>@endforelse
                        </td>
                        <td>
                            @forelse($teamleader as $field)<div title="{{ $field['key'] }}"><strong>{{ $field['label'] }}</strong><br><small class="text-muted">{{ $field['context'] }} · {{ $field['type'] ?: 'tipo sin catalogar' }}</small></div>@empty<span class="text-muted">—</span>@endforelse
                        </td>
                        <td>
                            @forelse($monday as $field)<div><strong>{{ $field['label'] }}</strong><br><small class="text-muted">Tablero {{ $field['scope_key'] }} · {{ $field['key'] }} · {{ isset($field['confidence']) && $field['confidence'] !== null ? $field['confidence'].'%' : 'pendiente de asociar' }}</small></div>@empty<span class="text-muted">Sin coincidencia automática</span>@endforelse
                        </td>
                        <td>
                            @if($row['match_method'] === 'legacy_catalog')<span class="badge badge-success">Catálogo legado</span>
                            @elseif($row['match_method'] === 'app_only')<span class="badge badge-secondary">Solo App</span>
                            @elseif($row['match_method'] === 'unmatched')<span class="badge badge-warning">Pendiente de asociar</span>
                            @else<span class="badge badge-secondary">{{ $row['match_method'] }}</span>@endif
                        </td>
                        <td>
                            @forelse($audit as $decision)<span class="badge badge-{{ $decision['status'] === 'approved' ? 'success' : ($decision['status'] === 'rejected' ? 'danger' : ($decision['status'] === 'needs_information' ? 'warning' : 'secondary')) }} d-block mb-1">{{ $decision['status'] }}</span>@empty<span class="text-muted">No revisado</span>@endforelse
                        </td>
                        <td class="text-right"><button type="button" class="btn btn-sm btn-outline-primary select-map" data-index="{{ $index }}">Ver</button></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">No se encontraron catálogos locales para auditar todavía.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer text-muted small">
            {{ $summary['legacy_associations'] }} asociaciones históricas; {{ $summary['app_legacy_columns'] }} tienen columna física detectada en <code>users</code>.
            @if($summary['active_mappings'])
                Hay {{ $summary['active_mappings'] }} mapeos operativos ya existentes: se muestran como contexto, pero este módulo no los modifica.
            @else
                No se detectaron mapeos operativos activos en la nueva capa.
            @endif
        </div>
    </div>

    <div class="card card-outline card-warning">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-link mr-1"></i>Relaciones directas y derivadas</h3>
            <div class="card-tools"><span class="badge badge-info mr-1">{{ $summary['automatic_relations'] }} automáticas</span><span class="badge badge-warning">{{ $summary['derived_relations'] }} derivadas</span></div>
        </div>
        <div class="card-body">
            <p class="mb-2">Puedes relacionar cualquier par de plataformas. Cuando haya relaciones directas aprobadas o históricas, el mapa propone cadenas del tipo <code>A ↔ B ↔ C</code>; la relación <code>A ↔ C</code> siempre queda como sugerencia y requiere revisión independiente.</p>
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
        </div>
        <div class="card-body border-top p-0 table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead><tr><th colspan="5" class="bg-light">Coincidencias automáticas entre todos los campos locales conocidos</th></tr><tr><th>Primera plataforma</th><th>Segunda plataforma</th><th>Confianza</th><th>Motivo</th><th></th></tr></thead>
                <tbody>
                @forelse($automatic_relations as $index => $relation)
                    <tr>
                        <td><strong>{{ ucfirst($relation['left']['provider']) }}</strong><br><small>{{ $relation['left']['label'] }} · <code>{{ $relation['left']['key'] }}</code></small>@if($relation['left_source'])<br><small class="text-muted">{{ $relation['left_source'] }}</small>@endif</td>
                        <td><strong>{{ ucfirst($relation['right']['provider']) }}</strong><br><small>{{ $relation['right']['label'] }} · <code>{{ $relation['right']['key'] }}</code></small>@if($relation['right_source'])<br><small class="text-muted">{{ $relation['right_source'] }}</small>@endif</td>
                        <td><span class="badge badge-{{ $relation['confidence'] === 100 ? 'success' : 'warning' }}">{{ $relation['confidence'] }}%</span><br><small>{{ $relation['match_method'] }}</small></td>
                        <td><small>{{ $relation['reason'] }}</small></td>
                        <td class="text-right">
                            @if($summary['relation_storage_ready'])
                                <button type="button" class="btn btn-xs btn-outline-primary use-automatic-relation" data-index="{{ $index }}">Convertir en propuesta</button>
                            @else
                                <span class="text-muted small">Solo lectura</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-3">No hay coincidencias automáticas por encima del umbral de revisión.</td></tr>
                @endforelse
                </tbody>
            </table>
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
                            <form action="{{ route('unification-map.relations.review', $relation['id']) }}" method="POST" class="d-inline">@csrf @method('PATCH')<input type="hidden" name="status" value="rejected"><input type="hidden" name="notes" value="{{ $relation['notes'] }}"><button class="btn btn-xs btn-outline-danger">Rechazar</button></form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-muted py-3">Aún no has registrado conexiones manuales entre plataformas.</td></tr>
                @endforelse
                </tbody></table>
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
                            <form action="{{ route('unification-map.review', $decision['id']) }}" method="POST" class="d-inline">@csrf @method('PATCH')<input type="hidden" name="status" value="rejected"><input type="hidden" name="notes" value="{{ $decision['notes'] }}"><button class="btn btn-xs btn-outline-danger">Rechazar</button></form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-3">Aún no hay propuestas manuales.</td></tr>
                @endforelse
                </tbody></table>
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
        <div class="modal fade" id="newAuditRelationModal" tabindex="-1" role="dialog" aria-labelledby="newAuditRelationTitle" aria-hidden="true">
            <div class="modal-dialog modal-lg" role="document"><form method="POST" action="{{ route('unification-map.relations.store') }}" class="modal-content" id="audit-relation-form">@csrf
                <div class="modal-header"><h5 class="modal-title" id="newAuditRelationTitle">Relacionar dos plataformas para auditoría</h5><button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button></div>
                <div class="modal-body">
                    <div class="alert alert-info small">Selecciona dos extremos. Esta conexión es de diseño: no copia datos, no crea campos y no activa ninguna sincronización. Solo las conexiones aprobadas pueden servir de puente para sugerencias derivadas.</div>
                    <div class="mb-3">
                        <button type="button" id="ai-suggest-platform-pair" class="btn btn-sm btn-outline-info" disabled @if(! $summary['ai_suggestions_available']) title="Configura OPENROUTER_API_KEY para habilitarlo" @endif>
                            <i class="fas fa-magic mr-1"></i>IA: revisar este par
                        </button>
                        <small class="d-block text-muted mt-1">Solo analiza coincidencias locales de las dos plataformas seleccionadas (máximo 40); no envía datos de clientes ni guarda una relación.</small>
                        <div id="ai-platform-suggestions" class="alert alert-info small mt-2 mb-0 d-none" role="status"></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label>Primera plataforma</label>
                            <select id="relation-left-provider" name="left_provider" class="form-control relation-provider"><option value="app">App</option><option value="hubspot">HubSpot</option><option value="teamleader">Teamleader</option><option value="monday">Monday</option></select>
                            <label class="mt-2">Campo</label>
                            <input type="search" id="relation-left-field-search" class="form-control form-control-sm mb-1 relation-field-search" data-side="left" placeholder="Filtrar por nombre o clave">
                            <select id="relation-left-field-picker" class="form-control relation-field-picker" data-side="left"></select>
                            <input type="hidden" name="left_entity_type" id="relation-left-entity-type">
                            <input type="hidden" name="left_scope_key" id="relation-left-scope-key">
                            <input type="hidden" name="left_field_key" id="relation-left-field-key">
                            <input type="hidden" name="left_field_label" id="relation-left-field-label">
                        </div>
                        <div class="form-group col-md-6">
                            <label>Segunda plataforma</label>
                            <select id="relation-right-provider" name="right_provider" class="form-control relation-provider"><option value="hubspot">HubSpot</option><option value="app">App</option><option value="teamleader">Teamleader</option><option value="monday">Monday</option></select>
                            <label class="mt-2">Campo</label>
                            <input type="search" id="relation-right-field-search" class="form-control form-control-sm mb-1 relation-field-search" data-side="right" placeholder="Filtrar por nombre o clave">
                            <select id="relation-right-field-picker" class="form-control relation-field-picker" data-side="right"></select>
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
        const csrfToken = @json(csrf_token());
        const platformFields = {{ \Illuminate\Support\Js::from($field_options) }};
        const derivedRelations = {{ \Illuminate\Support\Js::from($derived_relations) }};
        const automaticRelations = {{ \Illuminate\Support\Js::from($automatic_relations) }};
        const text = (field) => field ? `<strong>${escapeHtml(field.label || field.key || '—')}</strong><br><code>${escapeHtml(field.key || '')}</code>${field.scope_key ? `<br><small>Ámbito: ${escapeHtml(field.scope_key)}</small>` : ''}` : '—';
        const many = (fields, empty) => fields && fields.length ? fields.map(text).join('<hr class="my-1">') : `<span class="text-muted">${empty}</span>`;
        const escapeHtml = (value) => String(value ?? '').replace(/[&<>'"]/g, (character) => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;'}[character]));

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
            const status = row.match_method === 'legacy_catalog' ? 'Catálogo legado: revisar y decidir' : (row.match_method === 'unmatched' ? 'Campo pendiente de asociar' : 'Diseño por revisar');
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

        function populateRelationPicker(side) {
            const provider = document.getElementById(`relation-${side}-provider`);
            const picker = document.getElementById(`relation-${side}-field-picker`);
            if (!provider || !picker) return;

            const fields = platformFields[provider.value] || [];
            const search = document.getElementById(`relation-${side}-field-search`);
            const term = (search?.value || '').toLowerCase().trim();
            picker.innerHTML = '';
            fields.forEach((field, index) => {
                const searchable = `${field.label || ''} ${field.key || ''} ${field.source || ''}`.toLowerCase();
                if (term && !searchable.includes(term)) return;
                const option = document.createElement('option');
                option.value = String(index);
                option.textContent = `${field.label || field.key} · ${field.key}${field.scope_key && field.scope_key !== '*' ? ` · tablero ${field.scope_key}` : ''}${field.source ? ` · ${field.source}` : ''}`;
                picker.appendChild(option);
            });
            picker.disabled = picker.options.length === 0;
            if (!picker.options.length) {
                const option = document.createElement('option');
                option.value = '';
                option.textContent = 'No hay campos locales disponibles';
                picker.appendChild(option);
            }
            syncRelationEndpoint(side);
            updatePairAiButton();
        }

        function syncRelationEndpoint(side) {
            const provider = document.getElementById(`relation-${side}-provider`);
            const picker = document.getElementById(`relation-${side}-field-picker`);
            if (!provider || !picker) return;
            const field = (platformFields[provider.value] || [])[Number(picker.value)];
            document.getElementById(`relation-${side}-entity-type`).value = field?.entity_type || '';
            document.getElementById(`relation-${side}-scope-key`).value = field?.scope_key || '';
            document.getElementById(`relation-${side}-field-key`).value = field?.key || '';
            document.getElementById(`relation-${side}-field-label`).value = field?.label || '';
        }

        function selectedPlatformPair() {
            return {
                left: document.getElementById('relation-left-provider')?.value || '',
                right: document.getElementById('relation-right-provider')?.value || '',
            };
        }

        function updatePairAiButton() {
            const button = document.getElementById('ai-suggest-platform-pair');
            const {left, right} = selectedPlatformPair();
            if (button) button.disabled = !aiAvailable || !left || !right || left === right;
        }

        function clearPlatformAiSuggestions() {
            const target = document.getElementById('ai-platform-suggestions');
            if (target) target.classList.add('d-none');
        }

        document.getElementById('ai-suggest-platform-pair')?.addEventListener('click', async function () {
            const {left, right} = selectedPlatformPair();
            if (!aiAvailable || !left || !right || left === right) return;

            const button = this;
            const target = document.getElementById('ai-platform-suggestions');
            button.disabled = true;
            button.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Analizando este par';
            target.className = 'alert alert-info small mt-2 mb-0';
            target.textContent = `Analizando solo coincidencias locales de ${left} ↔ ${right}; no se envían datos de clientes.`;

            try {
                const response = await fetch(suggestUrl, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken},
                    body: JSON.stringify({left_provider: left, right_provider: right}),
                });
                const payload = await response.json().catch(() => ({}));
                if (!response.ok) throw new Error(payload.message || `No se pudo generar la sugerencia (HTTP ${response.status}).`);

                const suggestion = payload.suggestion || {};
                const items = suggestion.suggestions || [];
                if (!items.length) {
                    target.className = 'alert alert-secondary small mt-2 mb-0';
                    target.textContent = suggestion.used_ai
                        ? 'La IA no recomendó convertir ninguna coincidencia de este par en propuesta. No se guardó ni activó nada.'
                        : 'No hay coincidencias locales suficientes para este par; OpenRouter no fue consultado y no se consumieron créditos.';
                    return;
                }

                target.className = 'alert alert-info small mt-2 mb-0';
                target.innerHTML = `<strong>IA · ${items.length} sugerencia(s) para revisar</strong><ul class="mb-1 pl-3 mt-2">${items.map((item, index) => `<li class="mb-2"><strong>${escapeHtml(item.left.label)} ↔ ${escapeHtml(item.right.label)}</strong> <span class="badge badge-info">${escapeHtml(item.confidence)}%</span><br>${escapeHtml(item.reason || 'Sin explicación adicional.')}<br><button type="button" class="btn btn-xs btn-outline-primary mt-1 use-ai-relation" data-ai-index="${index}">Usar como propuesta</button></li>`).join('')}</ul><small>Modelo: ${escapeHtml(suggestion.model)}. Requiere guardado y revisión humana; no se activó ningún mapeo.</small>`;
                target.querySelectorAll('.use-ai-relation').forEach((item) => item.addEventListener('click', () => {
                    openSuggestedRelation(items[Number(item.dataset.aiIndex)]);
                }));
            } catch (error) {
                target.className = 'alert alert-warning small mt-2 mb-0';
                target.textContent = error.message || 'No se pudo generar la sugerencia.';
            } finally {
                updatePairAiButton();
                button.innerHTML = '<i class="fas fa-magic mr-1"></i>IA: revisar este par';
            }
        });

        ['left', 'right'].forEach((side) => {
            document.getElementById(`relation-${side}-provider`)?.addEventListener('change', () => {
                clearPlatformAiSuggestions();
                populateRelationPicker(side);
            });
            document.getElementById(`relation-${side}-field-picker`)?.addEventListener('change', () => syncRelationEndpoint(side));
            document.getElementById(`relation-${side}-field-search`)?.addEventListener('input', () => populateRelationPicker(side));
            populateRelationPicker(side);
        });
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
        document.querySelectorAll('.use-automatic-relation').forEach((button) => button.addEventListener('click', () => {
            openSuggestedRelation(automaticRelations[Number(button.dataset.index)]);
        }));

        function openSuggestedRelation(relation) {
            if (!relation) return;
            applyDerivedEndpoint('left', relation.left);
            applyDerivedEndpoint('right', relation.right);
            if (window.jQuery) window.jQuery('#newAuditRelationModal').modal('show');
        }

        function applyDerivedEndpoint(side, endpoint) {
            const provider = document.getElementById(`relation-${side}-provider`);
            const picker = document.getElementById(`relation-${side}-field-picker`);
            if (!provider || !picker) return;
            provider.value = endpoint.provider;
            const search = document.getElementById(`relation-${side}-field-search`);
            if (search) search.value = '';
            populateRelationPicker(side);
            const fields = platformFields[endpoint.provider] || [];
            const index = fields.findIndex((field) => field.key === endpoint.key
                && field.scope_key === endpoint.scope_key
                && field.entity_type === endpoint.entity_type);
            if (index >= 0) picker.value = String(index);
            syncRelationEndpoint(side);
        }
        document.getElementById('map-search')?.addEventListener('input', function () {
            const term = this.value.toLowerCase().trim();
            document.querySelectorAll('#map-table tbody tr[data-search]').forEach((row) => { row.style.display = !term || row.dataset.search.includes(term) ? '' : 'none'; });
        });
        if (rows.length) window.selectMap(0);
    })();
</script>
@stop
