@extends('adminlte::page')

@section('content')
<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
  <div class="card">
    <div class="card-header">
      <h3 class="card-title">Crear lista</h3>
    </div>

    <div class="card-body">
      <form method="POST" action="{{ route('crud.lists.store') }}">
        @csrf

        <div class="form-group">
          <label>Nombre</label>
          <input class="form-control" name="name" value="{{ old('name') }}" required>
          @error('name') <small class="text-danger">{{ $message }}</small> @enderror
        </div>

        <div class="form-group">
          <label>Descripción (opcional)</label>
          <textarea class="form-control" rows="3" name="description">{{ old('description') }}</textarea>
          @error('description') <small class="text-danger">{{ $message }}</small> @enderror
        </div>

        <div class="form-group">
          <label>Owner (opcional)</label>
          <select class="form-control" name="owner_id">
            <option value="">—</option>
            @foreach($owners as $o)
              <option value="{{ $o->id }}" {{ old('owner_id') == $o->id ? 'selected' : '' }}>
                {{ $o->name }} ({{ $o->email }})
              </option>
            @endforeach
          </select>
          @error('owner_id') <small class="text-danger">{{ $message }}</small> @enderror
        </div>

        <div class="form-group">
          <div class="custom-control custom-switch">
            <input type="checkbox"
                   class="custom-control-input"
                   id="include_in_task_pool"
                   name="include_in_task_pool"
                   value="1"
                   {{ old('include_in_task_pool', '1') ? 'checked' : '' }}>
            <label class="custom-control-label" for="include_in_task_pool">
              Tomar en cuenta esta lista en el pool de tareas
            </label>
          </div>
        </div>

        <div class="form-group">
          <div class="custom-control custom-switch">
            <input type="checkbox"
                   class="custom-control-input"
                   id="disable_hubspot_reassignment"
                   name="disable_hubspot_reassignment"
                   value="1"
                   {{ old('disable_hubspot_reassignment') ? 'checked' : '' }}>
            <label class="custom-control-label" for="disable_hubspot_reassignment">
              No reasignar estos contactos en HubSpot
            </label>
          </div>
          <small class="text-muted">
            Si esta activo, las tareas pueden generarse, pero el owner de HubSpot no se cambia.
          </small>
        </div>

        <button class="btn btn-primary">Crear</button>
        <a class="btn btn-secondary" href="{{ route('crud.lists.index') }}">Volver</a>
      </form>
    </div>
  </div>
</div>
@endsection

@section('css')
    <link rel="stylesheet" href="{{ asset('css/sefar.css') }}">
@endsection
