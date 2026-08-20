@extends('adminlte::page')

@section('title', 'Tokens API')

@section('content_header')
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center">
        <h1>Tokens API</h1>
        @include('admin.integrations._tabs')
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
                        <i class="fas fa-plus-circle mr-1"></i> Crear token API
                    </h3>
                </div>
                <form method="POST" action="{{ route('admin.integrations.api-tokens.store') }}">
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
                            <input type="text" name="name" id="name" class="form-control" maxlength="80" value="{{ old('name') }}" placeholder="Integracion interna" required>
                        </div>

                        <div class="form-group mb-0">
                            <label>Permisos</label>
                            @foreach($abilities as $ability => $label)
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" name="abilities[]" value="{{ $ability }}" id="ability_{{ $ability }}" class="custom-control-input" @checked(in_array($ability, old('abilities', ['read'])))>
                                    <label class="custom-control-label" for="ability_{{ $ability }}">{{ $label }}</label>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="card-footer text-right">
                        <button class="btn btn-primary">
                            <i class="fas fa-save mr-1"></i> Crear token
                        </button>
                    </div>
                </form>
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
            color: var(--sefar-muted);
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
