{{-- resources/views/tasks/admin/create.blade.php --}}
@extends('adminlte::page')

@section('title', 'Nueva Tarea')

@section('content_header')
    <h1><i class="fas fa-plus mr-2"></i>Nueva Tarea Manual</h1>
@stop

@section('content')

<div class="row justify-content-center">
    <div class="col-md-7">
        <div class="card card-outline card-primary shadow-sm">
            <div class="card-header">
                <h3 class="card-title">Datos de la tarea</h3>
            </div>
            <form action="{{ route('tasks.admin.store') }}" method="POST">
                @csrf
                <div class="card-body">

                    {{-- ───── Asesor (Select2 local) ───── --}}
                    <div class="form-group">
                        <label class="font-weight-bold">
                            <i class="fas fa-user-tie text-muted mr-1"></i>
                            Asesor asignado <span class="text-danger">*</span>
                        </label>
                        <div class="select2-wrapper @error('user_id') has-error @enderror">
                            <span class="select2-prefix-icon">
                                <i class="fas fa-user"></i>
                            </span>
                            <select name="user_id"
                                    id="select-advisor"
                                    class="form-control @error('user_id') is-invalid @enderror"
                                    style="width: 100%;">
                                <option value="">— Seleccione asesor —</option>
                                @foreach($advisors as $id => $name)
                                    <option value="{{ $id }}" {{ old('user_id') == $id ? 'selected' : '' }}>
                                        {{ $name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        @error('user_id')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- ───── Contacto (Select2 AJAX) ───── --}}
                    <div class="form-group">
                        <label class="font-weight-bold">
                            <i class="fas fa-address-book text-muted mr-1"></i>
                            Contacto
                        </label>
                        <div class="select2-wrapper @error('contact_id') has-error @enderror">
                            <span class="select2-prefix-icon">
                                <i class="fas fa-search"></i>
                            </span>
                            <select name="contact_id"
                                    id="select-contact"
                                    class="form-control @error('contact_id') is-invalid @enderror"
                                    style="width: 100%;">
                                <option value="">— Sin contacto (tarea general / HubSpot / lead) —</option>
                                @if(old('contact_id') && isset($contacts[old('contact_id')]))
                                    <option value="{{ old('contact_id') }}" selected>
                                        {{ $contacts[old('contact_id')] }}
                                    </option>
                                @endif
                            </select>
                        </div>
                        @error('contact_id')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- ───── Título ───── --}}
                    <div class="form-group">
                        <label class="font-weight-bold">
                            <i class="fas fa-heading text-muted mr-1"></i>
                            Título <span class="text-danger">*</span>
                        </label>
                        <input type="text" name="title"
                               class="form-control @error('title') is-invalid @enderror"
                               value="{{ old('title') }}"
                               placeholder="Comunicarse con el cliente...">
                        @error('title')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- ───── Descripción ───── --}}
                    <div class="form-group">
                        <label class="font-weight-bold">
                            <i class="fas fa-align-left text-muted mr-1"></i>
                            Descripción
                        </label>
                        <textarea name="description" rows="3"
                                  class="form-control @error('description') is-invalid @enderror"
                                  placeholder="Notas adicionales...">{{ old('description') }}</textarea>
                    </div>

                    {{-- ───── Fecha límite ───── --}}
                    <div class="form-group">
                        <label class="font-weight-bold">
                            <i class="fas fa-calendar-alt text-muted mr-1"></i>
                            Fecha límite <span class="text-danger">*</span>
                        </label>
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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-4-theme@1.0.0/dist/select2-bootstrap-4.min.css">
    @include('tasks.admin._select2_styles')
@stop

@section('js')
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/i18n/es.js"></script>

    <script>
        $(document).ready(function () {

            // ─── Asesor: Select2 local ───
            $('#select-advisor').select2({
                theme: 'bootstrap-4',
                language: 'es',
                placeholder: 'Buscar asesor...',
                allowClear: true,
            });

            // ─── Contacto: Select2 AJAX ───
            $('#select-contact').select2({
                theme: 'bootstrap-4',
                language: 'es',
                placeholder: 'Escriba para buscar contacto...',
                allowClear: true,
                minimumInputLength: 2,
                ajax: {
                    url: '{{ route("api.contacts.search") }}',
                    dataType: 'json',
                    delay: 300,
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    data: params => ({ q: params.term, page: params.page || 1 }),
                    processResults: (data, params) => {
                        params.page = params.page || 1;
                        return { results: data.results, pagination: { more: data.pagination.more } };
                    },
                    cache: true
                },
            });

        });
    </script>
@stop
