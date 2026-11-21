@extends('adminlte::page')
@section('title', 'Citas y enlaces')
@section('content_header')<h1>Kalendia · Citas y enlaces</h1>@stop
@section('content')
    @include('kalendia.partials.messages')
    <div class="card"><div class="card-body">
        @include('kalendia.partials.timezone')
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3"><h2 class="h5">{{ $client ? 'Reuniones de '.$client->name : 'Reuniones de clientes' }}</h2>
            <form method="POST" action="{{ route('admin.kalendia.refresh') }}">@csrf<button class="btn btn-outline-primary" type="submit"><i class="fas fa-sync-alt mr-1" aria-hidden="true"></i>Actualizar desde Kalendia</button></form>
        </div>
        <form method="GET" class="row mb-3">@if($client)<input type="hidden" name="client" value="{{ $client->id }}">@endif<div class="col-sm-5"><label for="appointment-status">Estado</label><select class="form-control" id="appointment-status" name="status"><option value="">Todos</option>@foreach(['confirmed'=>'Confirmadas','pending'=>'Pendientes','cancelled'=>'Canceladas','completed'=>'Completadas'] as $value=>$label)<option value="{{ $value }}" @selected(request('status')===$value)>{{ $label }}</option>@endforeach</select></div><div class="col-sm-3 align-self-end mt-2"><button class="btn btn-primary">Filtrar</button></div></form>
        @include('kalendia.partials.appointments', ['showClient' => true])
        {{ $appointments->links() }}
        <small class="text-muted">La sincronización consulta una página por ejecución y continúa desde el último punto. Los cambios de agenda se reflejan también en el COS.</small>
    </div></div>
@stop
