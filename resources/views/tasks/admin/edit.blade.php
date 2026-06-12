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
        <div class="card card-outline card-warning shadow-sm">
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
                                    style="width:100%;">
                                <option value="">— Seleccione asesor —</option>
                                @foreach($advisors as $id => $name)
                                    <option value="{{ $id }}"
                                        {{ (old('user_id', $task->user_id) == $id) ? 'selected' : '' }}>
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
                                    style="width:100%;">
                                <option value="">— Sin contacto —</option>
                                @php
                                    $currentContactId   = old('contact_id', $task->contact_id);
                                    $currentContactName = $task->contact
                                        ? $task->contact->name . ' — ' . $task->contact->email
                                        : null;
                                @endphp
                                @if($currentContactId && $currentContactName)
                                    <option value="{{ $currentContactId }}" selected>
                                        {{ $currentContactName }}
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
                               value="{{ old('title', $task->title) }}">
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
                                  class="form-control @error('description') is-invalid @enderror">{{ old('description', $task->description) }}</textarea>
                    </div>

                    <div class="row">
                        {{-- ───── Fecha límite ───── --}}
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-bold">
                                    <i class="fas fa-calendar-alt text-muted mr-1"></i>
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

                        {{-- ───── Estado ───── --}}
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-bold">
                                    <i class="fas fa-flag text-muted mr-1"></i>
                                    Estado
                                </label>
                                <div class="select2-wrapper @error('status') has-error @enderror">
                                    <span class="select2-prefix-icon">
                                        <i class="fas fa-circle status-dot
                                            {{ match(old('status', $task->status)) {
                                                'pending'     => 'text-secondary',
                                                'in_progress' => 'text-primary',
                                                'completed'   => 'text-success',
                                                'canceled'    => 'text-danger',
                                                default       => 'text-muted'
                                            } }}">
                                        </i>
                                    </span>
                                    <select name="status"
                                            id="select-status"
                                            class="form-control @error('status') is-invalid @enderror"
                                            style="width:100%;">
                                        @foreach([
                                            'pending'     => ['label' => 'Pendiente',  'icon' => '⏳'],
                                            'in_progress' => ['label' => 'En curso',   'icon' => '🔄'],
                                            'completed'   => ['label' => 'Completada', 'icon' => '✅'],
                                            'canceled'    => ['label' => 'Cancelada',  'icon' => '❌'],
                                        ] as $val => $meta)
                                            <option value="{{ $val }}"
                                                {{ old('status', $task->status) === $val ? 'selected' : '' }}>
                                                {{ $meta['icon'] }} {{ $meta['label'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                @error('status')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    {{-- ───── Info de resultado (solo lectura) ───── --}}
                    <div class="card card-outline card-info mt-3">
                        <div class="card-header py-2">
                            <h3 class="card-title">
                                <i class="fas fa-chart-line mr-1"></i>Seguimiento de venta
                            </h3>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label class="font-weight-bold d-block">Vias de contacto</label>
                                @php($selectedMethods = old('contact_methods', $task->contact_methods ?? []))
                                @foreach(\App\Models\Task::contactMethodOptions() as $value => $meta)
                                    <div class="custom-control custom-checkbox custom-control-inline mb-2">
                                        <input type="checkbox"
                                               name="contact_methods[]"
                                               value="{{ $value }}"
                                               id="method-{{ $value }}"
                                               class="custom-control-input"
                                               {{ in_array($value, $selectedMethods ?? [], true) ? 'checked' : '' }}>
                                        <label class="custom-control-label" for="method-{{ $value }}">
                                            <i class="fas fa-{{ $meta['icon'] }} mr-1"></i>{{ $meta['label'] }}
                                        </label>
                                    </div>
                                @endforeach
                            </div>

                            <div class="form-group">
                                <label class="font-weight-bold d-block">Cliente respondio</label>
                                @php($respondedValue = old('customer_responded', is_null($task->customer_responded) ? null : ($task->customer_responded ? '1' : '0')))
                                <div class="custom-control custom-radio custom-control-inline">
                                    <input type="radio" id="customer-responded-yes" name="customer_responded" value="1" class="custom-control-input" {{ $respondedValue === '1' ? 'checked' : '' }}>
                                    <label class="custom-control-label" for="customer-responded-yes">Si</label>
                                </div>
                                <div class="custom-control custom-radio custom-control-inline">
                                    <input type="radio" id="customer-responded-no" name="customer_responded" value="0" class="custom-control-input" {{ $respondedValue === '0' ? 'checked' : '' }}>
                                    <label class="custom-control-label" for="customer-responded-no">No / esperando respuesta</label>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="font-weight-bold">Evidencia si respondio</label>
                                <textarea name="contact_proof" rows="3" maxlength="2000" class="form-control @error('contact_proof') is-invalid @enderror" placeholder="Enlace al chat, resumen verificable o soporte de la respuesta">{{ old('contact_proof', $task->contact_proof) }}</textarea>
                                <small class="form-text text-muted">Obligatoria si marcas que el cliente respondio.</small>
                                @error('contact_proof')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="form-group">
                                <label class="font-weight-bold">Estatus de la venta</label>
                                <select name="sale_status" class="form-control @error('sale_status') is-invalid @enderror">
                                    <option value="">Sin estatus</option>
                                    @foreach(\App\Models\Task::saleStatusOptions() as $value => $label)
                                        <option value="{{ $value }}" {{ old('sale_status', $task->sale_status) === $value ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('sale_status')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="form-group mb-0">
                                <label class="font-weight-bold d-block">Etiquetas</label>
                                @php($selectedTags = old('sales_tags', $task->sales_tags ?? []))
                                @foreach(\App\Models\Task::salesTagOptions() as $value => $meta)
                                    <div class="custom-control custom-checkbox custom-control-inline mb-2">
                                        <input type="checkbox"
                                               name="sales_tags[]"
                                               value="{{ $value }}"
                                               id="tag-{{ $value }}"
                                               class="custom-control-input"
                                               {{ in_array($value, $selectedTags ?? [], true) ? 'checked' : '' }}>
                                        <label class="custom-control-label" for="tag-{{ $value }}">
                                            <span class="badge badge-{{ $meta['class'] }}">{{ $meta['label'] }}</span>
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    @if(!is_null($task->call_effective))
                        <div class="alert alert-light border mt-2">
                            <strong><i class="fas fa-info-circle mr-1"></i>Resultado registrado por el asesor:</strong>
                            <ul class="mb-0 mt-1 small">
                                <li>Vias de contacto: {{ implode(', ', $task->contactMethodLabels()) ?: '-' }}</li>
                                <li>Cliente respondio: {{ is_null($task->customer_responded) ? 'Sin registrar' : ($task->customer_responded ? 'Si' : 'No / esperando respuesta') }}</li>
                                <li>Gestion efectiva: {{ $task->call_effective ? 'Si' : 'No' }}</li>
                                @if($task->reason_no_effective)
                                    <li>Motivo no efectiva: {{ $task->reason_no_effective }}</li>
                                @endif
                                @if($task->contact_proof)
                                    <li>Evidencia: {{ $task->contact_proof }}</li>
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
                    <a href="{{ route('tasks.admin.index') }}" class="btn btn-outline-secondary">
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

            // ─── Estado: Select2 local ───
            $('#select-status').select2({
                theme: 'bootstrap-4',
                language: 'es',
                minimumResultsForSearch: Infinity, // sin buscador (pocas opciones)
                allowClear: false,
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
