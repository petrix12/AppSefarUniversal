@extends('adminlte::page')

@section('title', 'Correo de la app')

@section('content_header')
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center">
        <h1>Correo de la app</h1>
        @include('admin.integrations._tabs')
    </div>
@stop

@section('content')
    @can('administrador')<p><a class="btn btn-outline-primary" href="{{ route('marketing.templates.index') }}"><i class="fas fa-magic mr-1"></i>Generar y editar plantillas con IA</a></p>@endcan
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
    @unless($schemaReady)<div class="alert alert-warning">Aplica la migración de correo de la app para habilitar el selector y el historial.</div>@endunless

    <div class="row">
        <div class="col-lg-6">
            <div class="card card-outline card-primary">
                <div class="card-header"><h2 class="card-title">Proveedor de los correos automáticos</h2></div>
                <div class="card-body">
                    <p>Se aplica a registros, recuperación de contraseña, comprobantes y notificaciones. Las campañas conservan su configuración.</p>
                    <p>Activo: <strong>{{ $provider === 'ses' ? 'Amazon SES' : 'Proveedor habitual ('.strtoupper($originalMailer).')' }}</strong></p>
                    <form method="POST" action="{{ route('admin.integrations.app-mail.update') }}">
                        @csrf @method('PUT')
                        <div class="form-group">
                            <label for="app-mail-provider">Enviar los correos con</label>
                            <select class="form-control" id="app-mail-provider" name="provider" required @disabled(!$schemaReady)>
                                <option value="default" @selected(old('provider', $provider) === 'default')>Proveedor habitual ({{ strtoupper($originalMailer) }})</option>
                                <option value="ses" @selected(old('provider', $provider) === 'ses') @disabled(!$sesReady && $provider !== 'ses')>Amazon SES</option>
                            </select>
                        </div>
                        @if($provider !== 'ses')
                            <div class="custom-control custom-checkbox mb-3">
                                <input type="checkbox" class="custom-control-input" id="received-mail-test" name="received_test" value="1" @checked(old('received_test'))>
                                <label class="custom-control-label" for="received-mail-test">Para activar SES, confirmo que recibí su correo de prueba.</label>
                            </div>
                        @endif
                        @if($testProof)
                            <p class="text-success">Prueba SES aceptada para {{ $testProof['recipient'] }}. Puedes activarlo al confirmar su recepción.</p>
                        @elseif($provider !== 'ses')
                            <p class="text-muted">Antes de activar SES, envía una prueba. Puedes volver al proveedor habitual en cualquier momento.</p>
                        @endif
                        <button class="btn btn-primary" type="submit" @disabled(!$schemaReady)>Guardar proveedor</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card card-outline card-secondary">
                <div class="card-header"><h2 class="card-title">Probar un proveedor</h2></div>
                <div class="card-body">
                    <p>SES: <strong>{{ $sesReady ? 'Configurado' : 'Pendiente de configuración en el servidor' }}</strong></p>
                    @if($sesFrom)<p>Remitente SES: {{ $sesFrom }} · {{ $sesRegion }}</p>@endif
                    <form method="POST" action="{{ route('admin.integrations.app-mail.test') }}">
                        @csrf
                        <div class="form-group">
                            <label for="test-mail-provider">Proveedor de la prueba</label>
                            <select class="form-control" id="test-mail-provider" name="provider" required>
                                <option value="ses" @disabled(!$sesReady)>Amazon SES</option>
                                <option value="default" @selected(!$sesReady)>Proveedor habitual ({{ strtoupper($originalMailer) }})</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="test-mail-recipient">Enviar la prueba a</label>
                            <input type="email" class="form-control" id="test-mail-recipient" name="recipient" value="{{ old('recipient', auth()->user()->email) }}" maxlength="254" required>
                        </div>
                        <button class="btn btn-outline-primary" type="submit" @disabled(!$schemaReady)>Enviar correo de prueba</button>
                    </form>
                    <p class="text-muted mt-3 mb-0">La prueba usa el proveedor elegido sin cambiar el que utiliza la app.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h2 class="card-title">Direcciones con envíos bloqueados</h2></div>
        <div class="card-body">
            <p>Los rebotes permanentes y las quejas por spam suspenden los envíos a esa dirección. El representado conserva el acceso a la app.</p>
            <div class="table-responsive"><table class="table table-hover">
                <thead><tr><th>Correo</th><th>Motivo</th><th>Acción</th></tr></thead>
                <tbody>@forelse($suppressions as $suppression)
                    <tr><td>{{ $suppression->email }}</td><td>{{ \App\Models\EmailSuppression::REASONS[$suppression->reason] ?? $suppression->reason }}</td>
                        <td><form method="POST" action="{{ route('admin.integrations.app-mail.suppressions.clear', $suppression) }}">@csrf<button class="btn btn-sm btn-outline-primary">Reactivar envíos tras revisar la dirección</button></form></td></tr>
                @empty<tr><td colspan="3" class="text-muted">No hay direcciones bloqueadas.</td></tr>@endforelse</tbody>
            </table></div>
        </div>
    </div>
    <div class="card">
        <div class="card-header"><h2 class="card-title">Recordatorios de registro detenidos</h2></div>
        <div class="card-body">
            <p>Cada representado recibe como máximo tres recordatorios de pago. Un pago confirmado o una respuesta registrada en sus tareas de atención detiene el seguimiento. Atención al Representado también puede registrar la respuesta desde aquí.</p>
            @if(request('recipient'))
                @php($responseClient = \App\Models\User::clients()->whereRaw('LOWER(email) = ?', [mb_strtolower(trim(request('recipient')))])->first())
                @if($responseClient)
                    <form method="POST" action="{{ route('admin.integrations.app-mail.response', $responseClient) }}" class="mb-3">@csrf<button class="btn btn-outline-primary">Registrar que {{ $responseClient->name }} respondió</button></form>
                @endif
            @endif
            <div class="table-responsive"><table class="table table-hover">
                <thead><tr><th>Representado</th><th>Correo</th><th>Motivo</th></tr></thead>
                <tbody>@forelse($followupStates as $state)<tr><td>{{ $state->user?->name }}</td><td>{{ $state->user?->email }}</td><td>{{ $state->stop_reason === 'limit_reached' ? 'Límite de tres recordatorios' : 'Representado respondió' }}</td></tr>
                @empty<tr><td colspan="3" class="text-muted">No hay seguimientos detenidos.</td></tr>@endforelse</tbody>
            </table></div>
        </div>
    </div>
    <div class="card">
        <div class="card-header"><h2 class="card-title">Historial de correo</h2></div>
        <div class="card-body">
            <p>«Aceptado por el proveedor» confirma el envío. «Entregado al servidor» confirma la recepción por el servidor de destino; no identifica la carpeta donde terminó.</p>
            @unless($eventsConfigured)<p class="text-muted">Las confirmaciones de entrega, rebotes y quejas de SES aparecerán al configurar sus notificaciones de eventos.</p>@endunless
            <form method="GET" class="form-row align-items-end mb-3">
                <div class="col-md-6 form-group"><label for="mail-recipient-filter">Buscar destinatario</label><input id="mail-recipient-filter" class="form-control" name="recipient" value="{{ request('recipient') }}" maxlength="254"></div>
                <div class="col-auto form-group"><button class="btn btn-outline-secondary">Buscar</button></div>
            </form>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead><tr><th>Fecha (Caracas)</th><th>Proveedor</th><th>Destinatarios</th><th>Asunto</th><th>Estado</th></tr></thead>
                    <tbody>
                        @forelse($logs ?? [] as $log)
                            <tr>
                                <td class="text-nowrap">{{ $log->created_at->timezone('America/Caracas')->format('d/m/Y H:i') }}</td>
                                <td>{{ $log->provider === 'ses' ? 'Amazon SES' : 'Habitual' }} @if($log->is_test)<span class="badge badge-light">Prueba</span>@endif</td>
                                <td>{{ implode(', ', $log->recipients) }}</td>
                                <td>{{ $log->subject }}</td>
                                <td>{{ \App\Models\AppMailLog::STATUSES[$log->status] ?? $log->status }} @if($log->error_code)<small class="d-block text-muted">{{ $log->error_code }}</small>@endif</td>
                            </tr>
                        @empty<tr><td colspan="5" class="text-muted">No hay envíos registrados.</td></tr>@endforelse
                    </tbody>
                </table>
            </div>
            @if($logs){{ $logs->links() }}@endif
        </div>
    </div>
@stop
