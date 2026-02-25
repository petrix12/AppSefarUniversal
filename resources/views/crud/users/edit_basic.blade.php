@extends('adminlte::page')

@section('content')
@if(session('success'))
  <div class="alert alert-success">{{ session('success') }}</div>
@endif

<div class="card">
  <div class="card-header">
    <div class="d-flex justify-content-between align-items-center">
      <h3 class="card-title mb-0">Edición básica (Admin) — {{ $user->nombres }} {{ $user->apellidos }}</h3>

      {{-- Link al edit "full" (el que llama Cos) --}}
      <a class="btn btn-outline-secondary btn-sm" href="{{ route('crud.users.edit', $user->id) }}">
        Ir a edición completa
      </a>
    </div>
  </div>

  <div class="card-body">
    <form method="POST" action="{{ route('crud.users.updateBasic', $user->id) }}">
      @csrf
      @method('PUT')

      {{-- =======================
           DATOS BÁSICOS
      ======================== --}}
      <h5 class="mb-3">Datos básicos</h5>

      <div class="row">
        <div class="col-md-6">
          <label>Nombres</label>
          <input class="form-control" name="nombres" value="{{ old('nombres', $user->nombres) }}" required>
          @error('nombres') <small class="text-danger">{{ $message }}</small> @enderror
        </div>

        <div class="col-md-6">
          <label>Apellidos</label>
          <input class="form-control" name="apellidos" value="{{ old('apellidos', $user->apellidos) }}" required>
          @error('apellidos') <small class="text-danger">{{ $message }}</small> @enderror
        </div>

        <div class="col-md-6 mt-3">
          <label>Correo</label>
          <input class="form-control" type="email" name="email" value="{{ old('email', $user->email) }}" required>
          @error('email') <small class="text-danger">{{ $message }}</small> @enderror
        </div>

        <div class="col-md-6 mt-3">
          <label>Teléfono</label>
          <input class="form-control" name="phone" value="{{ old('phone', $user->phone) }}" required>
          @error('phone') <small class="text-danger">{{ $message }}</small> @enderror
        </div>

        <div class="col-md-6 mt-3">
          <label>Nueva contraseña (opcional)</label>
          <input class="form-control" type="password" name="password" autocomplete="new-password">
          @error('password') <small class="text-danger">{{ $message }}</small> @enderror
        </div>

        <div class="col-md-6 mt-3">
          <label>Confirmar contraseña</label>
          <input class="form-control" type="password" name="password_confirmation" autocomplete="new-password">
        </div>
      </div>

      <hr class="my-4">

      {{-- =======================
           ROLES
      ======================== --}}
      <h5 class="mb-3">Roles</h5>
      <div class="row">
        @foreach($roles as $role)
          <div class="col-md-4">
            <label class="d-flex align-items-center">
              <input
                type="checkbox"
                name="roles[]"
                value="{{ $role->name }}"
                class="mr-2"
                {{ in_array($role->name, old('roles', $userRoleNames)) ? 'checked' : '' }}
              >
              <span>{{ $role->name }}</span>
            </label>
          </div>
        @endforeach
      </div>

      <hr class="my-4">

      {{-- =======================
           PERMISOS DIRECTOS
      ======================== --}}
      <h5 class="mb-2">Permisos directos</h5>
      <small class="text-muted d-block mb-3">
        Estos permisos se asignan directamente al usuario (además de los que herede por roles).
      </small>

      <div class="row">
        @foreach($permissions as $perm)
          <div class="col-md-4">
            <label class="d-flex align-items-center">
              <input
                type="checkbox"
                name="permissions[]"
                value="{{ $perm->name }}"
                class="mr-2"
                {{ in_array($perm->name, old('permissions', $userPermissionNames)) ? 'checked' : '' }}
              >
              <span>{{ $perm->name }}</span>
            </label>
          </div>
        @endforeach
      </div>

      <div class="mt-4">
        <button class="btn btn-primary">Guardar</button>
        <a class="btn btn-secondary" href="{{ route('crud.users.index') }}">Volver</a>
      </div>
    </form>
  </div>
</div>
@endsection

@section('css')
    <link rel="stylesheet" href="{{ asset('css/sefar.css') }}">
@endsection
