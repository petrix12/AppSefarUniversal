@extends('adminlte::page')

@section('title', $lead->exists ? 'Editar lead' : 'Nuevo lead')

@section('content_header')
    <h1><i class="fas fa-user-plus mr-2 text-primary"></i>{{ $lead->exists ? 'Editar lead' : 'Nuevo lead' }}</h1>
@stop

@section('content')
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    <div class="card card-outline card-primary"><div class="card-body">
        <form method="POST" action="{{ $action }}">
            @csrf @if($method !== 'POST') @method($method) @endif
            <div class="form-row">
                <div class="form-group col-md-6"><label>Nombre</label><input name="nombre" value="{{ old('nombre', $lead->nombre) }}" class="form-control" required>@error('nombre')<small class="text-danger">{{ $message }}</small>@enderror</div>
                <div class="form-group col-md-6"><label>Correo</label><input type="email" name="email" value="{{ old('email', $lead->email) }}" class="form-control">@error('email')<small class="text-danger">{{ $message }}</small>@enderror</div>
                <div class="form-group col-md-6"><label>Teléfono</label><input name="telefono" value="{{ old('telefono', $lead->telefono) }}" class="form-control"></div>
                <div class="form-group col-md-6"><label>Pasaporte / identificación</label><input name="passport" value="{{ old('passport', $lead->passport) }}" class="form-control"></div>
                <div class="form-group col-md-4"><label>Origen</label><input name="origen" value="{{ old('origen', $lead->origen) }}" class="form-control" placeholder="Formulario, WhatsApp, referido..."></div>
                <div class="form-group col-md-4"><label>Etapa</label><select name="etapa" class="form-control" required>@foreach(\App\Models\Lead::STAGES as $key => $label)<option value="{{ $key }}" @selected(old('etapa', $lead->etapa) === $key)>{{ $label }}</option>@endforeach</select></div>
                <div class="form-group col-md-4"><label>Estado</label>@if($lead->estado === \App\Models\Lead::STATUS_CONVERTED)<input type="hidden" name="estado" value="convertido"><div class="form-control bg-light">Convertido a representado</div>@else<select name="estado" class="form-control"><option value="abierto" @selected(old('estado', $lead->estado) === 'abierto')>Abierto</option><option value="perdido" @selected(old('estado', $lead->estado) === 'perdido')>Perdido</option></select>@endif</div>
                <div class="form-group col-md-6"><label>Responsable interno</label><select name="user_id" class="form-control"><option value="">Sin asignar</option>@foreach($internalUsers as $internal)<option value="{{ $internal->id }}" @selected((string) old('user_id', $lead->user_id) === (string) $internal->id)>{{ $internal->name }} · {{ $internal->email }}</option>@endforeach</select></div>
            </div>
            <a href="{{ route('crm.leads.index') }}" class="btn btn-outline-secondary">Volver</a>
            <button class="btn btn-primary">Guardar lead</button>
        </form>
    </div></div>
@stop
