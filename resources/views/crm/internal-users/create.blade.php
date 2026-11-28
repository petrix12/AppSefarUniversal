@extends('adminlte::page')

@section('title', 'Crear usuario interno')

@section('content_header')
    <h1><i class="fas fa-user-plus mr-2 text-primary"></i>Crear usuario interno</h1>
@stop

@section('content')
    <div class="card card-outline card-primary"><div class="card-body">
        <p class="text-muted">La cuenta se crea con una contraseña aleatoria. La persona recibirá un enlace para establecer la suya.</p>
        <form method="POST" action="{{ route('crm.internal-users.store') }}">
            @csrf
            <div class="form-row">
                <div class="form-group col-md-6"><label>Nombre completo</label><input name="name" value="{{ old('name') }}" class="form-control" required maxlength="254">@error('name')<small class="text-danger">{{ $message }}</small>@enderror</div>
                <div class="form-group col-md-6"><label>Correo corporativo</label><input type="email" name="email" value="{{ old('email') }}" class="form-control" required maxlength="255">@error('email')<small class="text-danger">{{ $message }}</small>@enderror</div>
                <div class="form-group col-md-6"><label>Teléfono</label><input name="phone" value="{{ old('phone') }}" class="form-control" maxlength="80">@error('phone')<small class="text-danger">{{ $message }}</small>@enderror</div>
                <div class="form-group col-md-6"><label>Roles internos</label><div class="border rounded p-3">@forelse($roles as $role)<div class="custom-control custom-checkbox"><input class="custom-control-input" type="checkbox" name="roles[]" id="role-{{ $role->id }}" value="{{ $role->name }}" @checked(in_array($role->name, old('roles', []), true))><label class="custom-control-label" for="role-{{ $role->id }}">{{ \App\Support\RepresentedLanguage::label($role->name) }}</label></div>@empty<span class="text-muted">No hay roles internos disponibles.</span>@endforelse</div>@error('roles')<small class="text-danger d-block">{{ $message }}</small>@enderror @error('roles.*')<small class="text-danger d-block">{{ $message }}</small>@enderror</div>
            </div>
            <a href="{{ route('crm.internal-users.index') }}" class="btn btn-outline-secondary">Volver</a><button class="btn btn-primary" @disabled($roles->isEmpty())>Crear cuenta</button>
        </form>
    </div></div>
@stop
