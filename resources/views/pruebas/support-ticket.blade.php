@extends('adminlte::page')

@section('title', 'Prueba de ticket de soporte')

@section('content_header')
    <h1>Prueba de ticket de soporte</h1>
@stop

@section('content')
    @if(session('support_success'))
        <div class="alert alert-success">
            <strong>{{ session('support_success') }}</strong>
            @if(session('support_ticket_id'))
                <div>Ticket HubSpot: {{ session('support_ticket_id') }}</div>
            @endif
            @if(session('support_owner_email'))
                <div>Correo del propietario: {{ session('support_owner_email') }}</div>
            @endif
            @if(session('support_inbox_fallback'))
                <div>Fallback usado: correo enviado a info@sefarvzla.com</div>
            @endif
            @if(session('support_ticket_error'))
                <div style="margin-top: .35rem; font-size: .85rem;">
                    Diagnostico HubSpot: {{ session('support_ticket_error') }}
                </div>
            @endif
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="card">
        <div class="card-body">
            <div class="alert alert-warning">
                Esta prueba crea un ticket real en HubSpot y envia correos reales con marca de PRUEBA.
            </div>

            <form method="POST" action="{{ route('admin.support-ticket-test.store') }}">
                @csrf

                <div class="form-group">
                    <label for="select-client">Cliente de prueba</label>
                    @php
                        $selectedClient = old('client_id') ? \App\Models\User::find(old('client_id')) : null;
                    @endphp
                    <select
                        id="select-client"
                        name="client_id"
                        class="form-control @error('client_id') is-invalid @enderror"
                        style="width: 100%;"
                        required
                    >
                        <option value=""></option>
                        @if($selectedClient)
                            <option value="{{ $selectedClient->id }}" selected>
                                {{ trim($selectedClient->name . ' - ' . $selectedClient->email . ($selectedClient->passport ? ' - ' . $selectedClient->passport : '')) }}
                            </option>
                        @endif
                    </select>
                    @error('client_id')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="request_description">Solicitud de prueba</label>
                    <textarea
                        id="request_description"
                        name="request_description"
                        class="form-control"
                        rows="7"
                        minlength="15"
                        maxlength="3000"
                        required
                        placeholder="Describe la solicitud de prueba que quieres enviar..."
                    >{{ old('request_description', 'Prueba administrativa del flujo de solicitud de soporte desde App Sefar.') }}</textarea>
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-ticket-alt mr-1"></i>
                    Crear ticket de prueba
                </button>
            </form>
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
            $('#select-client').select2({
                theme: 'bootstrap-4',
                language: 'es',
                placeholder: 'Buscar cliente por nombre, email o pasaporte...',
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

                        return {
                            results: data.results,
                            pagination: { more: data.pagination.more }
                        };
                    },
                    cache: true
                },
            });
        });
    </script>
@stop
