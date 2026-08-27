@extends('adminlte::page')

@section('title', $rule->exists ? 'Editar automatización' : 'Nueva automatización')

@section('content_header')
    <h1><i class="fas fa-bolt mr-2 text-warning"></i>{{ $rule->exists ? 'Editar automatización' : 'Nueva automatización' }}</h1>
@stop

@section('content')
    <form method="POST" action="{{ $rule->exists ? route('automations.update', $rule) : route('automations.store') }}">
        @csrf
        @if($rule->exists) @method('PUT') @endif
        <div class="card card-outline card-primary">
            <div class="card-body">
                <div class="row"><div class="col-md-8 form-group"><label>Nombre *</label><input name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $rule->name) }}" required>@error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    <div class="col-md-4 form-group"><label>Entidad</label><select name="entity_type" class="form-control"><option value="client" @selected(old('entity_type', $rule->entity_type) === 'client')>Cliente</option><option value="global" @selected(old('entity_type', $rule->entity_type) === 'global')>Global</option></select></div></div>
                <div class="form-group"><label>Descripción</label><textarea name="description" class="form-control" rows="2">{{ old('description', $rule->description) }}</textarea></div>
                <div class="row"><div class="col-md-4 form-group"><label>Tipo de disparador *</label><select name="trigger_type" class="form-control"><option value="event" @selected(old('trigger_type', $rule->trigger_type) === 'event')>Evento de la app</option><option value="date_field" @selected(old('trigger_type', $rule->trigger_type) === 'date_field')>Fecha de un campo</option><option value="schedule" @selected(old('trigger_type', $rule->trigger_type) === 'schedule')>Programación cron</option></select></div>
                    <div class="col-md-4 form-group"><label>Evento</label><input name="trigger_event" class="form-control" value="{{ old('trigger_event', $rule->trigger_event) }}" placeholder="client.field_changed"></div>
                    <div class="col-md-4 form-group"><label>Zona horaria *</label><input name="timezone" class="form-control" value="{{ old('timezone', $rule->timezone ?: config('app.timezone')) }}" required></div></div>
                <div class="form-group"><label>Expresión cron</label><input name="cron_expression" class="form-control" value="{{ old('cron_expression', $rule->cron_expression) }}" placeholder="0 9 * * 1-5"><small class="form-text text-muted">Solo para “Programación cron”. Ejemplo: <code>0 9 * * 1-5</code> ejecuta de lunes a viernes a las 9:00.</small></div>
                <div class="form-group"><label>Configuración del disparador (JSON)</label><textarea name="trigger_config_json" class="form-control font-monospace @error('trigger_config_json') is-invalid @enderror" rows="5">{{ old('trigger_config_json', $triggerConfigJson) }}</textarea>@error('trigger_config_json')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <small class="form-text text-muted">Fecha: <code>{"field_key":"fecha_vencimiento","offset_minutes":-43200,"catch_up":false}</code>. Cron para cliente específico: <code>{"client_id":123}</code>.</small></div>
                <div class="form-group"><label>Condiciones (JSON)</label><textarea name="conditions_json" class="form-control font-monospace @error('conditions_json') is-invalid @enderror" rows="5">{{ old('conditions_json', $conditionsJson) }}</textarea>@error('conditions_json')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <small class="form-text text-muted">Ejemplo: <code>{"all":[{"path":"field.new_value","operator":"equals","value":"Pendiente"}]}</code></small></div>
                <div class="form-group"><label>Acciones (JSON) *</label><textarea name="actions_json" class="form-control font-monospace @error('actions_json') is-invalid @enderror" rows="14" required>{{ old('actions_json', $actionsJson) }}</textarea>@error('actions_json')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <small class="form-text text-muted">Tipos: <code>create_task</code>, <code>notify_user</code>, <code>set_custom_field</code>, <code>move_workflow</code>. Usa <code>{{ '{{client.name}}' }}</code> dentro de textos. En tareas/notificaciones, <code>owner</code>, <code>client</code> o <code>user</code> determina el destinatario.</small></div>
                <div class="custom-control custom-switch"><input type="checkbox" class="custom-control-input" id="is_active" name="is_active" value="1" @checked(old('is_active', $rule->is_active))><label class="custom-control-label" for="is_active">Regla activa</label></div>
            </div>
            <div class="card-footer d-flex justify-content-between"><a href="{{ route('automations.index') }}" class="btn btn-outline-secondary">Cancelar</a><button class="btn btn-primary"><i class="fas fa-save mr-1"></i>Guardar automatización</button></div>
        </div>
    </form>
@stop
