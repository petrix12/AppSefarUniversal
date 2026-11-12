@if(auth()->user()?->hasRole('Administrador') && ! auth()->user()->hasRole('Cliente'))
    <div class="tooltip-container {{ ($mobile ?? false) ? 'flex-1' : '' }}">
        <form action="{{ route('crud.users.destroy', $user) }}" method="POST" class="m-0"
              data-user-delete="{{ $user->id }}"
              onsubmit="return confirm('¿Eliminar definitivamente este usuario de la App? Esta acción no se puede deshacer.');">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-danger {{ ($mobile ?? false) ? 'w-full' : '' }}"
                    aria-label="Eliminar usuario {{ $user->name }}">
                <i class="fas fa-trash fa-fw" aria-hidden="true"></i>
            </button>
        </form>
        <span class="tooltip-text">Eliminar usuario</span>
    </div>
@endif
