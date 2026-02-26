@extends('adminlte::page')

@section('content')
<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
  <div class="card">
    <div class="card-header">
      <h3 class="card-title">Editar lista — {{ $lista->name }}</h3>
    </div>

    <div class="card-body">
      <form method="POST" action="{{ route('crud.lists.update', $lista) }}">
        @csrf
        @method('PUT')

        <div class="form-group">
          <label>Nombre</label>
          <input class="form-control" name="name" value="{{ old('name', $lista->name) }}" required>
          @error('name') <small class="text-danger">{{ $message }}</small> @enderror
        </div>

        <div class="form-group">
          <label>Descripción (opcional)</label>
          <textarea class="form-control" rows="3" name="description">{{ old('description', $lista->description) }}</textarea>
          @error('description') <small class="text-danger">{{ $message }}</small> @enderror
        </div>

        <div class="form-group">
          <label>Owner (opcional)</label>
          <select class="form-control" name="owner_id">
            <option value="">—</option>
            @foreach($owners as $o)
              <option value="{{ $o->id }}" {{ old('owner_id', $lista->owner_id) == $o->id ? 'selected' : '' }}>
                {{ $o->name }} ({{ $o->email }})
              </option>
            @endforeach
          </select>
          @error('owner_id') <small class="text-danger">{{ $message }}</small> @enderror
        </div>

        <button class="btn btn-primary">Guardar</button>
        <a class="btn btn-secondary" href="{{ route('crud.lists.show', $lista) }}">Volver</a>
      </form>
    </div>
  </div>
</div>
@endsection

@section('css')
    <link rel="stylesheet" href="{{ asset('css/sefar.css') }}">
@endsection
