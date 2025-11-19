@extends('adminlte::page')

@section('title', 'Números de Reporte')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h1 class="mb-0">
            <i class="fab fa-whatsapp text-success mr-2"></i>
            Gestión de Números para Reportes de WhatsApp
        </h1>
        <button class="btn btn-primary" data-toggle="modal" data-target="#modalAdd">
            <i class="fas fa-plus mr-1"></i> Añadir Número
        </button>
    </div>
@stop

@section('content')

    {{-- Mensajes de sesión --}}
    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle mr-2"></i>
            {{ session('success') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Cerrar">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif

    @if ($errors->any() && !old('id'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle mr-2"></i>
            Corrige los errores del formulario.
            <ul class="mb-0 mt-2 pl-3">
                @foreach ($errors->all() as $error)
                    <li class="small">{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="close" data-dismiss="alert" aria-label="Cerrar">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif

    <div class="card card-outline card-primary shadow">
        <div class="card-header">
            <h3 class="card-title">Números Registrados</h3>
            <div class="card-tools">
                <button class="btn btn-tool" data-card-widget="collapse"><i class="fas fa-minus"></i></button>
            </div>
        </div>
        <div class="card-body p-0">
            @if ($phoneNumbers->isEmpty())
                <div class="p-5 text-center text-muted">
                    <i class="fas fa-info-circle fa-2x mb-2"></i>
                    <p class="mb-0">No hay números de teléfono registrados para enviar reportes.</p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th style="width:45%">Número de Teléfono</th>
                                <th style="width:35%">Fecha de Creación</th>
                                <th class="text-right" style="width:20%">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($phoneNumbers as $number)
                                <tr>
                                    <td class="align-middle">
                                        <i class="fab fa-whatsapp text-success mr-2"></i>
                                        <span class="font-weight-bold">{{ $number->phone_number }}</span>
                                    </td>
                                    <td class="align-middle text-muted">
                                        <i class="far fa-clock mr-1"></i>
                                        {{ $number->created_at->format('d/m/Y H:i') }}
                                    </td>
                                    <td class="align-middle text-right">
                                        <button
                                            class="btn btn-sm btn-outline-primary mr-2"
                                            data-toggle="modal"
                                            data-target="#modalEdit"
                                            data-id="{{ $number->id }}"
                                            data-phone="{{ $number->phone_number }}"
                                        >
                                            <i class="fas fa-edit"></i> Editar
                                        </button>

                                        <form
                                            action="{{ route('whatsapp.numbers.destroy', $number) }}"
                                            method="POST"
                                            class="d-inline"
                                            onsubmit="return confirm('¿Estás seguro de que deseas eliminar este número?');"
                                        >
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                <i class="fas fa-trash-alt"></i> Eliminar
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
        @if (!$phoneNumbers->isEmpty() && method_exists($phoneNumbers, 'links'))
            <div class="card-footer">
                {{ $phoneNumbers->links() }}
            </div>
        @endif
    </div>

    {{-- Modal: Añadir Número --}}
    <div class="modal fade" id="modalAdd" tabindex="-1" role="dialog" aria-labelledby="modalAddLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content shadow">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="modalAddLabel"><i class="fas fa-plus mr-2"></i>Añadir Número de Reporte</h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form id="add-form" method="POST" action="{{ route('whatsapp.numbers.store') }}" novalidate>
                    @csrf
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="new_phone_number" class="font-weight-semibold">Número de Teléfono</label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fab fa-whatsapp text-success"></i></span>
                                </div>
                                <input
                                    type="text"
                                    name="phone_number"
                                    id="new_phone_number"
                                    class="form-control @error('phone_number') is-invalid @enderror"
                                    placeholder="+584121234567"
                                    value="{{ old('phone_number') }}"
                                    required
                                    pattern="^\+?[1-9]\d{7,14}$"
                                    autocomplete="off"
                                >
                                @error('phone_number')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>
                            <small class="form-text text-muted">
                                Formato sugerido E.164: empieza con +, sin espacios ni guiones. Ej: +584121234567
                            </small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Modal: Editar Número --}}
    <div class="modal fade" id="modalEdit" tabindex="-1" role="dialog" aria-labelledby="modalEditLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content shadow">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title" id="modalEditLabel"><i class="fas fa-edit mr-2"></i>Editar Número de Reporte</h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form id="edit-form" method="POST" action="">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="id" id="edit_id" value="{{ old('id') }}">
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="edit_phone_number" class="font-weight-semibold">Número de Teléfono</label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fab fa-whatsapp text-success"></i></span>
                                </div>
                                <input
                                    type="text"
                                    name="phone_number"
                                    id="edit_phone_number"
                                    class="form-control @error('phone_number') is-invalid @enderror"
                                    placeholder="+584121234567"
                                    value="{{ old('phone_number') }}"
                                    required
                                    pattern="^\+?[1-9]\d{7,14}$"
                                    autocomplete="off"
                                >
                                @error('phone_number')
                                    @if (old('id'))
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @endif
                                @enderror
                            </div>
                            <small class="form-text text-muted">
                                Mantén el formato E.164. Ej: +584121234567
                            </small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-info"><i class="fas fa-sync-alt mr-1"></i> Actualizar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

@stop

@section('css')
    <!-- Asegúrate de tener Font Awesome si usas íconos -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('css/sefar.css') }}">
    {{-- AdminLTE ya incluye Bootstrap y Font Awesome --}}
    <style>
        .font-weight-semibold { font-weight: 600; }
        .table td, .table th { vertical-align: middle; }
        .card .table thead th { border-bottom: 1px solid rgba(0,0,0,.1); }
    </style>
@stop

@section('js')
<script>
    // Rellenar modal de edición con datos del botón
    $('#modalEdit').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget);
        var id     = button.data('id');
        var phone  = button.data('phone');

        var modal = $(this);
        modal.find('#edit_id').val(id);
        modal.find('#edit_phone_number').val(phone);
        modal.find('#edit-form').attr('action', '{{ url('whatsapp/numbers') }}/' + id);
    });

    // Reabrir el modal correspondiente si hubo errores de validación
    @if ($errors->any())
        @if (old('id'))
            $(function(){
                // Hubo error en edición
                $('#modalEdit').modal('show');
                $('#edit-form').attr('action', '{{ url('whatsapp/numbers') }}/' + "{{ old('id') }}");
            });
        @else
            $(function(){
                // Hubo error en creación
                $('#modalAdd').modal('show');
            });
        @endif
    @endif

    // Auto-cerrar alerts
    setTimeout(function(){
        $('.alert').alert('close');
    }, 5000);
</script>
@stop
