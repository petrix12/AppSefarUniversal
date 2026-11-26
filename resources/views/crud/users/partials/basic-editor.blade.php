@php
    $embeddedInCos = $embeddedInCos ?? false;
    $errorBag = $embeddedInCos ? 'cosBasic' : 'default';
    $fieldPrefix = ($embeddedInCos ? 'cos-basic-' : 'basic-') . $user->getKey();
@endphp

@if(session('success'))
  <div class="alert alert-success" role="status">{{ session('success') }}</div>
@endif

@if(session('sync_conflict'))
  @php($conflict = session('sync_conflict'))
  <div class="alert alert-warning" role="alert">
    <strong>Conflicto de sincronización.</strong>
    <div>{{ $conflict['message'] ?? 'Revisa los datos antes de continuar.' }}</div>

    @if(!empty($conflict['conflicting_user']))
      <div class="mt-2">
        El dato en conflicto ya está asociado a:
        <a href="{{ route('crud.users.editBasic', $conflict['conflicting_user']['id']) }}">
          #{{ $conflict['conflicting_user']['id'] }}
          {{ $conflict['conflicting_user']['nombres'] ?? '' }}
          {{ $conflict['conflicting_user']['apellidos'] ?? '' }}
        </a>
        ({{ $conflict['conflicting_user']['email'] ?? 'sin correo' }}).
      </div>
    @endif

    @if(!empty($conflict['updates_to_db']))
      <div class="mt-2 small">
        Valores sugeridos por HubSpot:
        @foreach($conflict['updates_to_db'] as $field => $value)
          <span class="badge badge-light border mr-1">{{ $field }}: {{ is_scalar($value) ? $value : json_encode($value) }}</span>
        @endforeach
      </div>
    @endif
  </div>
@endif

