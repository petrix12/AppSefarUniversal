<details class="mb-3" data-platform-booking data-options-url="{{ route('admin.platforms.booking',[$provider,$account,$record]) }}">
    <summary>Crear reunión con este contacto</summary>
    <p class="text-muted mt-2">Selecciona un servicio sin cobro en Kalendia. Las consultas con precio se contratan y pagan desde la App.</p>
    <button class="btn btn-outline-secondary mb-3" type="button" data-booking-load>Cargar servicios disponibles</button>
    <form method="POST" action="{{ route('admin.platforms.operate',[$provider,$account,$record]) }}" data-platform-form data-booking-form hidden>@csrf
        <input type="hidden" name="operation_id" value="{{ \Illuminate\Support\Str::uuid() }}"><input type="hidden" name="action" value="create_appointment">
        <div class="form-group"><label for="booking-service">Servicio</label><select class="form-control" id="booking-service" name="service_id" required></select></div>
        <div class="form-group"><label for="booking-timezone">Zona horaria</label><select class="form-control" id="booking-timezone" name="timezone" required data-booking-timezone>
            @foreach(array_unique([\App\Services\KalendiaTimezone::preferred(),'America/Bogota','America/Caracas','America/Santiago','Europe/Madrid']) as $zone)<option>{{ $zone }}</option>@endforeach</select></div>
        <div class="form-group"><label for="booking-date">Fecha local</label><input class="form-control" id="booking-date" name="date" type="date" required data-kalendia-local-date></div>
        <button class="btn btn-outline-primary mb-3" type="button" data-booking-slots>Consultar horarios</button>
        <div class="form-group"><label for="booking-slot">Horario local disponible</label><select class="form-control" id="booking-slot" name="slot" required><option value="">Consulta primero los horarios</option></select></div>
        <div class="form-group"><label for="booking-notes">Observaciones</label><textarea class="form-control" id="booking-notes" name="notes" maxlength="2000"></textarea></div>
        <label class="d-block"><input type="checkbox" name="confirmed" value="1" required> Confirmo esta reserva en el horario y zona indicados.</label><button class="btn btn-primary" type="submit">Crear reunión</button>
    </form>
</details>
