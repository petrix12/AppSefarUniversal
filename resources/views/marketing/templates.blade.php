@extends('adminlte::page')

@section('title', 'Plantillas de email')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center"><h1 class="mb-0"><i class="fas fa-palette text-primary mr-2"></i>Plantillas</h1><a href="{{ route('marketing.campaigns.create') }}" class="btn btn-primary">Usar en una campaña</a></div>
@stop

@section('content')
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    <div class="row"><div class="col-lg-5"><div class="card card-outline card-primary"><div class="card-header"><h3 class="card-title">Guardar plantilla HTML</h3></div><form method="POST" action="{{ route('marketing.templates.store') }}"><div class="card-body">@csrf
        <div class="form-group"><label>Nombre</label><input required class="form-control" name="name" placeholder="Boletín mensual"></div><div class="form-group"><label>Asunto sugerido</label><input class="form-control" name="subject"></div><div class="form-group mb-0"><label>HTML</label><textarea required class="form-control font-monospace" rows="12" name="body_html" placeholder="&lt;h1&gt;Hola {{ '{{first_name}}' }}&lt;/h1&gt;"></textarea><small class="text-muted">También puedes guardar el HTML que preparaste en el constructor de campañas.</small></div>
    </div><div class="card-footer text-right"><button class="btn btn-primary">Guardar plantilla</button></div></form></div></div>
    <div class="col-lg-7"><div class="card card-outline card-secondary"><div class="card-header"><h3 class="card-title">Plantillas guardadas</h3></div><div class="card-body p-0 table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Plantilla</th><th>Asunto</th><th>Creada</th></tr></thead><tbody>@forelse($templates as $template)<tr><td><strong>{{ $template->name }}</strong></td><td>{{ $template->subject }}</td><td>{{ $template->created_at->format('d/m/Y') }}</td></tr>@empty<tr><td colspan="3" class="text-center text-muted py-4">No hay plantillas todavía.</td></tr>@endforelse</tbody></table></div>@if($templates->hasPages())<div class="card-footer">{{ $templates->links() }}</div>@endif</div></div></div>
@stop