<form method="POST" action="{{ route('crud.users.updateBasic', $user->id) }}">
  @csrf
  @method('PUT')

  @if($embeddedInCos)
    <input type="hidden" name="return_to_cos" value="1">
  @endif

  <h5 class="mb-3">{{ $embeddedInCos ? 'Datos básicos del representado' : 'Datos básicos' }}</h5>

  <div class="row">
    <div class="col-md-12">
      <label for="{{ $fieldPrefix }}-name">Nombre visible</label>
      <input id="{{ $fieldPrefix }}-name" class="form-control" name="name" value="{{ old('name', $user->name) }}" required>
      @error('name', $errorBag) <small class="text-danger">{{ $message }}</small> @enderror
    </div>

    <div class="col-md-6 mt-3">
      <label for="{{ $fieldPrefix }}-nombres">Nombres</label>
      <input id="{{ $fieldPrefix }}-nombres" class="form-control" name="nombres" value="{{ old('nombres', $user->nombres) }}" required>
      @error('nombres', $errorBag) <small class="text-danger">{{ $message }}</small> @enderror
    </div>

    <div class="col-md-6 mt-3">
      <label for="{{ $fieldPrefix }}-apellidos">Apellidos</label>
      <input id="{{ $fieldPrefix }}-apellidos" class="form-control" name="apellidos" value="{{ old('apellidos', $user->apellidos) }}" required>
      @error('apellidos', $errorBag) <small class="text-danger">{{ $message }}</small> @enderror
    </div>

    <div class="col-md-6 mt-3">
      <label for="{{ $fieldPrefix }}-email">Correo</label>
      <input id="{{ $fieldPrefix }}-email" class="form-control" type="email" name="email" value="{{ old('email', $user->email) }}" required>
      @error('email', $errorBag) <small class="text-danger">{{ $message }}</small> @enderror
    </div>

    <div class="col-md-6 mt-3">
      <label for="{{ $fieldPrefix }}-phone">Teléfono</label>
      <input id="{{ $fieldPrefix }}-phone" class="form-control" name="phone" value="{{ old('phone', $user->phone) }}" required>
      @error('phone', $errorBag) <small class="text-danger">{{ $message }}</small> @enderror
    </div>

    <div class="col-md-6 mt-3">
      <label for="{{ $fieldPrefix }}-passport">Pasaporte</label>
      <input id="{{ $fieldPrefix }}-passport" class="form-control" name="passport" value="{{ old('passport', $user->passport) }}" required>
      @error('passport', $errorBag) <small class="text-danger">{{ $message }}</small> @enderror
    </div>

    <div class="col-md-6 mt-3">
      <label for="{{ $fieldPrefix }}-genealogy-tree-id">ID secundario del árbol</label>
      <input
        id="{{ $fieldPrefix }}-genealogy-tree-id"
        class="form-control"
        name="genealogy_tree_id"
        value="{{ old('genealogy_tree_id', $user->genealogy_tree_id) }}"
        placeholder="IDCliente del árbol, si es distinto al pasaporte"
      >
      <small class="form-text text-muted">Solo se usa si no existe un árbol con el número de pasaporte.</small>
      @error('genealogy_tree_id', $errorBag) <small class="text-danger">{{ $message }}</small> @enderror
    </div>

    <div class="col-md-6 mt-3">
      <label for="{{ $fieldPrefix }}-hs-id">HubSpot ID</label>
      <input id="{{ $fieldPrefix }}-hs-id" class="form-control" name="hs_id" value="{{ old('hs_id', $user->hs_id) }}">
      @error('hs_id', $errorBag) <small class="text-danger">{{ $message }}</small> @enderror
    </div>

    <div class="col-md-6 mt-3">
      <label for="{{ $fieldPrefix }}-tl-id">Teamleader ID</label>
      <input id="{{ $fieldPrefix }}-tl-id" class="form-control" name="tl_id" value="{{ old('tl_id', $user->tl_id) }}">
      @error('tl_id', $errorBag) <small class="text-danger">{{ $message }}</small> @enderror
    </div>

    <div class="col-md-6 mt-3">
      <label for="{{ $fieldPrefix }}-password">Nueva contraseña (opcional)</label>
      <input id="{{ $fieldPrefix }}-password" class="form-control" type="password" name="password" autocomplete="new-password">
      @error('password', $errorBag) <small class="text-danger">{{ $message }}</small> @enderror
    </div>

    <div class="col-md-6 mt-3">
      <label for="{{ $fieldPrefix }}-password-confirmation">Confirmar contraseña</label>
      <input id="{{ $fieldPrefix }}-password-confirmation" class="form-control" type="password" name="password_confirmation" autocomplete="new-password">
    </div>
  </div>

  @if($embeddedInCos)
    <details class="border rounded p-3 mt-4" @if($errors->getBag($errorBag)->hasAny(['roles', 'roles.*', 'permissions', 'permissions.*'])) open @endif>
      <summary class="font-weight-bold">Roles y permisos</summary>
      <div class="mt-3">
  @else
    <hr class="my-4">
  @endif
  <h5 class="mb-3">Roles</h5>
  <div class="row">
    @foreach($roles as $index => $role)
      @php($roleId = $fieldPrefix . '-role-' . $index)
      <div class="col-md-4">
        <label class="d-flex align-items-center" for="{{ $roleId }}">
          <input
            id="{{ $roleId }}"
            type="checkbox"
            name="roles[]"
            value="{{ $role->name }}"
            class="mr-2"
            @checked(in_array($role->name, (array) old('roles', $userRoleNames), true))
          >
          <span>{{ $role->name }}</span>
        </label>
      </div>
    @endforeach
  </div>

  <hr class="my-4">
  <h5 class="mb-2">Permisos directos</h5>
  <small class="text-muted d-block mb-3">
    Estos permisos se asignan directamente al usuario, además de los que hereda por sus roles.
  </small>

  <div class="row">
    @foreach($permissions as $index => $permission)
      @php($permissionId = $fieldPrefix . '-permission-' . $index)
      <div class="col-md-4">
        <label class="d-flex align-items-center" for="{{ $permissionId }}">
          <input
            id="{{ $permissionId }}"
            type="checkbox"
            name="permissions[]"
            value="{{ $permission->name }}"
            class="mr-2"
            @checked(in_array($permission->name, (array) old('permissions', $userPermissionNames), true))
          >
          <span>{{ $permission->name }}</span>
        </label>
      </div>
    @endforeach
  </div>
  @if($embeddedInCos)
      </div>
    </details>
  @endif

  <div class="mt-4">
    <button class="btn btn-primary" type="submit">Guardar cambios</button>
    @unless($embeddedInCos)
      <a class="btn btn-secondary" href="{{ route('crud.users.index') }}">Volver</a>
    @endunless
  </div>
</form>
