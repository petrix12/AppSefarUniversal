<div class="card card-outline card-primary">
    <div class="card-header">
        <h3 class="card-title mb-0">
            <i class="fas fa-list mr-1"></i> Tokens activos
        </h3>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Usuario</th>
                        <th>Permisos</th>
                        <th>Ultimo uso</th>
                        <th>Creado</th>
                        <th class="text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($tokens as $token)
                        @php
                            $abilities = is_array($token->abilities)
                                ? $token->abilities
                                : (json_decode($token->abilities ?? '[]', true) ?: []);
                        @endphp
                        <tr>
                            <td>
                                <strong>{{ $token->name }}</strong>
                                <div class="small text-muted">ID {{ $token->id }}</div>
                            </td>
                            <td>
                                @if($token->tokenable)
                                    {{ $token->tokenable->name }}
                                    <div class="small text-muted">{{ $token->tokenable->email }}</div>
                                @else
                                    <span class="text-muted">Usuario no disponible</span>
                                @endif
                            </td>
                            <td>
                                @forelse($abilities as $ability)
                                    <span class="badge badge-secondary mr-1">{{ $ability }}</span>
                                @empty
                                    <span class="badge badge-light">Sin permisos</span>
                                @endforelse
                            </td>
                            <td>{{ optional($token->last_used_at)->format('d/m/Y H:i') ?? 'Nunca' }}</td>
                            <td>{{ optional($token->created_at)->format('d/m/Y H:i') }}</td>
                            <td class="text-right">
                                <form method="POST" action="{{ route('admin.integrations.tokens.destroy', $token) }}" class="d-inline" onsubmit="return confirm('Revocar este token?');">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted p-4">No hay tokens activos.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($tokens->hasPages())
        <div class="card-footer">
            {{ $tokens->links() }}
        </div>
    @endif
</div>
