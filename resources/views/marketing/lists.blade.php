@extends('adminlte::page')

@section('title', 'Listas y segmentos')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center"><div><h1 class="mb-0"><i class="fas fa-users text-primary mr-2"></i>Listas y segmentos</h1><small class="text-muted">Cada importación crea una audiencia reutilizable y deduplicada por correo.</small></div><a href="{{ route('marketing.dashboard') }}" class="btn btn-outline-secondary">Panel</a></div>
@stop

@section('content')
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    <div class="row">
        <div class="col-lg-5">
            <div class="card card-outline card-primary"><div class="card-header"><h3 class="card-title">Importar una audiencia</h3></div><form method="POST" action="{{ route('marketing.lists.import') }}" enctype="multipart/form-data"><div class="card-body">@csrf
                <div class="form-group"><label>Nombre de la lista</label><input required name="name" class="form-control" value="{{ old('name') }}" placeholder="Ej.: Leads de septiembre"></div>
                <div class="form-group"><label>Descripción</label><textarea name="description" class="form-control" rows="2">{{ old('description') }}</textarea></div>
                <div class="form-group"><label>Fuente</label><select name="source" id="source" class="form-control"><option value="app">Contactos de la app</option><option value="teamleader">Teamleader sincronizado</option><option value="hubspot">Segmento/lista de HubSpot</option><option value="csv">Archivo CSV</option></select></div>
                <div class="source-option" data-source="app"><label>Segmento de la app</label><select name="app_segment" class="form-control"><option value="all">Todos los usuarios con email</option><option value="clients">Solo clientes</option><option value="verified">Solo email verificado</option></select></div>
                <div class="source-option d-none" data-source="teamleader"><label>Etiquetas de Teamleader</label><input name="teamleader_tags" class="form-control" value="{{ old('teamleader_tags') }}" placeholder="Ej.: lead, webinar"><small class="text-muted">Se usa la copia sincronizada de Teamleader. Si indicas varias, debe cumplir todas.</small></div>
                <div class="source-option d-none" data-source="hubspot"><label>ID de lista HubSpot</label><input name="hubspot_list_id" class="form-control" value="{{ old('hubspot_list_id') }}" placeholder="Ej.: 123"><small class="text-muted">Admite listas activas o estáticas que la integración pueda leer.</small></div>
                <div class="source-option d-none" data-source="csv"><label>Archivo CSV</label><input type="file" name="csv_file" accept=".csv,.txt,text/csv" class="form-control-file"><small class="text-muted">Encabezados reconocidos: email/correo, first_name/nombre, last_name/apellido, phone/teléfono. El resto se guarda como campo de personalización.</small></div>
            </div><div class="card-footer text-right"><button class="btn btn-primary"><i class="fas fa-file-import mr-1"></i>Crear e importar</button></div></form></div>
        </div>
        <div class="col-lg-7"><div class="card card-outline card-secondary"><div class="card-header"><h3 class="card-title">Audiencias disponibles</h3></div><div class="card-body p-0 table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Lista</th><th>Origen</th><th>Contactos</th><th>Última importación</th></tr></thead><tbody>@forelse($lists as $list)<tr><td><strong>{{ $list->name }}</strong><br><small class="text-muted">{{ $list->description }}</small></td><td><span class="badge badge-secondary">{{ strtoupper($list->source_type) }}</span></td><td>{{ number_format($list->members_count) }}</td><td>{{ optional($list->last_imported_at)->format('d/m/Y H:i') ?? '—' }}</td></tr>@empty<tr><td colspan="4" class="text-center text-muted py-4">No hay listas importadas.</td></tr>@endforelse</tbody></table></div>@if($lists->hasPages())<div class="card-footer">{{ $lists->links() }}</div>@endif</div></div>
    </div>
@stop

@section('js')
<script>
(() => { const source = document.getElementById('source'); const update = () => document.querySelectorAll('.source-option').forEach(item => item.classList.toggle('d-none', item.dataset.source !== source.value)); source.addEventListener('change', update); update(); })();
</script>
@stop
