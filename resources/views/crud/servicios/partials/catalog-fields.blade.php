@php
    $current = $servicio ?? null;
@endphp

<div class="row">
    <div class="col-md-3 py-2">
        <label class="block text-sm font-medium text-gray-700">Tipo</label>
        <select name="tipo" class="form-control" required>
            @foreach(['servicio' => 'Servicio', 'cos_fase' => 'Fase COS', 'consulta' => 'Consultoria', 'miscelaneo' => 'Miscelaneo'] as $value => $label)
                <option value="{{ $value }}" {{ old('tipo', $current->tipo ?? 'servicio') === $value ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
        @error('tipo')
            <small style="color:red">*{{ $message }}*</small>
        @enderror
    </div>

    <div class="col-md-3 py-2">
        <label class="block text-sm font-medium text-gray-700">Categoria</label>
        <input value="{{ old('categoria', $current->categoria ?? 'general') }}" type="text" name="categoria" class="form-control">
    </div>

    <div class="col-md-2 py-2">
        <label class="block text-sm font-medium text-gray-700">Moneda</label>
        <input value="{{ old('moneda', $current->moneda ?? 'EUR') }}" type="text" name="moneda" class="form-control" maxlength="3">
    </div>

    <div class="col-md-2 py-2">
        <label class="block text-sm font-medium text-gray-700">Duracion min.</label>
        <input value="{{ old('duracion_minutos', $current->duracion_minutos ?? null) }}" type="number" name="duracion_minutos" class="form-control" min="15" max="480">
    </div>

    <div class="col-md-2 py-2">
        <label class="block text-sm font-medium text-gray-700">Orden</label>
        <input value="{{ old('orden', $current->orden ?? 0) }}" type="number" name="orden" class="form-control" min="0">
    </div>
</div>

<hr>

<div class="row">
    <div class="col-md-3 py-2">
        <div class="form-check mt-4">
            <input type="hidden" name="monday_sync_enabled" value="0">
            <input class="form-check-input" type="checkbox" name="monday_sync_enabled" value="1" id="monday_sync_enabled" {{ old('monday_sync_enabled', $current->monday_sync_enabled ?? true) ? 'checked' : '' }}>
            <label class="form-check-label" for="monday_sync_enabled">Enviar nuevos registros a Monday</label>
        </div>
    </div>

    <div class="col-md-4 py-2">
        <label class="block text-sm font-medium text-gray-700">Tablero de Monday</label>
        @php($selectedMondayBoard = (string) old('monday_board_id', $current->monday_board_id ?? '878831315'))
        <select
            id="monday_board_id"
            name="monday_board_id"
            class="form-control"
            data-selected="{{ $selectedMondayBoard }}"
            data-options-url="{{ route('crud.servicios.monday.boards') }}"
        >
            @if($selectedMondayBoard !== '')
                <option value="{{ $selectedMondayBoard }}" selected>Tablero guardado ({{ $selectedMondayBoard }})</option>
            @endif
        </select>
        @error('monday_board_id')
            <small style="color:red">*{{ $message }}*</small>
        @enderror
    </div>

    <div class="col-md-5 py-2">
        <label class="block text-sm font-medium text-gray-700">Monday Grupo/Subtablero</label>
        @php($selectedMondayGroup = (string) old('monday_group_id', $current->monday_group_id ?? 'duplicate_of_en_proceso'))
        <select
            id="monday_group_id"
            name="monday_group_id"
            class="form-control"
            data-selected="{{ $selectedMondayGroup }}"
            data-options-url="{{ route('crud.servicios.monday.groups') }}"
        >
            @if($selectedMondayGroup !== '')
                <option value="{{ $selectedMondayGroup }}" selected>Grupo guardado ({{ $selectedMondayGroup }})</option>
            @endif
        </select>
        @error('monday_group_id')
            <small style="color:red">*{{ $message }}*</small>
        @enderror
    </div>
</div>

<p class="text-muted px-1">
    <span id="monday-catalog-status">Los nombres se cargan automáticamente desde Monday.</span>
</p>

<div class="row">
    <div class="col-md-4 py-2">
        <div class="form-check mt-4">
            <input type="hidden" name="activo" value="0">
            <input class="form-check-input" type="checkbox" name="activo" value="1" id="activo" {{ old('activo', $current->activo ?? true) ? 'checked' : '' }}>
            <label class="form-check-label" for="activo">Activo</label>
        </div>
    </div>

    <div class="col-md-4 py-2">
        <div class="form-check mt-4">
            <input type="hidden" name="visible_cliente" value="0">
            <input class="form-check-input" type="checkbox" name="visible_cliente" value="1" id="visible_cliente" {{ old('visible_cliente', $current->visible_cliente ?? false) ? 'checked' : '' }}>
            <label class="form-check-label" for="visible_cliente">Visible en tienda de clientes</label>
        </div>
    </div>

    <div class="col-md-4 py-2">
        <div class="form-check mt-4">
            <input type="hidden" name="requiere_agenda" value="0">
            <input class="form-check-input" type="checkbox" name="requiere_agenda" value="1" id="requiere_agenda" {{ old('requiere_agenda', $current->requiere_agenda ?? false) ? 'checked' : '' }}>
            <label class="form-check-label" for="requiere_agenda">Requiere agenda</label>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6 py-2">
        <label class="block text-sm font-medium text-gray-700">HubSpot Pipeline ID</label>
        <input value="{{ old('hubspot_pipeline_id', $current->hubspot_pipeline_id ?? null) }}" type="text" name="hubspot_pipeline_id" class="form-control">
    </div>

    <div class="col-md-6 py-2">
        <label class="block text-sm font-medium text-gray-700">HubSpot Stage ID</label>
        <input value="{{ old('hubspot_stage_id', $current->hubspot_stage_id ?? null) }}" type="text" name="hubspot_stage_id" class="form-control">
    </div>
</div>

<div class="row">
    <div class="col-12 py-2">
        <label class="block text-sm font-medium text-gray-700">Descripcion publica</label>
        <textarea name="descripcion_publica" class="form-control" rows="4">{{ old('descripcion_publica', $current->descripcion_publica ?? null) }}</textarea>
    </div>
</div>
