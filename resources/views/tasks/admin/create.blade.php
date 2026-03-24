@extends('adminlte::page')

@section('title', 'Nueva Tarea')

@section('content_header')
    <h1><i class="fas fa-plus mr-2"></i>Nueva Tarea Manual</h1>
@stop

@section('content')

<div class="row justify-content-center">
    <div class="col-md-7">
        <div class="card card-outline card-primary">
            <div class="card-header">
                <h3 class="card-title">Datos de la tarea</h3>
            </div>
            <form action="{{ route('tasks.admin.store') }}" method="POST">
                @csrf
                <div class="card-body">

                    <div class="form-group">
                        <label>Asesor asignado <span class="text-danger">*</span></label>
                        <select name="user_id"
                                class="form-control @error('user_id') is-invalid @enderror">
                            <option value="">— Seleccione asesor —</option>
                            @foreach($advisors as $id => $name)
                                <option value="{{ $id }}" {{ old('user_id') == $id ? 'selected' : '' }}>
                                    {{ $name }}
                                </option>
                            @endforeach
                        </select>
                        @error('user_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label>Contacto <span class="text-danger">*</span></label>
                        <select name="contact_id"
                                class="form-control @error('contact_id') is-invalid @enderror">
                            <option value="">— Seleccione contacto —</option>
                            @foreach($contacts as $id => $name)
                                <option value="{{ $id }}" {{ old('contact_id') == $id ? 'selected' : '' }}>
                                    {{ $name }}
                                </option>
                            @endforeach
                        </select>
                        @error('contact_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label>Título <span class="text-danger">*</span></label>
                        <input type="text" name="title"
                               class="form-control @error('title') is-invalid @enderror"
                               value="{{ old('title') }}"
                               placeholder="Comunicarse con el cliente...">
                        @error('title')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label>Descripción</label>
                        <textarea name="description" rows="3"
                                  class="form-control @error('description') is-invalid @enderror"
                                  placeholder="Notas adicionales...">{{ old('description') }}</textarea>
                    </div>

                    <div class="form-group">
                        <label>Fecha límite <span class="text-danger">*</span></label>
                        <input type="date" name="due_date"
                               class="form-control @error('due_date') is-invalid @enderror"
                               value="{{ old('due_date', today()->toDateString()) }}">
                        @error('due_date')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                </div>
                <div class="card-footer d-flex justify-content-between">
                    <a href="{{ route('tasks.admin.index') }}" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left mr-1"></i>Cancelar
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save mr-1"></i>Crear tarea
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
