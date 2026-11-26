@extends('adminlte::page')

@section('content')
<div class="card">
  <div class="card-header">
    <div class="d-flex justify-content-between align-items-center">
      <h3 class="card-title mb-0">Edición básica del representado — {{ $user->nombres }} {{ $user->apellidos }}</h3>

      <a class="btn btn-outline-secondary btn-sm" href="{{ route('crud.users.edit', $user->id) }}">
        Ir a edición completa
      </a>
    </div>
  </div>

  <div class="card-body">
    @include('crud.users.partials.basic-editor', [
      'user' => $user,
      'roles' => $roles,
      'permissions' => $permissions,
      'userRoleNames' => $userRoleNames,
      'userPermissionNames' => $userPermissionNames,
      'embeddedInCos' => false,
    ])
  </div>
</div>
@endsection

@section('css')
    <link rel="stylesheet" href="{{ asset('css/sefar.css') }}">
@endsection
