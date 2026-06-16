@php
    $rulesByDay = $calendar ? $calendar->availabilityRules->keyBy('weekday') : collect();
@endphp

@if($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="card">
    <div class="card-body">
        <div class="row">
            <div class="col-md-6 form-group">
                <label>Nombre</label>
                <input type="text" name="nombre" class="form-control" value="{{ old('nombre', $calendar->nombre ?? '') }}" required>
            </div>

            <div class="col-md-6 form-group">
                <label>Servicio asociado</label>
                <select name="servicio_id" class="form-control">
                    <option value="">General</option>
                    @foreach($servicios as $servicio)
                        <option value="{{ $servicio->id }}" {{ (string) old('servicio_id', $calendar->servicio_id ?? '') === (string) $servicio->id ? 'selected' : '' }}>
                            {{ $servicio->nombre }} ({{ $servicio->id_hubspot }})
                        </option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="row">
            <div class="col-md-4 form-group">
                <label>Zona horaria</label>
                <input type="text" name="timezone" class="form-control" value="{{ old('timezone', $calendar->timezone ?? 'America/Caracas') }}" required>
            </div>

            <div class="col-md-4 form-group">
                <label>Duracion de cada consulta</label>
                <input type="number" name="slot_duration_minutes" class="form-control" min="15" max="480" value="{{ old('slot_duration_minutes', $calendar->slot_duration_minutes ?? 60) }}" required>
            </div>

            <div class="col-md-4 form-group">
                <label>Buffer entre consultas</label>
                <input type="number" name="buffer_minutes" class="form-control" min="0" max="240" value="{{ old('buffer_minutes', $calendar->buffer_minutes ?? 0) }}">
            </div>
        </div>

        <div class="form-group">
            <label>Descripcion interna</label>
            <textarea name="descripcion" class="form-control" rows="3">{{ old('descripcion', $calendar->descripcion ?? '') }}</textarea>
        </div>

        <div class="form-check mb-3">
            <input type="hidden" name="activo" value="0">
            <input type="checkbox" class="form-check-input" name="activo" value="1" id="activo" {{ old('activo', $calendar->activo ?? true) ? 'checked' : '' }}>
            <label class="form-check-label" for="activo">Activo</label>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <strong>Disponibilidad semanal</strong>
    </div>
    <div class="card-body table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Dia</th>
                    <th>Activo</th>
                    <th>Desde</th>
                    <th>Hasta</th>
                    <th>Slot especial</th>
                    <th>Buffer especial</th>
                </tr>
            </thead>
            <tbody>
                @foreach($weekdays as $day => $label)
                    @php
                        $rule = $rulesByDay->get($day);
                    @endphp
                    <tr>
                        <td>{{ $label }}</td>
                        <td>
                            <input type="checkbox" name="availability[{{ $day }}][enabled]" value="1" {{ old("availability.$day.enabled", $rule ? true : false) ? 'checked' : '' }}>
                        </td>
                        <td>
                            <input type="time" name="availability[{{ $day }}][starts_at]" class="form-control" value="{{ old("availability.$day.starts_at", $rule ? substr($rule->starts_at, 0, 5) : '09:00') }}">
                        </td>
                        <td>
                            <input type="time" name="availability[{{ $day }}][ends_at]" class="form-control" value="{{ old("availability.$day.ends_at", $rule ? substr($rule->ends_at, 0, 5) : '17:00') }}">
                        </td>
                        <td>
                            <input type="number" name="availability[{{ $day }}][slot_duration_minutes]" class="form-control" min="15" max="480" value="{{ old("availability.$day.slot_duration_minutes", $rule->slot_duration_minutes ?? '') }}">
                        </td>
                        <td>
                            <input type="number" name="availability[{{ $day }}][buffer_minutes]" class="form-control" min="0" max="240" value="{{ old("availability.$day.buffer_minutes", $rule->buffer_minutes ?? 0) }}">
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<div class="text-right mb-4">
    <a href="{{ route('admin.consultation-calendars.index') }}" class="btn btn-secondary">Cancelar</a>
    <button type="submit" class="btn btn-primary">Guardar</button>
</div>
