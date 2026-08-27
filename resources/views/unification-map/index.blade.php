@extends('adminlte::page')

@section('title', 'Mapa de unificación')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="mb-0"><i class="fas fa-project-diagram mr-2 text-primary"></i>Mapa de unificación</h1>
            <small class="text-muted">Inventario y decisiones de diseño: App, HubSpot, Teamleader y Monday.</small>
        </div>
        @if($summary['audit_storage_ready'])
            <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#newAuditLinkModal">
                <i class="fas fa-plus mr-1"></i>Proponer relación
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

    @if(! $summary['audit_storage_ready'])
        <div class="alert alert-info">
            El registro para guardar propuestas está preparado en código, pero su migración no se ha ejecutado. Por ahora este mapa es estrictamente de lectura.
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
            <div class="card-tools"><span id="selected-map-status" class="badge badge-secondary">Selecciona una fila</span></div>
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
                            @forelse($monday as $field)<div><strong>{{ $field['label'] }}</strong><br><small class="text-muted">Tablero {{ $field['scope_key'] }} · {{ $field['key'] }} · {{ $field['confidence'] }}%</small></div>@empty<span class="text-muted">Sin coincidencia automática</span>@endforelse
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
        document.getElementById('map-search')?.addEventListener('input', function () {
            const term = this.value.toLowerCase().trim();
            document.querySelectorAll('#map-table tbody tr[data-search]').forEach((row) => { row.style.display = !term || row.dataset.search.includes(term) ? '' : 'none'; });
        });
        if (rows.length) window.selectMap(0);
    })();
</script>
@stop
