@extends('adminlte::page')

@section('title', 'Editar HubSpot Owner')

@section('content_header')

@stop

@section('content')
<div class="container">
  <h1 class="mb-3">Nuevo Owner</h1>

  <div class="card">
    <div class="card-body">
      <form method="POST" action="{{ route('hubspot-owners.store') }}">
        @csrf

        <div class="mb-3">
          <label class="form-label">Owner ID (HubSpot)</label>
          <input name="id" class="form-control" value="{{ old('id') }}" required />
          @error('id') <div class="text-danger small">{{ $message }}</div> @enderror
        </div>

        <div class="mb-3">
          <label class="form-label">Nombre</label>
          <input name="name" class="form-control" value="{{ old('name') }}" required />
          @error('name') <div class="text-danger small">{{ $message }}</div> @enderror
        </div>

        <div class="mb-3">
          <label class="form-label">Email (opcional)</label>
          <input name="email" class="form-control" value="{{ old('email') }}" />
          @error('email') <div class="text-danger small">{{ $message }}</div> @enderror
        </div>

        <div class="form-check mb-3">
          <input class="form-check-input" type="checkbox" name="active" value="1" checked />
          <label class="form-check-label">Activo</label>
        </div>

        <button class="btn btn-success">Guardar</button>
        <a class="btn btn-link" href="{{ route('hubspot-owners.index') }}">Volver</a>
      </form>
    </div>
  </div>
</div>
@stop

@section('css')
    <link rel="stylesheet" href="{{ asset('css/sefar.css') }}">
@stop

@section('js')
@stop
