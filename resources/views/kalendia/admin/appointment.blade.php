@extends('adminlte::page')
@section('title', 'Gestionar cita')
@section('content_header')<h1>Gestionar cita</h1>@stop
@section('content')
    @include('kalendia.partials.messages')
    <div class="card"><div class="card-body">
        @include('kalendia.partials.timezone')
        <h2 class="h5">{{ $appointment->service_name ?: 'Reunión' }}</h2>
        <p>{{ $appointment->user?->name ?: data_get($appointment->customer, 'name', 'Contacto de Kalendia') }} · {{ $appointment->staff_name ?: 'Equipo Sefar' }}</p>
        <p><strong data-kalendia-instant="{{ $appointment->starts_at->toIso8601String() }}">{{ $appointment->starts_at->timezone(\App\Services\KalendiaTimezone::preferred())->format('d/m/Y H:i') }}</strong> · {{ $appointment->display_status }}</p>
        @if($appointment->meeting_url && $appointment->status !== 'cancelled')<a href="{{ $appointment->meeting_url }}" target="_blank" rel="noopener noreferrer" class="btn btn-outline-primary mb-3">Abrir enlace de reunión</a>@endif
        @if($operation)
            <div class="alert alert-info">Hay una operación pendiente de confirmación. Reintenta la misma operación antes de iniciar otro cambio.</div>
            <form method="POST" action="{{ route('admin.kalendia.change', $appointment) }}">@csrf<input type="hidden" name="operation_id" value="{{ $operationId }}"><input type="hidden" name="action" value="{{ $operation->action }}">
                <input type="hidden" name="timezone" value="America/Bogota">
                @foreach($operation->payload as $key=>$value)@if(in_array($key,['date','time'],true))<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif @endforeach
                <button class="btn btn-primary" type="submit">Comprobar cambio pendiente</button>
            </form>
        @elseif($appointment->status !== 'cancelled')
            <form method="POST" action="{{ route('admin.kalendia.change', $appointment) }}" class="mb-4">@csrf<input type="hidden" name="operation_id" value="{{ $operationId }}"><input type="hidden" name="action" value="reschedule">
                <input type="hidden" name="timezone" value="{{ \App\Services\KalendiaTimezone::preferred() }}" data-kalendia-timezone-input>
                <div class="row"><div class="col-md-5 form-group"><label for="appointment-date">Nueva fecha local</label><input id="appointment-date" type="date" name="date" class="form-control" data-kalendia-local-date min="{{ now(\App\Services\KalendiaTimezone::preferred())->format('Y-m-d') }}" required></div><div class="col-md-4 form-group"><label for="appointment-time">Hora local</label><input id="appointment-time" type="time" name="time" class="form-control" required></div></div>
                <small class="d-block text-muted mb-3">Se comprueba la disponibilidad real del mismo profesional. No se permite reagendar a fines de semana o al pasado.</small>
                <button class="btn btn-primary" type="submit">Reagendar en Kalendia</button>
            </form>
            <form method="POST" action="{{ route('admin.kalendia.change', $appointment) }}" onsubmit="return confirm('¿Cancelar esta cita en Kalendia? Esta acción no procesa un reembolso.');">@csrf<input type="hidden" name="operation_id" value="{{ \Illuminate\Support\Str::uuid() }}"><input type="hidden" name="action" value="cancel"><button class="btn btn-outline-danger" type="submit">Cancelar cita</button></form>
        @endif
    </div><div class="card-footer"><a href="{{ route('admin.kalendia.appointments', ['client'=>$appointment->user_id]) }}" class="btn btn-outline-secondary">Volver a reuniones</a></div></div>
@stop
