@extends('adminlte::page')

@section('title', 'MCP privado')

@section('content_header')
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center">
        <h1>MCP privado</h1>
        <div class="btn-group mt-2 mt-md-0">
            <a href="{{ route('admin.integrations.api-tokens.index') }}" class="btn btn-outline-primary">
                <i class="fas fa-key mr-1"></i> Tokens API
            </a>
            <a href="{{ route('admin.integrations.mcp.index') }}" class="btn btn-primary">
                <i class="fas fa-shield-alt mr-1"></i> MCP privado
            </a>
        </div>
    </div>
@stop

@section('content')
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    @include('admin.integrations._created-token')

    <div class="row">
        <div class="col-lg-4">
            <div class="card card-outline card-primary">
                <div class="card-header">
                    <h3 class="card-title mb-0">
                        <i class="fas fa-plus-circle mr-1"></i> Crear token MCP
                    </h3>
                </div>
                <form method="POST" action="{{ route('admin.integrations.mcp.tokens.store') }}">
                    @csrf
                    <div class="card-body">
                        <div class="form-group">
                            <label for="user_id">Usuario interno</label>
                            <select name="user_id" id="user_id" class="form-control" required>
                                <option value="">Seleccionar usuario</option>
                                @foreach($users as $user)
                                    <option value="{{ $user->id }}" @selected(old('user_id') == $user->id)>
                                        {{ $user->name }} - {{ $user->email }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="name">Nombre del token</label>
                            <input type="text" name="name" id="name" class="form-control" maxlength="80" value="{{ old('name') }}" placeholder="MCP privado - integracion">
                        </div>

                        <div class="callout callout-info mb-0">
                            <strong>Permiso:</strong> <code>mcp:read</code>
                            <div class="small mt-1">Solo usuarios internos. Los usuarios con rol Cliente quedan excluidos.</div>
                        </div>
                    </div>
                    <div class="card-footer text-right">
                        <button class="btn btn-primary">
                            <i class="fas fa-save mr-1"></i> Crear token MCP
                        </button>
                    </div>
                </form>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title mb-0">
                        <i class="fas fa-link mr-1"></i> Endpoint
                    </h3>
                </div>
                <div class="card-body">
                    <div class="integration-endpoint mb-2">{{ url('/api/mcp/v1') }}</div>
                    <div class="small text-muted">Usar como Bearer Token en las rutas privadas MCP.</div>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            @include('admin.integrations._tokens-table', ['tokens' => $tokens])
        </div>
    </div>
@stop

@section('css')
    <style>
        .integration-endpoint {
            border: 1px solid var(--sefar-border);
            border-radius: var(--sefar-radius);
            background: var(--sefar-primary-soft);
            color: var(--sefar-text);
            font-family: monospace;
            padding: .75rem;
            word-break: break-word;
        }
    </style>
@stop

@section('js')
    <script>
        document.querySelectorAll('[data-copy-token]').forEach(function (button) {
            button.addEventListener('click', function () {
                const input = document.querySelector('[data-created-token]');
                if (! input) return;

                input.select();
                navigator.clipboard?.writeText(input.value);
            });
        });
    </script>
@stop
