@extends('adminlte::page')
@section('title', 'Superadmins')
@section('content_header')
    <h1>Superadmins</h1>
@stop
@section('content')
    @if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
    @error('is_superadmin')<div class="alert alert-danger">{{ $message }}</div>@enderror
    <div class="card card-outline card-primary">
        <div class="card-header"><h2 class="card-title">Permisos especiales</h2></div>
        <div class="card-body">
            <p class="text-muted">Solo los superadmins pueden conceder esta marca y eliminar notas de representados.</p>
            <form method="GET" class="mb-3">
                <label for="superadmin-search">Buscar por nombre o correo</label>
                <div class="input-group"><input class="form-control" id="superadmin-search" name="search" value="{{ $search }}" maxlength="255">
                    <div class="input-group-append"><button class="btn btn-primary" type="submit">Buscar</button></div>
                </div>
            </form>
            <div class="table-responsive"><table class="table table-striped">
                <thead><tr><th>Usuario</th><th>Correo</th><th>Superadmin</th><th>Acción</th></tr></thead>
                <tbody>@foreach($users as $user)<tr>
                    <td>{{ $user->name }}</td><td>{{ $user->email }}</td><td>{{ $user->isSuperadmin() ? 'Sí' : 'No' }}</td>
                    <td><form method="POST" action="{{ route('admin.superadmins.update', $user) }}" onsubmit="return confirm('¿Actualizar los permisos especiales de este usuario?')">
                        @csrf @method('PATCH')<input type="hidden" name="is_superadmin" value="{{ $user->isSuperadmin() ? 0 : 1 }}">
                        <button type="submit" class="btn btn-outline-primary btn-sm">{{ $user->isSuperadmin() ? 'Retirar marca' : 'Marcar como superadmin' }}</button>
                    </form></td>
                </tr>@endforeach</tbody>
            </table></div>
            {{ $users->links() }}
        </div>
    </div>
@stop
