{{-- resources/views/tasks/admin/edit.blade.php --}}
@extends('adminlte::page')

@section('title', 'Editar Tarea #' . $task->id)

@section('content_header')
    <h1>
        <i class="fas fa-edit mr-2 text-warning"></i>
        Editar Tarea
        <small class="text-muted">#{{ $task->id }}</small>
    </h1>
@stop

@section('content')

<div class="row justify-content-center">
    <div class="col-md-7">
        <div class="card card-outline card-warning">
            <div class="card-header">
                <h3 class="card-title">Modificar datos de la tarea</h3>
                <div class="card-tools">
                    <span class="badge badge-secondary">
                        Creada: {{ $task->created_at->format('d/m/Y H:i') }}
                    </span>
                </div>
            </div>

            <form action="{{ route('tasks.admin.update', $task) }}" method="POST">
                @csrf
                @method('PUT')

                <div class="card-body">

                    @if($errors->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                @foreach($errors->all() as $e)
                                    <li>{{ $e }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="form-group">
                        <label class="font-weight-bold">
                            Asesor asignado <span class="text-danger">*</span>
                        </label>
                        <select name="user_id"
                                class="form-control @error('user_id') is-invalid @enderror">
                            @foreach($advisors as $id => $name)
                                <option value="{{ $id }}"
                                    {{ (old('user_id', $task->user_id) == $id) ? 'selected' : '' }}>
                                    {{ $name }}
                                </option>
                            @endforeach
                        </select>
                        @error('user_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label class="font-weight-bold">
                            Contacto <span class="text-danger">*</span>
                        </label>
                        <select name="contact_id"
                                class="form-control @error('contact_id') is-invalid @enderror">
                            @foreach($contacts as $id => $name)
                                <option value="{{ $id }}"
                                    {{ (old('contact_id', $task->contact_id) == $id) ? 'selected' : '' }}>
                                    {{ $name }}
                                </option>
                            @endforeach
                        </select>
                        @error('contact_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label class="font-weight-bold">
                            Título <span class="text-danger">*</span>
                        </label>
                        <input type="text" name="title"
                               class="form-control @error('title') is-invalid @enderror"
                               value="{{ old('title', $task->title) }}">
                        @error('title')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label class="font-weight-bold">Descripción</label>
                        <textarea name="description" rows="3"
                                  class="form-control @error('description') is-invalid @enderror">{{ old('description', $task->description) }}</textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-bold">
                                    Fecha límite <span class="text-danger">*</span>
                                </label>
                                <input type="date" name="due_date"
                                       class="form-control @error('due_date') is-invalid @enderror"
                                       value="{{ old('due_date', $task->due_date->toDateString()) }}">
                                @error('due_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-bold">Estado</label>
                                <select name="status"
                                        class="form-control @error('status') is-invalid @enderror">
                                    @foreach([
                                        'pending'     => 'Pendiente',
                                        'in_progress' => 'En curso',
                                        'completed'   => 'Completada',
                                        'canceled'    => 'Cancelada',
                                    ] as $val => $label)
                                        <option value="{{ $val }}"
                                            {{ old('status', $task->status) === $val ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('status')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    {{-- Info de resultado (solo lectura si ya fue gestionada) --}}
                    @if(!is_null($task->call_effective))
                        <div class="alert alert-light border mt-2">
                            <strong><i class="fas fa-info-circle mr-1"></i>Resultado registrado por el asesor:</strong>
                            <ul class="mb-0 mt-1 small">
                                <li>Llamada efectiva: {{ $task->call_effective ? '✅ Sí' : '❌ No' }}</li>
                                @if($task->reason_no_effective)
                                    <li>Motivo no efectiva: {{ $task->reason_no_effective }}</li>
                                @endif
                                @if(!is_null($task->interest_level))
                                    <li>Interés: {{ $task->interest_level ? '✅ Sí' : '❌ No' }}</li>
                                @endif
                                @if($task->reason_no_interest)
                                    <li>Sin interés: {{ $task->reason_no_interest }}</li>
                                @endif
                                @if($task->product_of_interest)
                                    <li>Producto: {{ $task->product_of_interest }}</li>
                                @endif
                                @if($task->follow_up_date)
                                    <li>Seguimiento: {{ $task->follow_up_date->format('d/m/Y') }}</li>
                                @endif
                            </ul>
                        </div>
                    @endif

                </div>

                <div class="card-footer d-flex justify-content-between">
                    <a href="{{ route('tasks.admin.index') }}"
                       class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left mr-1"></i>Cancelar
                    </a>
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-save mr-1"></i>Guardar cambios
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@stop

@section('css')
    <link rel="stylesheet" href="{{ asset('css/sefar.css') }}">
@stop
