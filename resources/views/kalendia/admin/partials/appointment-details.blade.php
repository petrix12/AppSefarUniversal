<h3 class="h5">{{ $appointment->service_name ?: 'Reunión' }}</h3>
<p class="mb-1">{{ $appointment->user?->name ?: data_get($appointment->customer, 'name', 'Contacto de Kalendia') }}</p>
<p class="text-muted">{{ $appointment->staff_name ?: 'Equipo Sefar' }}</p>
<p><strong data-kalendia-instant="{{ $appointment->starts_at->toIso8601String() }}">{{ $appointment->starts_at->timezone(\App\Services\KalendiaTimezone::preferred())->format('d/m/Y H:i') }}</strong>
    · {{ $appointment->display_status }}<br><small class="text-muted">Hasta <span data-kalendia-instant="{{ $appointment->ends_at->toIso8601String() }}">{{ $appointment->ends_at->timezone(\App\Services\KalendiaTimezone::preferred())->format('d/m/Y H:i') }}</span> · <span data-kalendia-timezone-label>{{ \App\Services\KalendiaTimezone::preferred() }}</span></small></p>
@if($appointment->meeting_url && $appointment->status !== 'cancelled')
    <div class="form-group"><label for="kalendia-meeting-link">Enlace de reunión</label>
        <div class="input-group"><input id="kalendia-meeting-link" class="form-control" type="url" value="{{ $appointment->meeting_url }}" readonly>
            <div class="input-group-append"><button class="btn btn-outline-primary" type="button" data-copy-meeting-link>Copiar enlace</button></div></div>
    </div>
@endif
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
        <div class="row"><div class="col-md-6 form-group"><label for="appointment-date">Nueva fecha local</label><input id="appointment-date" type="date" name="date" class="form-control" data-kalendia-local-date min="{{ now(\App\Services\KalendiaTimezone::preferred())->format('Y-m-d') }}" required></div><div class="col-md-6 form-group"><label for="appointment-time">Hora local</label><input id="appointment-time" type="time" name="time" class="form-control" required></div></div>
        <small class="d-block text-muted mb-3">Se comprueba la disponibilidad real del mismo profesional. No se permite reagendar a fines de semana o al pasado.</small>
        <button class="btn btn-primary" type="submit">Reagendar en Kalendia</button>
    </form>
    <details><summary class="btn btn-outline-danger">Cancelar cita</summary>
        <form method="POST" action="{{ route('admin.kalendia.change', $appointment) }}" class="mt-3">@csrf<input type="hidden" name="operation_id" value="{{ \Illuminate\Support\Str::uuid() }}"><input type="hidden" name="action" value="cancel">
            <p>¿Confirmas la cancelación de esta cita? Esta acción no procesa un reembolso.</p>
            <button class="btn btn-danger" type="submit">Sí, cancelar cita</button>
        </form>
    </details>
@endif
