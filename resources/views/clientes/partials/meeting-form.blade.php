<form method="POST" action="{{ route('clientes.meeting.book', $purchase->id) }}" data-registration-agenda
      data-availability-url="{{ route('clientes.meeting.availability') }}" data-purchase="{{ $purchase->id }}"
      data-today="{{ $today }}" data-booking-ready="{{ $bookingReady ? '1' : '0' }}">
    @csrf
    <input type="hidden" name="timezone" value="{{ \App\Services\KalendiaTimezone::preferred() }}" data-kalendia-timezone-input>
    <div class="row">
        <div class="col-md-6 mb-3">
            <label for="meeting-date">{{ $purchase->source === \App\Models\KalendiaConsultationOrder::SOURCE ? 'Fecha de la consulta' : 'Fecha de la reunión' }}</label>
            <input id="meeting-date" name="date" type="date" class="form-control" min="{{ $today }}" value="{{ old('date') }}" aria-describedby="meeting-date-help" required>
            <small id="meeting-date-help" class="form-text text-muted">De lunes a viernes, en una fecha que no haya pasado.</small>
        </div>
        <div class="col-md-6 mb-3">
            <label for="meeting-slot">Horario disponible</label>
            <select id="meeting-slot" name="slot" class="form-control" required disabled><option value="">Selecciona primero una fecha</option></select>
            <small class="form-text text-muted">Hora local: <span data-kalendia-timezone-label>{{ \App\Services\KalendiaTimezone::preferred() }}</span>.</small>
        </div>
    </div>
    <p data-agenda-status role="status" aria-live="polite" class="text-muted"></p>
    <button class="btn btn-primary" type="submit" data-agenda-submit disabled>{{ $purchase->source === \App\Models\KalendiaConsultationOrder::SOURCE ? 'Enviar solicitud de consulta' : 'Enviar solicitud de reunión' }}</button>
</form>
