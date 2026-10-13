@extends('adminlte::page')

@section('title', 'Organigrama del equipo')

@section('content_header')
    <a href="{{ route('kanban.index') }}" class="text-muted small"><i class="fas fa-arrow-left mr-1"></i>Tableros</a>
    <h1 class="mt-2"><i class="fas fa-sitemap text-primary mr-2"></i>Organigrama</h1>
    <p class="text-muted mb-0">Define quién supervisa a quién. Las personas verán sus tarjetas y las de todos sus subordinados.</p>
@stop

@section('content')
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="close" data-dismiss="alert"><span>&times;</span></button></div>
    @endif

    <div class="row">
        <div class="col-12 col-xl-5">
            <div class="card card-outline card-primary">
                <div class="card-header"><h3 class="card-title">Añadir relación</h3></div>
                <form method="POST" action="{{ route('kanban.org-chart.store') }}">
                    @csrf
                    <div class="card-body">
                        <div class="form-group">
                            <label for="manager-user">Supervisor</label>
                            <select class="form-control select2" id="manager-user" name="manager_user_id" required>
                                <option value="">Selecciona una persona</option>
                                @foreach($users as $user)<option value="{{ $user->id }}" @selected(old('manager_user_id') == $user->id)>{{ $user->name }} · {{ $user->email }}</option>@endforeach
                            </select>
                            @error('manager_user_id')<small class="text-danger">{{ $message }}</small>@enderror
                        </div>
                        <div class="form-group mb-0">
                            <label for="subordinate-user">Subordinado</label>
                            <select class="form-control select2" id="subordinate-user" name="subordinate_user_id" required>
                                <option value="">Selecciona una persona</option>
                                @foreach($users as $user)<option value="{{ $user->id }}" @selected(old('subordinate_user_id') == $user->id)>{{ $user->name }} · {{ $user->email }}</option>@endforeach
                            </select>
                            @error('subordinate_user_id')<small class="text-danger">{{ $message }}</small>@enderror
                        </div>
                        <p class="text-muted small mt-3 mb-0">Cada persona puede tener un supervisor directo. Si una persona cambia de supervisor, guarda la nueva relación aquí.</p>
                    </div>
                    <div class="card-footer"><button class="btn btn-primary"><i class="fas fa-save mr-1"></i>Guardar relación</button></div>
                </form>
            </div>
        </div>

        <div class="col-12 col-xl-7">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Relaciones actuales</h3><span class="badge badge-light ml-2">{{ $reportingLines->count() }}</span></div>
                <div class="card-body p-0">
                    @if($reportingLines->isEmpty())
                        <div class="text-center text-muted py-5 px-3">
                            <i class="fas fa-sitemap fa-2x mb-2"></i>
                            <p class="mb-0">Todavía no hay relaciones cargadas. Hasta entonces, cada usuario solo verá sus propios tableros y tarjetas.</p>
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead><tr><th>Supervisor</th><th></th><th>Subordinado</th><th class="text-right">Acción</th></tr></thead>
                                <tbody>
                                    @foreach($reportingLines as $line)
                                        <tr>
                                            <td>{{ $line->manager?->name ?? 'Usuario eliminado' }}</td>
                                            <td class="text-center text-muted"><i class="fas fa-long-arrow-alt-right"></i></td>
                                            <td>{{ $line->subordinate?->name ?? 'Usuario eliminado' }}</td>
                                            <td class="text-right">
                                                <form method="POST" action="{{ route('kanban.org-chart.destroy', $line) }}" onsubmit="return confirm('¿Eliminar esta relación?')">
                                                    @csrf @method('DELETE')
                                                    <button class="btn btn-sm btn-outline-danger" title="Eliminar relación"><i class="far fa-trash-alt"></i></button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@stop

@section('css')
    <link rel="stylesheet" href="{{ asset('vendor/select2/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/select2-bootstrap4-theme/select2-bootstrap4.min.css') }}">
@stop

@section('js')
    <script src="{{ asset('vendor/select2/js/select2.full.min.js') }}"></script>
    <script>
        $(function () {
            $('.select2').select2({ theme: 'bootstrap4', width: '100%', placeholder: 'Selecciona una persona' });
        });
    </script>
@stop
